<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\CancelSubscriptionWithRefundAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use App\Domains\Subscriptions\Notifications\SubscriptionCancelledNotification;
use App\Domains\Subscriptions\Notifications\SubscriptionRefundRequestedNotification;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackCancelledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'registrations', 'cancellation');

const CANCEL_REGISTRATIONS_COMPONENT = 'pages::club-admin.users.registrations';

beforeEach(function (): void {
    Club::factory()->ownClub()->create();
    $this->season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);
});

describe('CancelSubscriptionWithRefundAction', function (): void {

    test('cancelling an unpaid confirmed subscription sets status cancelled without refund payment', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'confirmed',
            'amount_due' => 125,
        ]);

        $refundPayment = (new CancelSubscriptionWithRefundAction)($subscription);

        expect($refundPayment)->toBeNull()
            ->and($subscription->fresh()->status)->toBe('cancelled')
            ->and($subscription->payments()->where('status', 'to_refund')->exists())->toBeFalse();

        Notification::assertSentTo($subscription->user, SubscriptionCancelledNotification::class);
    });

    test('outstanding pending payments are marked cancelled', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'confirmed',
            'amount_due' => 125,
        ]);
        $payment = $subscription->payments()->create([
            'reference' => 'REF-TEST-001',
            'amount_due' => 125,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        (new CancelSubscriptionWithRefundAction)($subscription);

        expect($payment->fresh()->status)->toBe('cancelled');
    });

    test('cancelling a paid subscription with a refund sets status refunded and creates a to_refund payment', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'paid',
            'amount_due' => 125,
        ]);
        $subscription->payments()->create([
            'reference' => 'REF-TEST-002',
            'amount_due' => 125,
            'amount_paid' => 125,
            'status' => 'paid',
        ]);

        $refundPayment = (new CancelSubscriptionWithRefundAction)($subscription, 125.0);

        expect($subscription->fresh()->status)->toBe('refunded')
            ->and($refundPayment)->not->toBeNull()
            ->and($refundPayment->status)->toBe('to_refund')
            ->and($refundPayment->payment_method)->toBe('refund')
            ->and($refundPayment->amount_due)->toBe(125.0)
            ->and($refundPayment->amount_paid)->toBe(125.0);

        Notification::assertSentTo($subscription->user, SubscriptionCancelledNotification::class);
    });

    test('treasurer and secretary are notified when a refund is requested', function (): void {
        Notification::fake();

        $treasurer = User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create(['committee_role' => CommitteeRolesEnum::TREASURER->value]);
        $secretary = User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create(['committee_role' => CommitteeRolesEnum::SECRETARY->value]);

        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'paid',
            'amount_due' => 125,
        ]);
        $subscription->payments()->create([
            'reference' => 'REF-TEST-003',
            'amount_due' => 125,
            'amount_paid' => 125,
            'status' => 'paid',
        ]);

        (new CancelSubscriptionWithRefundAction)($subscription, 125.0);

        Notification::assertSentTo($treasurer, SubscriptionRefundRequestedNotification::class);
        Notification::assertSentTo($secretary, SubscriptionRefundRequestedNotification::class);
    });

    test('a partially paid confirmed subscription can be cancelled with a partial refund', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'confirmed',
            'amount_due' => 125,
        ]);
        $subscription->payments()->create([
            'reference' => 'REF-TEST-004',
            'amount_due' => 125,
            'amount_paid' => 60,
            'status' => 'paid',
        ]);

        $refundPayment = (new CancelSubscriptionWithRefundAction)($subscription, 40.0);

        expect($subscription->fresh()->status)->toBe('refunded')
            ->and($refundPayment->amount_due)->toBe(40.0);
    });

    test('a refund amount above the total paid is rejected', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'paid',
            'amount_due' => 125,
        ]);
        $subscription->payments()->create([
            'reference' => 'REF-TEST-005',
            'amount_due' => 125,
            'amount_paid' => 125,
            'status' => 'paid',
        ]);

        (new CancelSubscriptionWithRefundAction)($subscription, 200.0);
    })->throws(InvalidArgumentException::class);

    test('training packs are detached and the waitlist is promoted when a spot is freed', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'confirmed',
            'amount_due' => 215,
        ]);
        $pack = TrainingPack::factory()->create(['price' => 90, 'max_participants' => 1]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $waitingSubscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'paid',
        ]);
        $waitingSubscription->trainingPacks()->attach($pack->id, ['status' => 'waiting', 'waitlist_position' => 1]);

        (new CancelSubscriptionWithRefundAction)($subscription);

        $promotedPivot = $waitingSubscription->trainingPacks()->where('training_pack_id', $pack->id)->first();

        expect($subscription->fresh()->trainingPacks)->toBeEmpty()
            ->and($promotedPivot->pivot->status)->toBe('offered');
    });

    test('no per-pack cancellation email is sent on top of the global cancellation email', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'confirmed',
            'amount_due' => 125,
        ]);
        $pack = TrainingPack::factory()->create(['price' => 90]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'pending']);

        (new CancelSubscriptionWithRefundAction)($subscription);

        Notification::assertNotSentTo($subscription->user, TrainingPackCancelledNotification::class);
        Notification::assertSentTo($subscription->user, SubscriptionCancelledNotification::class);
    });

    test('the treasurer refund email renders with the amount and a link to the member profile', function (): void {
        $treasurer = User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create(['committee_role' => CommitteeRolesEnum::TREASURER->value]);

        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'paid',
            'amount_due' => 125,
        ]);
        $payment = $subscription->payments()->create([
            'reference' => 'REF-TEST-010',
            'amount_due' => 125,
            'amount_paid' => 125,
            'status' => 'to_refund',
            'payment_method' => 'refund',
        ]);

        $mail = (new SubscriptionRefundRequestedNotification($payment, $subscription))->toMail($treasurer);
        $html = $mail->render()->__toString();

        expect($html)->toContain('125')
            ->and($html)->toContain(route('admin.users.edit', $subscription->user->id));
    });

    test('the optional message is embedded in the member email', function (): void {
        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'confirmed',
            'amount_due' => 125,
        ]);

        (new CancelSubscriptionWithRefundAction)($subscription, 0.0, 'À bientôt au club !');

        $notification = new SubscriptionCancelledNotification($subscription->fresh(), 0.0, 'À bientôt au club !');
        $mail = $notification->toMail($subscription->user);

        expect($mail->render()->__toString())->toContain('À bientôt au club !');
    });

});

describe('Subscription state machine — refund from confirmed', function (): void {

    test('a confirmed subscription with money collected can transition to refunded', function (): void {
        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'confirmed',
        ]);
        $subscription->payments()->create([
            'reference' => 'REF-TEST-006',
            'amount_due' => 125,
            'amount_paid' => 60,
            'status' => 'paid',
        ]);

        $subscription->refund();

        expect($subscription->fresh()->status)->toBe('refunded');
    });

    test('a confirmed subscription without any payment cannot be refunded', function (): void {
        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'confirmed',
        ]);

        $subscription->refund();
    })->throws(LogicException::class);

});

describe('Registrations page — cancel flow', function (): void {

    beforeEach(function (): void {
        actingAs(User::factory()->isAdmin()->create());
    });

    test('openCancelModal prefills the refund amount with the total paid', function (): void {
        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'paid',
            'amount_due' => 125,
        ]);
        $subscription->payments()->create([
            'reference' => 'REF-TEST-007',
            'amount_due' => 125,
            'amount_paid' => 125,
            'status' => 'paid',
        ]);

        Livewire::test(CANCEL_REGISTRATIONS_COMPONENT)
            ->call('openCancelModal', $subscription->id)
            ->assertSet('cancelModal', true)
            ->assertSet('cancelSubscriptionId', $subscription->id)
            ->assertSet('cancelRefundAmount', 125.0);
    });

    test('openCancelModal ignores pending and cancelled subscriptions', function (): void {
        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'pending',
        ]);

        Livewire::test(CANCEL_REGISTRATIONS_COMPONENT)
            ->call('openCancelModal', $subscription->id)
            ->assertSet('cancelModal', false);
    });

    test('confirmCancelSubscription cancels an unpaid confirmed subscription', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'confirmed',
            'amount_due' => 125,
        ]);

        Livewire::test(CANCEL_REGISTRATIONS_COMPONENT)
            ->call('openCancelModal', $subscription->id)
            ->call('confirmCancelSubscription')
            ->assertSet('cancelModal', false);

        expect($subscription->fresh()->status)->toBe('cancelled');
    });

    test('confirmCancelSubscription refunds a paid subscription with the chosen amount', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'paid',
            'amount_due' => 125,
        ]);
        $subscription->payments()->create([
            'reference' => 'REF-TEST-008',
            'amount_due' => 125,
            'amount_paid' => 125,
            'status' => 'paid',
        ]);

        Livewire::test(CANCEL_REGISTRATIONS_COMPONENT)
            ->call('openCancelModal', $subscription->id)
            ->set('cancelRefundAmount', 100.0)
            ->call('confirmCancelSubscription');

        $refund = $subscription->payments()->where('status', 'to_refund')->first();

        expect($subscription->fresh()->status)->toBe('refunded')
            ->and($refund)->not->toBeNull()
            ->and($refund->amount_due)->toBe(100.0);
    });

    test('confirmCancelSubscription rejects a refund amount above the total paid', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'paid',
            'amount_due' => 125,
        ]);
        $subscription->payments()->create([
            'reference' => 'REF-TEST-009',
            'amount_due' => 125,
            'amount_paid' => 125,
            'status' => 'paid',
        ]);

        Livewire::test(CANCEL_REGISTRATIONS_COMPONENT)
            ->call('openCancelModal', $subscription->id)
            ->set('cancelRefundAmount', 999.0)
            ->call('confirmCancelSubscription');

        expect($subscription->fresh()->status)->toBe('paid')
            ->and($subscription->payments()->where('status', 'to_refund')->exists())->toBeFalse();
    });

    test('confirmRefund on an enrolled pack creates a to_refund payment in the treasury workflow', function (): void {
        Notification::fake();

        $treasurer = User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create(['committee_role' => CommitteeRolesEnum::TREASURER->value]);

        $subscription = Subscription::factory()->create([
            'season_id' => $this->season->id,
            'status' => 'paid',
            'is_competitive' => false,
            'amount_due' => 150,
        ]);
        $pack = TrainingPack::factory()->create(['price' => 90]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        // 60 € (récréatif) + 90 € de pack, réellement encaissés : le
        // remboursement se calcule sur les paiements, pas sur le statut.
        $subscription->payments()->create([
            'reference' => 'TEST-PAID',
            'amount_due' => 150,
            'amount_paid' => 150,
            'status' => 'paid',
        ]);

        Livewire::test(CANCEL_REGISTRATIONS_COMPONENT)
            ->call('openRefundModal', $subscription->id, $pack->id)
            ->call('confirmRefund');

        $refund = $subscription->payments()->where('status', 'to_refund')->first();

        expect($subscription->trainingPacks()->where('training_pack_id', $pack->id)->exists())->toBeFalse()
            ->and($refund)->not->toBeNull()
            ->and($refund->payment_method)->toBe('refund')
            ->and($refund->amount_due)->toBe(90.0);

        Notification::assertSentTo($treasurer, SubscriptionRefundRequestedNotification::class);
    });

});
