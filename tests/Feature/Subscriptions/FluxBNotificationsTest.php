<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\LeaveTrainingPackAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackCancelledNotification;
use App\Domains\Trainings\Notifications\TrainingPackRequestedNotification;
use App\Domains\Trainings\Notifications\TrainingWaitlistJoinedNotification;
use Illuminate\Support\Facades\Notification;

// ─── TrainingPackRequestedNotification (Flux B) ────────────────────────────────

describe('TrainingPackRequestedNotification', function (): void {

    test('notification is sent via mail and database channels', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'paid']);
        $pack = TrainingPack::factory()->create(['name' => 'Pack Lundi']);
        $subscription->load('season');

        $notification = new TrainingPackRequestedNotification($pack, $subscription);

        expect($notification->via($subscription->user))->toBe(['mail', 'database']);
    })->group('notifications', 'flux-b');

    test('mail subject contains the pack name', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'paid']);
        $pack = TrainingPack::factory()->create(['name' => 'Pack Mardi']);
        $subscription->load(['user', 'season']);

        $mail = (new TrainingPackRequestedNotification($pack, $subscription))->toMail($subscription->user);

        expect($mail->subject)->toContain('Pack Mardi');
    })->group('notifications', 'flux-b');

    test('mail body contains pack name and season name', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'paid']);
        $pack = TrainingPack::factory()->create(['name' => 'Pack Vendredi']);
        $subscription->load(['user', 'season']);

        $mail = (new TrainingPackRequestedNotification($pack, $subscription))->toMail($subscription->user);

        $lines = implode(' ', $mail->introLines);
        expect($lines)->toContain('Pack Vendredi')
            ->and($lines)->toContain($subscription->season->name);
    })->group('notifications', 'flux-b');

    test('notification is sent when a paid member enrolls in a training pack (Flux B)', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create(['status' => 'paid']);
        $pack = TrainingPack::factory()->create(['max_participants' => 10, 'price' => 90]);

        (new EnrollInTrainingPackAction)($subscription, $pack);

        Notification::assertSentTo($subscription->user, TrainingPackRequestedNotification::class);
    })->group('notifications', 'flux-b', 'enrollment');

    test('notification is NOT sent when a pending member enrolls (Flux A — covered by subscription created notification)', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create(['status' => 'pending']);
        $pack = TrainingPack::factory()->create(['max_participants' => 10, 'price' => 90]);

        (new EnrollInTrainingPackAction)($subscription, $pack);

        Notification::assertNotSentTo($subscription->user, TrainingPackRequestedNotification::class);
    })->group('notifications', 'flux-b', 'enrollment');

    test('notification is NOT sent when a confirmed member enrolls (Flux A — not yet paid)', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create(['status' => 'confirmed']);
        $pack = TrainingPack::factory()->create(['max_participants' => 10, 'price' => 90]);

        (new EnrollInTrainingPackAction)($subscription, $pack);

        Notification::assertNotSentTo($subscription->user, TrainingPackRequestedNotification::class);
    })->group('notifications', 'flux-b', 'enrollment');

    test('waitlisted paid member receives waitlist notification, not TrainingPackRequested', function (): void {
        Notification::fake();

        $season = Season::factory()->create(['is_active' => true]);
        $pack = TrainingPack::factory()->create(['max_participants' => 1, 'price' => 90]);

        // Fill the spot — use explicit separate users so factory doesn't reuse the same user
        $user1 = User::factory()->create();
        $other = Subscription::factory()->for($season, 'season')->create(['user_id' => $user1->id, 'status' => 'paid']);
        (new EnrollInTrainingPackAction)($other, $pack);

        // Second paid member (different user) goes to waitlist
        $user2 = User::factory()->create();
        $subscription = Subscription::factory()->for($season, 'season')->create(['user_id' => $user2->id, 'status' => 'paid']);
        (new EnrollInTrainingPackAction)($subscription, $pack);

        Notification::assertSentTo($subscription->user, TrainingWaitlistJoinedNotification::class);
        Notification::assertNotSentTo($subscription->user, TrainingPackRequestedNotification::class);
    })->group('notifications', 'flux-b', 'enrollment', 'waitlist');

})->group('notifications');

// ─── TrainingPackCancelledNotification (Flux B) ────────────────────────────────

describe('TrainingPackCancelledNotification', function (): void {

    test('notification is sent via mail and database channels', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'paid']);
        $pack = TrainingPack::factory()->create(['name' => 'Pack Lundi']);
        $subscription->load('season');

        $notification = new TrainingPackCancelledNotification($pack, $subscription);

        expect($notification->via($subscription->user))->toBe(['mail', 'database']);
    })->group('notifications', 'flux-b');

    test('mail subject contains the pack name', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'paid']);
        $pack = TrainingPack::factory()->create(['name' => 'Pack Jeudi']);
        $subscription->load(['user', 'season']);

        $mail = (new TrainingPackCancelledNotification($pack, $subscription))->toMail($subscription->user);

        expect($mail->subject)->toContain('Pack Jeudi');
    })->group('notifications', 'flux-b');

    test('mail body contains pack name and season name', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'paid']);
        $pack = TrainingPack::factory()->create(['name' => 'Pack Samedi']);
        $subscription->load(['user', 'season']);

        $mail = (new TrainingPackCancelledNotification($pack, $subscription))->toMail($subscription->user);

        $lines = implode(' ', $mail->introLines);
        expect($lines)->toContain('Pack Samedi')
            ->and($lines)->toContain($subscription->season->name);
    })->group('notifications', 'flux-b');

    test('notification is sent when a paid member cancels a pending training pack', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create(['status' => 'paid']);
        $pack = TrainingPack::factory()->create(['max_participants' => 10, 'price' => 90]);

        // Enroll (becomes pending), then leave
        (new EnrollInTrainingPackAction)($subscription, $pack);
        (new LeaveTrainingPackAction)($subscription, $pack);

        Notification::assertSentTo($subscription->user, TrainingPackCancelledNotification::class);
    })->group('notifications', 'flux-b', 'enrollment');

    test('notification is NOT sent when leaving a waitlisted slot', function (): void {
        Notification::fake();

        $pack = TrainingPack::factory()->create(['max_participants' => 1, 'price' => 90]);

        // Fill the spot
        $occupier = Subscription::factory()->create(['status' => 'pending']);
        $occupier->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        // Second user goes on waitlist
        $subscription = Subscription::factory()->for($occupier->season, 'season')->create(['status' => 'paid']);
        (new EnrollInTrainingPackAction)($subscription, $pack); // → waiting

        // Leave the waitlist — should NOT send TrainingPackCancelledNotification
        (new LeaveTrainingPackAction)($subscription, $pack);

        Notification::assertNotSentTo($subscription->user, TrainingPackCancelledNotification::class);
    })->group('notifications', 'flux-b', 'enrollment', 'waitlist');

    test('notification is NOT sent when leaving an enrolled (admin-validated) pack', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create(['status' => 'paid']);
        $pack = TrainingPack::factory()->create(['max_participants' => 10, 'price' => 90]);

        // Attach directly as enrolled (admin already validated)
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        (new LeaveTrainingPackAction)($subscription, $pack);

        Notification::assertNotSentTo($subscription->user, TrainingPackCancelledNotification::class);
    })->group('notifications', 'flux-b', 'enrollment');

})->group('notifications');
