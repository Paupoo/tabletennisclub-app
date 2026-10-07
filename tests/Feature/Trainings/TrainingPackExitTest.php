<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\AddMemberToTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\CancelTrainingPackEnrolmentAction;
use App\Actions\ClubAdmin\Subscriptions\LeaveTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\ReconcileTrainingPackAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackEnrolmentCancelledNotification;
use App\Domains\Trainings\Services\TrainingPackExit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
 * Retirer une place validée, c'était toujours un départ daté du jour : les mois
 * entre l'entrée et le clic restaient facturés. Un joueur inscrit par erreur
 * puis retiré gardait donc sa facture, sans aucun moyen de dire « cette
 * inscription n'aurait jamais dû exister ».
 *
 * Deux sorties désormais : un départ à une date choisie, au pro rata, et une
 * annulation pour erreur d'encodage, qui efface la ligne comme si de rien
 * n'était — tant que personne n'a pointé le joueur présent.
 */

/** Octobre → avril, 7 mois, 210 € : 30 € le mois entamé. */
function exitTestPack(): TrainingPack
{
    return TrainingPack::factory()->create([
        'price' => 210,
        'allow_discount' => true,
        'max_participants' => 20,
        'pack_start_date' => '2026-10-01',
        'pack_end_date' => '2027-04-30',
    ]);
}

/** Récréative (60 €), avec le pack validé depuis le début et la facture payée ou non. */
function exitTestSubscription(TrainingPack $pack, bool $paid = false): Subscription
{
    $subscription = Subscription::factory()->create(['is_competitive' => false, 'status' => $paid ? 'paid' : 'confirmed']);
    $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);
    (new CalculatePriceAction)($subscription);

    $subscription->payments()->create([
        'reference' => 'EXIT-' . $subscription->id,
        'amount_due' => 270,
        'amount_paid' => $paid ? 270 : 0,
        'status' => $paid ? 'paid' : 'pending',
    ]);

    return $subscription->fresh();
}

function exitTestSession(TrainingPack $pack, Subscription $subscription, string $on, string $status): Training
{
    $start = Carbon::parse($on)->setTime(18, 0);

    $session = Training::factory()->create([
        'training_pack_id' => $pack->id,
        'start' => $start,
        'end' => $start->copy()->addMinutes(90),
        'attendance_taken_at' => $start->copy()->addHours(2),
    ]);

    $session->trainees()->attach($subscription->user_id, ['status' => $status]);

    return $session;
}

function exitTestMarks(Subscription $subscription): array
{
    return DB::table('training_user')
        ->join('trainings', 'trainings.id', '=', 'training_user.training_id')
        ->where('training_user.user_id', $subscription->user_id)
        ->orderBy('trainings.start')
        ->pluck('training_user.status', 'trainings.start')
        ->mapWithKeys(fn (string $status, string $start): array => [substr($start, 0, 10) => $status])
        ->all();
}

describe('Départ daté', function (): void {

    beforeEach(fn () => $this->travelTo('2027-01-15'));

    test('a departure dated in the past bills the months up to that date only', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack, paid: true);

        $refundable = (new LeaveTrainingPackAction)($subscription, $pack, notifyUser: false, endsOn: '2026-11-30');

        // Octobre et novembre : 2 mois sur 7 = 60 €. Le reste (150 €) est rendu.
        expect($subscription->fresh()->amount_due)->toBe(60.0 + 60.0)
            ->and($refundable)->toBe(150.0)
            ->and($subscription->trainingPacks()->first()->pivot->ends_on)->toBe('2026-11-30');
    })->group('training', 'prorata', 'refund');

    test('without a date the departure is still dated today', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack);

        (new LeaveTrainingPackAction)($subscription, $pack, notifyUser: false);

        expect($subscription->trainingPacks()->first()->pivot->ends_on)->toBe('2027-01-15');
    })->group('training', 'prorata');

    test('a departure cannot be dated in the future', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack);

        expect(fn () => (new LeaveTrainingPackAction)($subscription, $pack, notifyUser: false, endsOn: '2027-01-16'))
            ->toThrow(DomainException::class);

        expect($subscription->trainingPacks()->first()->pivot->status)->toBe('enrolled');
    })->group('training', 'prorata');

    test('a departure cannot precede the arrival', function (): void {
        $pack = exitTestPack();
        $subscription = Subscription::factory()->create(['is_competitive' => false, 'status' => 'confirmed']);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled', 'starts_on' => '2026-12-05']);

        expect(fn () => (new LeaveTrainingPackAction)($subscription, $pack, notifyUser: false, endsOn: '2026-12-04'))
            ->toThrow(DomainException::class);

        expect((new TrainingPackExit)->earliestDeparture($subscription, $pack)->toDateString())->toBe('2026-12-05');
    })->group('training', 'prorata');

    test('a departure cannot precede the last session the member was marked at', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack);
        exitTestSession($pack, $subscription, '2026-11-12', 'present');
        exitTestSession($pack, $subscription, '2026-11-19', 'excused');
        exitTestSession($pack, $subscription, '2026-11-26', 'absent');

        expect((new TrainingPackExit)->earliestDeparture($subscription, $pack)->toDateString())->toBe('2026-11-19');

        expect(fn () => (new LeaveTrainingPackAction)($subscription, $pack, notifyUser: false, endsOn: '2026-11-18'))
            ->toThrow(DomainException::class);
    })->group('training', 'prorata', 'attendance');

    test('the absences marked after the departure are erased, the rest stays', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack);
        exitTestSession($pack, $subscription, '2026-10-15', 'absent');
        exitTestSession($pack, $subscription, '2026-10-22', 'present');
        exitTestSession($pack, $subscription, '2026-11-05', 'absent');
        exitTestSession($pack, $subscription, '2026-12-10', 'absent');

        (new LeaveTrainingPackAction)($subscription, $pack, notifyUser: false, endsOn: '2026-10-31');

        expect(exitTestMarks($subscription))->toBe([
            '2026-10-15' => 'absent',
            '2026-10-22' => 'present',
        ]);
    })->group('training', 'prorata', 'attendance');

});

describe('Annulation pour erreur d\'encodage', function (): void {

    beforeEach(function (): void {
        $this->travelTo('2027-01-15');
        Notification::fake();
    });

    test('the line disappears and the complement claimed for it is cancelled', function (): void {
        $pack = exitTestPack();
        $subscription = Subscription::factory()->create(['is_competitive' => false, 'status' => 'confirmed']);
        (new CalculatePriceAction)($subscription);
        $subscription->payments()->create(['reference' => 'BASE', 'amount_due' => 60, 'amount_paid' => 60, 'status' => 'paid']);

        // L'erreur : le pack ajouté depuis son début, un complément réclamé.
        $complement = (new AddMemberToTrainingPackAction)($subscription, $pack, '2026-10-01');

        $refundable = (new CancelTrainingPackEnrolmentAction)($subscription->fresh(), $pack);

        expect($refundable)->toBe(0.0)
            ->and(DB::table('subscription_training_pack')->where('subscription_id', $subscription->id)->exists())->toBeFalse()
            ->and($subscription->fresh()->amount_due)->toBe(60.0)
            ->and($complement->fresh()->status)->toBe('cancelled');
    })->group('training', 'money');

    test('money already received for the pack is offered back in full', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack, paid: true);

        $refundable = (new CancelTrainingPackEnrolmentAction)($subscription, $pack);

        // Aucun mois consommé : les 210 € du pack, pas seulement la part à venir.
        expect($refundable)->toBe(210.0)
            ->and($subscription->fresh()->amount_due)->toBe(60.0);
    })->group('training', 'money', 'refund');

    test('a line already marked as left can still be cancelled', function (): void {
        $pack = exitTestPack();
        $subscription = Subscription::factory()->create(['is_competitive' => false, 'status' => 'confirmed']);
        (new CalculatePriceAction)($subscription);
        $subscription->payments()->create(['reference' => 'BASE', 'amount_due' => 60, 'amount_paid' => 60, 'status' => 'paid']);
        $complement = (new AddMemberToTrainingPackAction)($subscription, $pack, '2026-10-01');

        // Le cas de prod : retirée par un départ, la ligne garde 4 mois facturés.
        (new LeaveTrainingPackAction)($subscription->fresh(), $pack, notifyUser: false);
        expect($complement->fresh()->amount_due)->toBe(120.0);

        (new CancelTrainingPackEnrolmentAction)($subscription->fresh(), $pack);

        expect($subscription->fresh()->amount_due)->toBe(60.0)
            ->and($complement->fresh()->status)->toBe('cancelled')
            ->and($subscription->trainingPacks()->exists())->toBeFalse();
    })->group('training', 'money');

    test('a member marked present at a session cannot be cancelled', function (string $status): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack);
        exitTestSession($pack, $subscription, '2026-11-12', $status);

        expect(fn () => (new CancelTrainingPackEnrolmentAction)($subscription, $pack))
            ->toThrow(DomainException::class);

        expect($subscription->trainingPacks()->first()->pivot->status)->toBe('enrolled')
            ->and($subscription->fresh()->amount_due)->toBe(270.0);
    })->with(['present', 'excused'])->group('training', 'attendance');

    test('the absences the enrolment produced are erased', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack);
        exitTestSession($pack, $subscription, '2026-10-15', 'absent');
        exitTestSession($pack, $subscription, '2026-11-15', 'absent');

        // Une autre séance du membre, hors de ce pack : elle ne le regarde pas.
        $elsewhere = Training::factory()->past(30)->create();
        $elsewhere->trainees()->attach($subscription->user_id, ['status' => 'absent']);

        (new CancelTrainingPackEnrolmentAction)($subscription, $pack);

        expect(DB::table('training_user')->where('user_id', $subscription->user_id)->pluck('training_id')->all())
            ->toBe([$elsewhere->id]);
    })->group('training', 'attendance');

    test('a waiting or pending line is not cancelled this way', function (string $status): void {
        $pack = exitTestPack();
        $subscription = Subscription::factory()->create(['status' => 'confirmed']);
        $subscription->trainingPacks()->attach($pack->id, ['status' => $status]);

        expect(fn () => (new CancelTrainingPackEnrolmentAction)($subscription, $pack))
            ->toThrow(DomainException::class);
    })->with(['pending', 'waiting'])->group('training');

    test('the cancellation is written in the activity log', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack);

        (new CancelTrainingPackEnrolmentAction)($subscription, $pack);

        $entry = Activity::where('event', 'training_pack_enrolment_cancelled')->sole();

        expect($entry->subject_id)->toBe($subscription->id)
            ->and($entry->properties['training_pack_id'])->toBe($pack->id)
            ->and($entry->properties['amount_due_before'])->toEqual(270)
            ->and($entry->properties['amount_due_after'])->toEqual(60);
    })->group('training');

    test('the member is told the payment request is void', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack);

        (new CancelTrainingPackEnrolmentAction)($subscription, $pack);

        Notification::assertSentTo(
            $subscription->user,
            TrainingPackEnrolmentCancelledNotification::class,
            fn (TrainingPackEnrolmentCancelledNotification $n): bool => $n->reducedAmount === 210.0 && $n->refundAmount === 0.0,
        );
    })->group('training', 'notifications');

});

describe('Aperçu', function (): void {

    beforeEach(fn () => $this->travelTo('2027-01-15'));

    test('the departure preview announces what the departure does', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack, paid: true);

        $preview = (new TrainingPackExit)->preview($subscription, $pack, '2026-11-30');
        $refundable = (new LeaveTrainingPackAction)($subscription, $pack, notifyUser: false, endsOn: '2026-11-30');

        expect($preview['line_amount'])->toBe(60.0)
            ->and($preview['amount_due'])->toBe((float) $subscription->fresh()->amount_due)
            ->and($preview['refund'])->toBe($refundable);
    })->group('training', 'prorata');

    test('the cancellation preview announces what the cancellation does', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack);
        exitTestSession($pack, $subscription, '2026-10-15', 'absent');

        $preview = (new TrainingPackExit)->preview($subscription, $pack, null);

        expect($preview['line_amount'])->toBe(0.0)
            ->and($preview['amount_due'])->toBe(60.0)
            ->and($preview['reduced'])->toBe(210.0)
            ->and($preview['refund'])->toBe(0.0)
            ->and($preview['absences'])->toBe(1)
            ->and($subscription->fresh()->amount_due)->toBe(270.0);
    })->group('training');

});

describe('Ajustement manuel à la baisse', function (): void {

    test('forcing a lower amount lowers the payment still claimed', function (): void {
        $this->travelTo('2027-01-15');
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack);

        (new ReconcileTrainingPackAction)($subscription, $pack, overrideAmount: 0.0, overrideReason: 'Erreur d\'encodage');

        expect($subscription->fresh()->amount_due)->toBe(60.0)
            ->and($subscription->payments()->sole()->amount_due)->toBe(60.0);
    })->group('training', 'money');

    test('forcing a lower amount on a paid affiliation opens a refund', function (): void {
        Notification::fake();
        $this->travelTo('2027-01-15');
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack, paid: true);

        (new ReconcileTrainingPackAction)($subscription, $pack, overrideAmount: 100.0, overrideReason: 'Arrangement');

        $refund = $subscription->payments()->where('status', 'to_refund')->sole();

        expect($refund->amount_due)->toBe(110.0);
    })->group('training', 'money', 'refund');

});

describe('Modale de sortie', function (): void {

    beforeEach(function (): void {
        $this->travelTo('2027-01-15');
        Notification::fake();
        $this->manager = User::factory()->isCommitteeMember()
            ->withRole(Role::MEMBERS)
            ->create();
    });

    test('nothing is chosen in advance, and nothing happens until something is', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack);

        Livewire::actingAs($this->manager)
            ->test('admin.shared.training-pack-exit')
            ->call('open', $subscription->id, $pack->id)
            ->assertSet('mode', '')
            ->assertSet('endsOn', '2027-01-15')
            ->call('confirm')
            ->assertSet('modal', true);

        expect($subscription->trainingPacks()->first()->pivot->status)->toBe('enrolled');
    })->group('training');

    test('a dated departure goes through with its preview', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack, paid: true);

        Livewire::actingAs($this->manager)
            ->test('admin.shared.training-pack-exit')
            ->call('open', $subscription->id, $pack->id)
            ->set('mode', 'departure')
            ->set('endsOn', '2026-11-30')
            ->assertSeeHtml('data-testid="exit-preview"')
            ->assertSee('150.00 €')
            ->call('confirm')
            ->assertSet('modal', false);

        expect($subscription->trainingPacks()->first()->pivot->ends_on)->toBe('2026-11-30')
            ->and($subscription->payments()->where('status', 'to_refund')->sole()->amount_due)->toBe(150.0);
    })->group('training', 'refund');

    test('an encoding error erases the line and refunds what was paid', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack, paid: true);

        Livewire::actingAs($this->manager)
            ->test('admin.shared.training-pack-exit')
            ->call('open', $subscription->id, $pack->id)
            ->set('mode', 'error')
            ->call('confirm')
            ->assertSet('modal', false);

        expect($subscription->trainingPacks()->exists())->toBeFalse()
            ->and($subscription->payments()->where('status', 'to_refund')->sole()->amount_due)->toBe(210.0);
    })->group('training', 'refund');

    test('a line already left only offers the encoding error', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack);
        (new LeaveTrainingPackAction)($subscription, $pack, notifyUser: false);

        Livewire::actingAs($this->manager)
            ->test('admin.shared.training-pack-exit')
            ->call('open', $subscription->id, $pack->id)
            ->assertSet('onlyError', true)
            ->assertDontSee(__('They stopped coming'))
            ->set('mode', 'departure')
            ->call('confirm')
            ->assertSet('modal', true);
    })->group('training');

    test('the encoding error is greyed out once the member was marked present', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack);
        exitTestSession($pack, $subscription, '2026-11-12', 'present');

        Livewire::actingAs($this->manager)
            ->test('admin.shared.training-pack-exit')
            ->call('open', $subscription->id, $pack->id)
            ->assertSee(trans_choice('{1}Marked at one session by the coach: they came.|[2,*]Marked at :count sessions by the coach: they came.', 1, ['count' => 1]))
            ->set('mode', 'error')
            ->call('confirm')
            ->assertSet('modal', true);

        expect($subscription->trainingPacks()->first()->pivot->status)->toBe('enrolled');
    })->group('training', 'attendance');

    test('a delegation that cannot touch an affiliation cannot open it', function (): void {
        $coach = User::factory()->isCommitteeMember()
            ->withRole(Role::TRAININGS)
            ->create();

        Livewire::actingAs($coach)
            ->test('admin.shared.training-pack-exit')
            ->call('open', 1, 1)
            ->assertForbidden();

        Livewire::actingAs($coach)
            ->test('admin.shared.training-pack-exit')
            ->call('confirm')
            ->assertForbidden();
    })->group('training');

    test('the affiliations screen offers to cancel a left line', function (): void {
        $pack = exitTestPack();
        $subscription = exitTestSubscription($pack);
        (new LeaveTrainingPackAction)($subscription, $pack, notifyUser: false);

        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-admin.users.registrations')
            ->call('review', $subscription->id)
            ->assertSeeHtml('openPackExit(' . $subscription->id . ', ' . $pack->id . ')');
    })->group('training');

});
