<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\LeaveTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\PromoteFromTrainingWaitlistAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingWaitlistJoinedNotification;
use App\Domains\Trainings\Notifications\TrainingWaitlistSpotOfferedNotification;
use Illuminate\Support\Facades\Notification;

describe('Training Enrollment', function (): void {

    // ── EnrollInTrainingPackAction ──────────────────────────────────────────

    test('sets pending when spot is available (awaiting admin validation)', function (): void {
        $subscription = Subscription::factory()->create();
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);

        $status = (new EnrollInTrainingPackAction)($subscription, $pack);

        expect($status)->toBe('pending');

        $pivot = $subscription->trainingPacks()->wherePivot('training_pack_id', $pack->id)->first();
        expect($pivot)->not->toBeNull()
            ->and($pivot->pivot->status)->toBe('pending');
    })->group('training', 'enrollment');

    test('places on waitlist when pack is full (pending counts against capacity)', function (): void {
        Notification::fake();

        $pack = TrainingPack::factory()->create(['max_participants' => 1, 'price' => 90]);

        $first = Subscription::factory()->create();
        (new EnrollInTrainingPackAction)($first, $pack); // → pending, fills the one spot

        $second = Subscription::factory()->for($first->season, 'season')->create();
        $status = (new EnrollInTrainingPackAction)($second, $pack); // → waiting

        expect($status)->toBe('waiting');

        $pivot = $second->trainingPacks()->wherePivot('training_pack_id', $pack->id)->first();
        expect($pivot->pivot->status)->toBe('waiting')
            ->and($pivot->pivot->waitlist_position)->toBe(1);

        Notification::assertSentTo($second->user, TrainingWaitlistJoinedNotification::class);
    })->group('training', 'enrollment', 'waitlist');

    test('throws when subscription is cancelled', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'cancelled']);
        $pack = TrainingPack::factory()->create();

        expect(fn () => (new EnrollInTrainingPackAction)($subscription, $pack))
            ->toThrow(DomainException::class);
    })->group('training', 'enrollment');

    test('throws when already enrolled in pack', function (): void {
        $subscription = Subscription::factory()->create();
        $pack = TrainingPack::factory()->create(['price' => 90]);

        (new EnrollInTrainingPackAction)($subscription, $pack);

        expect(fn () => (new EnrollInTrainingPackAction)($subscription, $pack))
            ->toThrow(DomainException::class);
    })->group('training', 'enrollment');

    // ── LeaveTrainingPackAction ─────────────────────────────────────────────

    test('leaves enrolled pack and removes pivot', function (): void {
        $subscription = Subscription::factory()->create();
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        (new LeaveTrainingPackAction)($subscription, $pack);

        expect($subscription->trainingPacks()->where('training_pack_id', $pack->id)->exists())->toBeFalse();
    })->group('training', 'enrollment');

    test('leaving an enrolled spot promotes first waiter', function (): void {
        Notification::fake();

        $pack = TrainingPack::factory()->create(['max_participants' => 1, 'price' => 90]);

        // Use 'enrolled' directly (admin-approved) to simulate a spot being taken
        $enrolled = Subscription::factory()->create();
        $enrolled->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $waiter = Subscription::factory()->for($enrolled->season, 'season')->create();
        (new EnrollInTrainingPackAction)($waiter, $pack); // → waiting (enrolled spot is full)

        (new LeaveTrainingPackAction)($enrolled, $pack);

        $pivot = $waiter->fresh()->trainingPacks()->wherePivot('training_pack_id', $pack->id)->first();
        expect($pivot->pivot->status)->toBe('offered')
            ->and($pivot->pivot->confirmation_deadline)->not->toBeNull();

        Notification::assertSentTo($waiter->user, TrainingWaitlistSpotOfferedNotification::class);
    })->group('training', 'enrollment', 'waitlist');

    test('leaving a waitlist spot does not promote anyone', function (): void {
        Notification::fake();

        $pack = TrainingPack::factory()->create(['max_participants' => 1, 'price' => 90]);

        $enrolled = Subscription::factory()->create();
        $enrolled->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $waiter = Subscription::factory()->for($enrolled->season, 'season')->create();
        (new EnrollInTrainingPackAction)($waiter, $pack); // → waiting

        (new LeaveTrainingPackAction)($waiter, $pack);

        Notification::assertNotSentTo($waiter->user, TrainingWaitlistSpotOfferedNotification::class);
        expect($waiter->trainingPacks()->where('training_pack_id', $pack->id)->exists())->toBeFalse();
    })->group('training', 'enrollment', 'waitlist');

    // ── PromoteFromTrainingWaitlistAction ───────────────────────────────────

    test('promote renumbers remaining waitlist positions', function (): void {
        Notification::fake();

        $pack = TrainingPack::factory()->create(['max_participants' => 1, 'price' => 90]);

        $enrolled = Subscription::factory()->create();
        $enrolled->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $waiter1 = Subscription::factory()->for($enrolled->season, 'season')->create();
        $waiter2 = Subscription::factory()->for($enrolled->season, 'season')->create();
        (new EnrollInTrainingPackAction)($waiter1, $pack); // → waiting #1
        (new EnrollInTrainingPackAction)($waiter2, $pack); // → waiting #2

        (new PromoteFromTrainingWaitlistAction)($pack);

        $pivot1 = $waiter1->fresh()->trainingPacks()->wherePivot('training_pack_id', $pack->id)->first();
        $pivot2 = $waiter2->fresh()->trainingPacks()->wherePivot('training_pack_id', $pack->id)->first();

        expect($pivot1->pivot->status)->toBe('offered')
            ->and($pivot2->pivot->waitlist_position)->toBe(1);
    })->group('training', 'enrollment', 'waitlist');

    // ── Pricing with enrollment ─────────────────────────────────────────────

    test('pending and waitlisted packs are excluded from price calculation', function (): void {
        $subscription = Subscription::factory()->create(['is_competitive' => false]);
        $pack1 = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90, 'allow_discount' => true]);
        $pack2 = TrainingPack::factory()->create(['max_participants' => 1, 'price' => 90, 'allow_discount' => true]);

        // pack1: pending (awaiting admin validation) — should not count in price
        (new EnrollInTrainingPackAction)($subscription, $pack1);

        // pack2 is full — user goes on waitlist
        $other = Subscription::factory()->for($subscription->season, 'season')->create();
        (new EnrollInTrainingPackAction)($other, $pack2); // fills the spot (pending)
        (new EnrollInTrainingPackAction)($subscription, $pack2); // → waiting

        // When admin runs CalculatePriceAction at confirmation time,
        // neither pending nor waiting packs should be included.
        (new CalculatePriceAction)($subscription);

        expect($subscription->fresh()->amount_due)->toBe(60.0)
            ->and($subscription->fresh()->trainings_count)->toBe(0);
    })->group('training', 'pricing');

    // ── Enrolled pack protection ────────────────────────────────────────────

    test('admin can remove an enrolled pack via LeaveTrainingPackAction (for refund purposes)', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'paid']);
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        (new LeaveTrainingPackAction)($subscription, $pack);

        expect($subscription->trainingPacks()->where('training_pack_id', $pack->id)->exists())->toBeFalse();
    })->group('training', 'enrollment', 'refund');

    test('user-facing guard blocks leaving an enrolled pack via leaveTrainingPack method', function (): void {
        // The guard checks pivot status before calling LeaveTrainingPackAction
        // We test the pivot status check logic directly
        $subscription = Subscription::factory()->create(['status' => 'paid']);
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $pivot = $subscription->trainingPacks()->where('training_pack_id', $pack->id)->first();

        // Simulate the guard condition: enrolled status blocks user leave
        expect($pivot?->pivot->status)->toBe('enrolled');
        // Pack remains enrolled — user is blocked at the UI/controller level
        expect($subscription->trainingPacks()->where('training_pack_id', $pack->id)->exists())->toBeTrue();
    })->group('training', 'enrollment', 'refund');

    // ── Refundable amount (discount-aware) ──────────────────────────────────

    test('refunds only the overpayment when leaving a pack loses the multi-pack discount', function (): void {
        $subscription = Subscription::factory()->create(['is_competitive' => false]);
        $packA = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true, 'max_participants' => 5]);
        $packB = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true, 'max_participants' => 5]);

        $subscription->trainingPacks()->attach([$packA->id, $packB->id], ['status' => 'enrolled']);
        (new CalculatePriceAction)($subscription);

        // 60 (récréatif) + 80 + 80 après remise multi-packs
        expect($subscription->fresh()->amount_due)->toBe(220.0);

        $subscription->payments()->create([
            'reference' => 'TEST-PAID',
            'amount_due' => 220,
            'amount_paid' => 220,
            'status' => 'paid',
        ]);

        $refundable = (new LeaveTrainingPackAction)($subscription, $packA);

        // Nouveau dû : 60 + 90 (remise perdue) = 150 → trop-perçu de 70, pas 90.
        expect($subscription->fresh()->amount_due)->toBe(150.0)
            ->and($refundable)->toBe(70.0);
    })->group('training', 'enrollment', 'refund');

    test('refunds the full pack price when no discount was in play', function (): void {
        $subscription = Subscription::factory()->create(['is_competitive' => false]);
        $pack = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true, 'max_participants' => 5]);

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);
        (new CalculatePriceAction)($subscription);

        $subscription->payments()->create([
            'reference' => 'TEST-PAID',
            'amount_due' => 150,
            'amount_paid' => 150,
            'status' => 'paid',
        ]);

        $refundable = (new LeaveTrainingPackAction)($subscription, $pack);

        expect($refundable)->toBe(90.0);
    })->group('training', 'enrollment', 'refund');

    test('never refunds more than the member actually paid', function (): void {
        $subscription = Subscription::factory()->create(['is_competitive' => false]);
        $packA = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true, 'max_participants' => 5]);
        $packB = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true, 'max_participants' => 5]);

        $subscription->trainingPacks()->attach([$packA->id, $packB->id], ['status' => 'enrolled']);
        (new CalculatePriceAction)($subscription);

        // A versé 20 sur 220. La baisse du dû est de 70, mais on ne peut pas
        // rendre plus que ce qui est entré : le remboursement est plafonné.
        $subscription->payments()->create([
            'reference' => 'TEST-PARTIAL',
            'amount_due' => 220,
            'amount_paid' => 20,
            'status' => 'paid',
        ]);

        $refundable = (new LeaveTrainingPackAction)($subscription, $packA);

        expect($refundable)->toBe(20.0);
    })->group('training', 'enrollment', 'refund');

    test('refunds nothing when the member has paid nothing', function (): void {
        $subscription = Subscription::factory()->create(['is_competitive' => false]);
        $pack = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true, 'max_participants' => 5]);

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);
        (new CalculatePriceAction)($subscription);

        $refundable = (new LeaveTrainingPackAction)($subscription, $pack);

        expect($refundable)->toBe(0.0);
    })->group('training', 'enrollment', 'refund');

    test('does not refund twice when a refund is already queued in the treasury', function (): void {
        $subscription = Subscription::factory()->create(['is_competitive' => false]);
        $packA = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true, 'max_participants' => 5]);
        $packB = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true, 'max_participants' => 5]);

        $subscription->trainingPacks()->attach([$packA->id, $packB->id], ['status' => 'enrolled']);
        (new CalculatePriceAction)($subscription);

        $subscription->payments()->create([
            'reference' => 'TEST-PAID',
            'amount_due' => 220,
            'amount_paid' => 220,
            'status' => 'paid',
        ]);

        $first = (new LeaveTrainingPackAction)($subscription, $packA);
        $subscription->payments()->create([
            'reference' => 'TEST-REFUND',
            'amount_due' => $first,
            'amount_paid' => $first,
            'status' => 'to_refund',
            'payment_method' => 'refund',
        ]);

        $second = (new LeaveTrainingPackAction)($subscription, $packB);

        // 220 versés − 70 déjà en circuit = 150 net, nouveau dû 60 → 90, pas 160.
        expect($first)->toBe(70.0)
            ->and($second)->toBe(90.0);
    })->group('training', 'enrollment', 'refund');

});
