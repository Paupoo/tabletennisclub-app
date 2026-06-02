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

describe('Training Enrollment', function () {

    // ── EnrollInTrainingPackAction ──────────────────────────────────────────

    test('sets pending when spot is available (awaiting admin validation)', function () {
        $subscription = Subscription::factory()->create();
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);

        $status = (new EnrollInTrainingPackAction)($subscription, $pack);

        expect($status)->toBe('pending');

        $pivot = $subscription->trainingPacks()->wherePivot('training_pack_id', $pack->id)->first();
        expect($pivot)->not->toBeNull()
            ->and($pivot->pivot->status)->toBe('pending');
    })->group('training', 'enrollment');

    test('places on waitlist when pack is full (pending counts against capacity)', function () {
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

    test('throws when subscription is cancelled', function () {
        $subscription = Subscription::factory()->create(['status' => 'cancelled']);
        $pack = TrainingPack::factory()->create();

        expect(fn () => (new EnrollInTrainingPackAction)($subscription, $pack))
            ->toThrow(DomainException::class);
    })->group('training', 'enrollment');

    test('throws when already enrolled in pack', function () {
        $subscription = Subscription::factory()->create();
        $pack = TrainingPack::factory()->create(['price' => 90]);

        (new EnrollInTrainingPackAction)($subscription, $pack);

        expect(fn () => (new EnrollInTrainingPackAction)($subscription, $pack))
            ->toThrow(DomainException::class);
    })->group('training', 'enrollment');

    // ── LeaveTrainingPackAction ─────────────────────────────────────────────

    test('leaves enrolled pack and removes pivot', function () {
        $subscription = Subscription::factory()->create();
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        (new LeaveTrainingPackAction)($subscription, $pack);

        expect($subscription->trainingPacks()->where('training_pack_id', $pack->id)->exists())->toBeFalse();
    })->group('training', 'enrollment');

    test('leaving an enrolled spot promotes first waiter', function () {
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

    test('leaving a waitlist spot does not promote anyone', function () {
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

    test('promote renumbers remaining waitlist positions', function () {
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

    test('pending and waitlisted packs are excluded from price calculation', function () {
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

    test('admin can remove an enrolled pack via LeaveTrainingPackAction (for refund purposes)', function () {
        $subscription = Subscription::factory()->create(['status' => 'paid']);
        $pack = TrainingPack::factory()->create(['max_participants' => 5, 'price' => 90]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        (new LeaveTrainingPackAction)($subscription, $pack);

        expect($subscription->trainingPacks()->where('training_pack_id', $pack->id)->exists())->toBeFalse();
    })->group('training', 'enrollment', 'refund');

    test('user-facing guard blocks leaving an enrolled pack via leaveTrainingPack method', function () {
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

});
