<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingCampAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Role;
use App\Domains\Trainings\Models\TrainingPack;
use App\Services\ClubAdmin\Dashboard\PendingTasks;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    Mail::fake();
    Notification::fake();
    Club::factory()->ownClub()->create();

    $this->season = makeActiveSeason();
    $this->manager = User::factory()->isCommitteeMember()->withRole(Role::TRAININGS, Role::MEMBERS)->create();
});

function campScreensCamp(array $overrides = []): TrainingPack
{
    return makeTrainingPack(test()->season, array_merge([
        'is_camp' => true,
        'allow_discount' => false,
        'price' => 80,
        'max_participants' => 10,
        'pack_start_date' => today()->addMonth()->startOfMonth()->toDateString(),
        'pack_end_date' => today()->addMonth()->startOfMonth()->addDays(4)->toDateString(),
    ], $overrides));
}

function campScreensSubscription(User $member): Subscription
{
    return Subscription::where('user_id', $member->id)->where('season_id', test()->season->id)->firstOrFail();
}

describe('the committee screen', function (): void {
    it('keeps a stage a stage once a member is on it', function (): void {
        $camp = campScreensCamp();
        (new EnrollInTrainingCampAction)(campScreensSubscription(activeMember($this->season)), $camp, byClub: true);

        Livewire::actingAs($this->manager)
            ->test('pages::club-events.trainings.index')
            ->call('openEdit', $camp->id)
            ->assertSet('formIsCamp', true)
            ->set('formIsCamp', false)
            ->call('save');

        expect($camp->refresh()->is_camp)->toBeTrue();
    });

    it('accepts a request from the stage roster', function (): void {
        $camp = campScreensCamp(['requires_approval' => true]);
        $member = activeMember($this->season);
        (new EnrollInTrainingCampAction)(campScreensSubscription($member), $camp);

        Livewire::actingAs($this->manager)
            ->test('pages::club-events.trainings.index')
            ->call('openPack', $camp->id)
            ->assertSee(__('Accept'))
            ->call('acceptCampRequest', $member->id);

        $line = SubscriptionTrainingPack::where('training_pack_id', $camp->id)->firstOrFail();

        expect($line->status)->toBe('enrolled')
            ->and($line->payments()->count())->toBe(1);
    });

    it('adds a member at their own price, and adjusts it later', function (): void {
        $camp = campScreensCamp();
        $member = activeMember($this->season);

        $screen = Livewire::actingAs($this->manager)
            ->test('pages::club-events.trainings.index')
            ->call('openPack', $camp->id)
            ->call('openAddMember')
            ->set('addMemberUserId', $member->id)
            ->set('addMemberPrice', '20')
            ->set('addMemberPriceReason', 'Un seul jour')
            ->call('addMemberToPack')
            ->assertHasNoErrors();

        $line = SubscriptionTrainingPack::where('training_pack_id', $camp->id)->firstOrFail();
        expect((float) $line->payments()->first()->amount_due)->toBe(20.0);

        $screen->call('openCampPrice', $member->id)
            ->assertSet('campPriceAmount', '20.00')
            ->set('campPriceAmount', '35')
            ->set('campPriceReason', 'Deux jours')
            ->call('saveCampPrice');

        expect((float) $line->payments()->first()->amount_due)->toBe(35.0);
    });

    it('counts the stage requests as a task for whoever validates packs', function (): void {
        $camp = campScreensCamp(['requires_approval' => true]);
        (new EnrollInTrainingCampAction)(campScreensSubscription(activeMember($this->season)), $camp);

        $this->actingAs($this->manager)->get('/');

        $task = app(PendingTasks::class)->for($this->manager)['camp_requests'] ?? null;

        expect($task?->count)->toBe(1)
            ->and($task?->route)->toContain('pack=' . $camp->id);
    });
    it('refunds a paid stage once, on its own line, when it was an encoding error', function (): void {
        $camp = campScreensCamp();
        $member = activeMember($this->season);
        $subscription = campScreensSubscription($member);
        (new EnrollInTrainingCampAction)($subscription, $camp);

        $claim = Payment::where('payable_type', SubscriptionTrainingPack::class)->firstOrFail();
        (new AllocateTransactionAction)->credit($claim, 80.0, 'cash');

        Livewire::actingAs($this->manager)
            ->test('admin.shared.training-pack-exit')
            ->call('open', $subscription->id, $camp->id)
            ->set('mode', 'error')
            ->assertSet('preview.refund', 80.0)
            ->call('confirm');

        expect(Payment::where('payment_method', 'refund')->count())->toBe(1)
            ->and(Payment::where('payment_method', 'refund')->first()->payable_type)->toBe(SubscriptionTrainingPack::class)
            ->and($subscription->payments()->count())->toBe(0);
    });
});

describe('the member screens', function (): void {
    it('lists the stage in « My registrations » and enrols the member', function (): void {
        $camp = campScreensCamp(['name' => 'Stage de Pâques']);
        $member = activeMember($this->season);

        Livewire::actingAs($member)
            ->test('pages::club-admin.users.user-space.event-subscription', ['user' => $member])
            ->assertSee('Stage de Pâques')
            ->call('enrolInCamp', $camp->id)
            ->assertHasNoErrors();

        $line = SubscriptionTrainingPack::where('training_pack_id', $camp->id)->firstOrFail();

        expect($line->status)->toBe('enrolled');

        Livewire::actingAs($member)
            ->test('pages::club-admin.users.user-space.event-subscription', ['user' => $member])
            ->assertSee(__('to pay: :amount €', ['amount' => '80,00']));
    });

    it('describes the stage like a training pack, without the billing jargon', function (): void {
        $coach = User::factory()->create(['first_name' => 'Amaury', 'last_name' => 'Ketele']);
        $camp = campScreensCamp([
            'name' => 'Stage de Pâques',
            'price' => 135,
            'trainer_id' => $coach->id,
            'days_of_week' => [1, 2, 3, 4, 5],
            'start_time' => '09:00:00',
            'duration_minutes' => 420,
        ]);
        $member = activeMember($this->season);

        Livewire::actingAs($member)
            ->test('pages::club-admin.users.user-space.event-subscription', ['user' => $member])
            ->assertSee($camp->scheduleLabel())
            ->assertSee($camp->room->name)
            ->assertSee($camp->level->label)
            ->assertSee('Amaury Ketele')
            ->assertSee('135,00 €')
            ->assertSee(trans_choice(':n spot left|:n spots left', 10, ['n' => 10]))
            ->assertDontSee(__('invoiced separately'));
    });

    it('does not offer the stage in « My season »', function (): void {
        campScreensCamp(['name' => 'Stage de Pâques']);
        makeTrainingPack($this->season, ['name' => 'Mardi Élite']);
        $member = activeMember($this->season);

        Livewire::actingAs($member)
            ->test('pages::club-admin.users.user-space.registration-management', ['user' => $member])
            ->assertSee('Mardi Élite')
            ->assertDontSee('Stage de Pâques');
    });

    it('opens the payment of a stage in « My payments »', function (): void {
        $camp = campScreensCamp(['name' => 'Stage de Pâques']);
        $member = activeMember($this->season);
        (new EnrollInTrainingCampAction)(campScreensSubscription($member), $camp);

        $payment = Payment::where('payable_type', SubscriptionTrainingPack::class)->firstOrFail();

        Livewire::actingAs($member)
            ->test('pages::club-admin.users.user-space.payments', ['user' => $member])
            ->assertSee('Stage de Pâques')
            ->call('openPaymentModal', $payment->id)
            ->assertSet('paymentModal', true);
    });
});

describe('the treasury', function (): void {
    it('lists a stage payment as its own kind of income', function (): void {
        $camp = campScreensCamp(['name' => 'Stage de Pâques']);
        $member = activeMember($this->season, ['first_name' => 'Zélie']);
        (new EnrollInTrainingCampAction)(campScreensSubscription($member), $camp);

        $treasurer = User::factory()->create();
        $treasurer->assignRole(Role::TREASURY->value);

        Livewire::actingAs($treasurer)
            ->test('pages::club-admin.treasury.payments')
            ->set('eventType', SubscriptionTrainingPack::class)
            ->assertSee('Stage de Pâques')
            ->set('eventType', '')
            ->set('search', 'Zélie')
            ->assertSee('Stage de Pâques');
    });
});
