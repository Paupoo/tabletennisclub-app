<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\ApproveTrainingPacksAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\LeaveTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\ReconcileTrainingPackAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingPackProrata;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

const PRORATA_REGISTRATIONS_COMPONENT = 'pages::club-admin.users.registrations';

/**
 * Pack de référence de la décision D2 : octobre → avril, 7 mois, 210 €.
 *
 * @param  array<string, mixed>  $overrides
 */
function referencePack(array $overrides = []): TrainingPack
{
    return TrainingPack::factory()->create(array_merge([
        'price' => 210,
        'allow_discount' => true,
        'max_participants' => 20,
        'pack_start_date' => '2026-10-01',
        'pack_end_date' => '2027-04-30',
    ], $overrides));
}

function recreativeSubscription(): Subscription
{
    return Subscription::factory()->create(['is_competitive' => false, 'status' => 'confirmed']);
}

describe('Pro rata — mois calendaires entamés', function (): void {

    test('arriving mid-pack bills the remaining months only', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack();

        // Arrivée le 12 janvier → janvier, février, mars, avril = 4 mois sur 7.
        $subscription->trainingPacks()->attach($pack->id, [
            'status' => 'enrolled',
            'starts_on' => '2027-01-12',
        ]);

        (new CalculatePriceAction)($subscription);

        // 210 × 4/7 = 120 €
        expect($subscription->fresh()->amount_due)->toBe(60.0 + 120.0);
    })->group('training', 'pricing', 'prorata');

    test('a month started counts as a whole month', function (): void {
        $lateJanuary = recreativeSubscription();
        $firstOfJanuary = recreativeSubscription();
        $pack = referencePack();

        $lateJanuary->trainingPacks()->attach($pack->id, ['status' => 'enrolled', 'starts_on' => '2027-01-31']);
        $firstOfJanuary->trainingPacks()->attach($pack->id, ['status' => 'enrolled', 'starts_on' => '2027-01-01']);

        (new CalculatePriceAction)($lateJanuary);
        (new CalculatePriceAction)($firstOfJanuary);

        expect($lateJanuary->fresh()->amount_due)->toBe($firstOfJanuary->fresh()->amount_due)
            ->and($lateJanuary->fresh()->amount_due)->toBe(180.0);
    })->group('training', 'pricing', 'prorata');

    test('leaving early bills the months attended only', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack();

        // Octobre → décembre = 3 mois sur 7.
        $subscription->trainingPacks()->attach($pack->id, [
            'status' => 'enrolled',
            'ends_on' => '2026-12-20',
        ]);

        (new CalculatePriceAction)($subscription);

        // 210 × 3/7 = 90 €
        expect($subscription->fresh()->amount_due)->toBe(60.0 + 90.0);
    })->group('training', 'pricing', 'prorata');

    test('joining late and leaving early bills the overlap only', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack();

        // Décembre → février = 3 mois sur 7.
        $subscription->trainingPacks()->attach($pack->id, [
            'status' => 'enrolled',
            'starts_on' => '2026-12-05',
            'ends_on' => '2027-02-02',
        ]);

        (new CalculatePriceAction)($subscription);

        expect($subscription->fresh()->amount_due)->toBe(60.0 + 90.0);
    })->group('training', 'pricing', 'prorata');

    test('a line without dates keeps the full price (non-regression)', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack();

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        (new CalculatePriceAction)($subscription);

        expect($subscription->fresh()->amount_due)->toBe(60.0 + 210.0)
            ->and(DB::table('subscription_training_pack')->value('starts_on'))->toBeNull()
            ->and(DB::table('subscription_training_pack')->value('ends_on'))->toBeNull();
    })->group('training', 'pricing', 'prorata');

    test('dates wider than the pack never bill more than the pack', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack();

        $subscription->trainingPacks()->attach($pack->id, [
            'status' => 'enrolled',
            'starts_on' => '2026-08-01',
            'ends_on' => '2027-08-31',
        ]);

        (new CalculatePriceAction)($subscription);

        expect($subscription->fresh()->amount_due)->toBe(60.0 + 210.0);
    })->group('training', 'pricing', 'prorata');

    test('leaving before the pack starts bills nothing', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack();

        $subscription->trainingPacks()->attach($pack->id, [
            'status' => 'left',
            'ends_on' => '2026-09-15',
        ]);

        (new CalculatePriceAction)($subscription);

        expect($subscription->fresh()->amount_due)->toBe(60.0);
    })->group('training', 'pricing', 'prorata');

    test('a pack cannot exist without the period it covers', function (): void {
        expect(fn () => TrainingPack::factory()->create(['pack_start_date' => null]))
            ->toThrow(QueryException::class);
    })->group('training', 'pricing', 'prorata');

    test('a pack whose dates went missing is reported, not billed on a guess', function (): void {
        $pack = referencePack();
        $pack->pack_start_date = null;

        expect(fn (): float => (new TrainingPackProrata)->ratio($pack, '2027-01-12', null))
            ->toThrow(RuntimeException::class);
    })->group('training', 'pricing', 'prorata');

    test('the ratio is exposed unrounded for the UI', function (): void {
        $pack = referencePack();
        $prorata = new TrainingPackProrata;

        expect($prorata->ratio($pack, '2027-01-12', null))->toBe(4 / 7)
            ->and($prorata->ratio($pack, null, null))->toBe(1.0)
            ->and($prorata->ratio($pack, null, '2026-09-01'))->toBe(0.0);
    })->group('training', 'pricing', 'prorata');

});

describe('Pro rata — remise avant pro rata (D5)', function (): void {

    test('the multi-pack discount applies before the pro rata', function (): void {
        $subscription = recreativeSubscription();
        $packA = referencePack();
        $packB = referencePack();

        $subscription->trainingPacks()->attach($packA->id, ['status' => 'enrolled']);
        $subscription->trainingPacks()->attach($packB->id, ['status' => 'enrolled', 'starts_on' => '2027-01-12']);

        (new CalculatePriceAction)($subscription);

        // A : 200 (210 − 10 de remise) sur toute la durée.
        // B : (210 − 10) × 4/7 = 114.29 — la remise s'applique d'abord.
        expect(round(200.0 * 4 / 7, 2))->toBe(114.29)
            ->and($subscription->fresh()->amount_due)->toBe(round(60.0 + 200.0 + 114.29, 2));
    })->group('training', 'pricing', 'prorata');

    test('the family discount also applies before the pro rata', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack();

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled', 'starts_on' => '2027-01-12']);

        (new CalculatePriceAction)($subscription, 2);

        expect($subscription->fresh()->amount_due)->toBe(round(60.0 + 114.29, 2));
    })->group('training', 'pricing', 'prorata');

    test('the sum of the billed lines matches the stored total to the cent', function (): void {
        $subscription = recreativeSubscription();
        $packA = referencePack(['price' => 100]);
        $packB = referencePack(['price' => 100]);

        $subscription->trainingPacks()->attach($packA->id, ['status' => 'enrolled', 'starts_on' => '2027-01-12']);
        $subscription->trainingPacks()->attach($packB->id, ['status' => 'enrolled', 'starts_on' => '2026-12-01']);

        $quote = (new CalculatePriceAction)->quote($subscription);
        (new CalculatePriceAction)($subscription);

        $sum = round($quote['subscription_price'] + array_sum(array_column($quote['lines'], 'amount')), 2);

        expect($sum)->toBe($quote['total'])
            ->and($subscription->fresh()->amount_due)->toBe($sum);
    })->group('training', 'pricing', 'prorata');

});

describe('Départ — statut left', function (): void {

    test('leaving dates the line, frees the spot and lets the member come back', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack(['max_participants' => 1]);

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        (new LeaveTrainingPackAction)($subscription, $pack);

        $pivot = $subscription->trainingPacks()->where('training_pack_id', $pack->id)->first()->pivot;

        expect($pivot->status)->toBe('left')
            ->and($pivot->ends_on)->toBe(today()->toDateString())
            ->and($pack->fresh()->hasAvailableSpot())->toBeTrue();

        // Le garde-fou « déjà inscrit » doit ignorer la ligne quittée.
        $status = (new EnrollInTrainingPackAction)($subscription, $pack);

        expect($status)->toBe('pending')
            ->and(DB::table('subscription_training_pack')
                ->where('subscription_id', $subscription->id)
                ->where('training_pack_id', $pack->id)
                ->count())->toBe(1);
    })->group('training', 'enrollment', 'prorata');

    test('re-enrolling clears the previous stint dates and forced amount', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack();

        $subscription->trainingPacks()->attach($pack->id, [
            'status' => 'left',
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-12-01',
            'override_amount' => 4200,
            'override_reason' => 'Arrangement',
        ]);

        (new EnrollInTrainingPackAction)($subscription, $pack);

        $row = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->where('training_pack_id', $pack->id)
            ->first();

        expect($row->status)->toBe('pending')
            ->and($row->ends_on)->toBeNull()
            ->and($row->override_amount)->toBeNull()
            ->and($row->override_reason)->toBeNull();
    })->group('training', 'enrollment', 'prorata');

    test('leaving promotes the next waitlisted member', function (): void {
        $pack = referencePack(['max_participants' => 1]);

        $enrolled = recreativeSubscription();
        $enrolled->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $waiter = Subscription::factory()->for($enrolled->season, 'season')->create();
        $waiter->trainingPacks()->attach($pack->id, ['status' => 'waiting', 'waitlist_position' => 1]);

        (new LeaveTrainingPackAction)($enrolled, $pack);

        expect($waiter->fresh()->trainingPacks()->where('training_pack_id', $pack->id)->first()->pivot->status)
            ->toBe('offered');
    })->group('training', 'enrollment', 'waitlist', 'prorata');

    test('a departure refunds the unconsumed share only', function (): void {
        $this->travelTo('2027-01-15');

        $subscription = recreativeSubscription();
        $pack = referencePack();

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);
        (new CalculatePriceAction)($subscription);

        expect($subscription->fresh()->amount_due)->toBe(270.0);

        $subscription->payments()->create([
            'reference' => 'TEST-PAID',
            'amount_due' => 270,
            'amount_paid' => 270,
            'status' => 'paid',
        ]);

        $refundable = (new LeaveTrainingPackAction)($subscription, $pack);

        // Consommé : octobre → janvier = 4 mois sur 7 → 120 €. Le club garde
        // ces 120 €, le membre récupère les 90 € restants.
        expect($subscription->fresh()->amount_due)->toBe(60.0 + 120.0)
            ->and($refundable)->toBe(90.0);
    })->group('training', 'enrollment', 'refund', 'prorata');

    test('leaving twice never refunds the same euros twice', function (): void {
        $this->travelTo('2027-01-15');

        $subscription = recreativeSubscription();
        $pack = referencePack();

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);
        (new CalculatePriceAction)($subscription);

        $subscription->payments()->create([
            'reference' => 'TEST-PAID',
            'amount_due' => 270,
            'amount_paid' => 270,
            'status' => 'paid',
        ]);

        $first = (new LeaveTrainingPackAction)($subscription, $pack);
        $second = (new LeaveTrainingPackAction)($subscription, $pack);

        expect($first)->toBe(90.0)
            ->and($second)->toBe(0.0);
    })->group('training', 'enrollment', 'refund', 'prorata');

});

describe('Entrée en cours de pack', function (): void {

    test('enrolling after the pack started dates the line, before it does not', function (): void {
        $this->travelTo('2027-01-12');

        $started = recreativeSubscription();
        $notStarted = recreativeSubscription();

        $runningPack = referencePack();
        $futurePack = referencePack(['pack_start_date' => '2027-06-01', 'pack_end_date' => '2027-08-31']);

        (new EnrollInTrainingPackAction)($started, $runningPack);
        (new EnrollInTrainingPackAction)($notStarted, $futurePack);

        expect(DB::table('subscription_training_pack')->where('subscription_id', $started->id)->value('starts_on'))
            ->toBe('2027-01-12')
            ->and(DB::table('subscription_training_pack')->where('subscription_id', $notStarted->id)->value('starts_on'))
            ->toBeNull();
    })->group('training', 'enrollment', 'prorata');

    test('approval bills the request date, not the validation date', function (): void {
        $this->travelTo('2027-01-12');

        $subscription = recreativeSubscription();
        $pack = referencePack();

        (new EnrollInTrainingPackAction)($subscription, $pack);

        // Le club valide trois semaines plus tard : le membre ne gagne pas un mois.
        $this->travelTo('2027-02-05');
        (new ApproveTrainingPacksAction)($subscription, [$pack->id]);

        expect($subscription->fresh()->amount_due)->toBe(60.0 + 120.0);
    })->group('training', 'enrollment', 'prorata');

    test('a waitlisted line carries no start date until the spot is confirmed', function (): void {
        $this->travelTo('2026-12-05');

        $pack = referencePack(['max_participants' => 1]);

        $holder = recreativeSubscription();
        $holder->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $waiter = Subscription::factory()->for($holder->season, 'season')->create(['is_competitive' => false]);
        (new EnrollInTrainingPackAction)($waiter, $pack);

        expect(DB::table('subscription_training_pack')->where('subscription_id', $waiter->id)->value('status'))
            ->toBe('waiting')
            ->and(DB::table('subscription_training_pack')->where('subscription_id', $waiter->id)->value('starts_on'))
            ->toBeNull();
    })->group('training', 'enrollment', 'waitlist', 'prorata');

});

describe('Réconciliation manuelle', function (): void {

    test('editing the dates recalculates the price', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack();

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);
        (new CalculatePriceAction)($subscription);

        expect($subscription->fresh()->amount_due)->toBe(270.0);

        (new ReconcileTrainingPackAction)($subscription, $pack, '2027-01-12', null);

        expect($subscription->fresh()->amount_due)->toBe(60.0 + 120.0);
    })->group('training', 'reconciliation', 'prorata');

    test('a forced amount with a reason wins over the calculation and is audited', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack();

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);
        (new CalculatePriceAction)($subscription);

        (new ReconcileTrainingPackAction)($subscription, $pack, '2027-01-12', null, 75.5, 'Arrangement avec le coach');

        expect($subscription->fresh()->amount_due)->toBe(60.0 + 75.5);

        $row = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->where('training_pack_id', $pack->id)
            ->first();

        expect($row->override_amount)->toBe(7550)
            ->and($row->override_reason)->toBe('Arrangement avec le coach');

        $activity = Activity::query()->where('event', 'training_pack_reconciled')->latest('id')->first();

        expect($activity)->not->toBeNull()
            ->and($activity->subject_id)->toBe($subscription->id)
            ->and($activity->properties['override_amount'])->toBe(75.5)
            ->and($activity->properties['override_reason'])->toBe('Arrangement avec le coach')
            ->and((float) $activity->properties['amount_due_before'])->toBe(270.0)
            ->and((float) $activity->properties['amount_due_after'])->toBe(135.5);
    })->group('training', 'reconciliation', 'audit');

    test('a forced amount without a reason is refused', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack();

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);
        (new CalculatePriceAction)($subscription);

        expect(fn (): Subscription => (new ReconcileTrainingPackAction)($subscription, $pack, null, null, 75.5, '   '))
            ->toThrow(DomainException::class);

        expect($subscription->fresh()->amount_due)->toBe(270.0)
            ->and(DB::table('subscription_training_pack')->value('override_amount'))->toBeNull();
    })->group('training', 'reconciliation');

    test('an end date before the start date is refused', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack();

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        expect(fn (): Subscription => (new ReconcileTrainingPackAction)($subscription, $pack, '2027-02-01', '2027-01-01'))
            ->toThrow(DomainException::class);
    })->group('training', 'reconciliation');

    test('clearing the forced amount gives the calculation back the last word', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack();

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        (new ReconcileTrainingPackAction)($subscription, $pack, null, null, 10.0, 'Geste commercial');
        expect($subscription->fresh()->amount_due)->toBe(70.0);

        (new ReconcileTrainingPackAction)($subscription, $pack, null, null, null, null);

        expect($subscription->fresh()->amount_due)->toBe(270.0)
            ->and(DB::table('subscription_training_pack')->value('override_reason'))->toBeNull();
    })->group('training', 'reconciliation');

});

describe('Écran trésorerie — réconciliation', function (): void {

    beforeEach(function (): void {
        Club::factory()->ownClub()->create();
        actingAs(User::factory()->isAdmin()->create());
    });

    test('the modal opens prefilled with the stored period and forced amount', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack();

        $subscription->trainingPacks()->attach($pack->id, [
            'status' => 'enrolled',
            'starts_on' => '2027-01-12',
            'override_amount' => 9900,
            'override_reason' => 'Accord commission',
        ]);

        Livewire::test(PRORATA_REGISTRATIONS_COMPONENT)
            ->call('openReconcileModal', $subscription->id, $pack->id)
            ->assertSet('reconcileModal', true)
            ->assertSet('reconcileStartsOn', '2027-01-12')
            ->assertSet('reconcileEndsOn', null)
            ->assertSet('reconcileOverrideAmount', '99.00')
            ->assertSet('reconcileOverrideReason', 'Accord commission');
    });

    test('saving new dates recalculates the subscription total', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack();

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);
        (new CalculatePriceAction)($subscription);

        Livewire::test(PRORATA_REGISTRATIONS_COMPONENT)
            ->call('openReconcileModal', $subscription->id, $pack->id)
            ->set('reconcileStartsOn', '2027-01-12')
            ->call('saveReconciliation')
            ->assertSet('reconcileModal', false);

        expect($subscription->fresh()->amount_due)->toBe(180.0);
    });

    test('forcing an amount without a reason keeps the modal open and changes nothing', function (): void {
        $subscription = recreativeSubscription();
        $pack = referencePack();

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);
        (new CalculatePriceAction)($subscription);

        Livewire::test(PRORATA_REGISTRATIONS_COMPONENT)
            ->call('openReconcileModal', $subscription->id, $pack->id)
            ->set('reconcileOverrideAmount', '42')
            ->set('reconcileOverrideReason', '')
            ->call('saveReconciliation')
            ->assertSet('reconcileModal', true);

        expect($subscription->fresh()->amount_due)->toBe(270.0);
    });

    test('the cancel modal suggests a refund net of the months already attended', function (): void {
        $this->travelTo('2027-01-15');

        $subscription = recreativeSubscription();
        $subscription->update(['status' => 'paid']);
        $pack = referencePack();

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);
        (new CalculatePriceAction)($subscription);

        $subscription->payments()->create([
            'reference' => 'TEST-PAID',
            'amount_due' => 270,
            'amount_paid' => 270,
            'status' => 'paid',
        ]);

        // 270 versés − 120 d'entraînement consommé (4 mois sur 7) = 150.
        Livewire::test(PRORATA_REGISTRATIONS_COMPONENT)
            ->call('openCancelModal', $subscription->id)
            ->assertSet('cancelRefundAmount', 150.0);
    });

})->group('club-admin', 'registrations', 'reconciliation');
