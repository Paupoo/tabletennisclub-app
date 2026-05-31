<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Subscriptions\Notifications\SubscriptionRejectedNotification;
use App\Domains\Subscriptions\Notifications\TrainingPackRejectedNotification;
use App\Domains\Trainings\Models\TrainingPack;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Support\Facades\Notification;

// ─── SubscriptionRejectedNotification ─────────────────────────────────────────

describe('SubscriptionRejectedNotification', function () {

    test('notification is sent via mail channel only', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['name' => '2025-2026', 'is_active' => true]);
        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id]);
        $subscription->load('season');

        $notification = new SubscriptionRejectedNotification($subscription);

        expect($notification->via($user))->toBe(['mail', 'database']);
    })->group('notifications', 'rejection');

    test('mail subject contains the season name', function () {
        $user = User::factory()->create(['first_name' => 'Alice']);
        $season = Season::factory()->create(['name' => '2025-2026', 'is_active' => true]);
        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id]);
        $subscription->load('season');

        $mail = (new SubscriptionRejectedNotification($subscription))->toMail($user);

        expect($mail->subject)->toContain('2025-2026');
    })->group('notifications', 'rejection');

    test('mail greeting contains user first name and body contains season name', function () {
        $user = User::factory()->create(['first_name' => 'Marie']);
        $season = Season::factory()->create(['name' => '2025-2026', 'is_active' => true]);
        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id]);
        $subscription->load('season');

        $mail = (new SubscriptionRejectedNotification($subscription))->toMail($user);

        $lines = implode(' ', $mail->introLines);
        expect($mail->greeting)->toContain('Marie')
            ->and($lines)->toContain('2025-2026');
    })->group('notifications', 'rejection');

    test('template level adds level-mismatch reason line', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true]);
        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id]);
        $subscription->load('season');

        $mail = (new SubscriptionRejectedNotification($subscription, template: 'level'))->toMail($user);

        $lines = implode(' ', $mail->introLines);
        expect($lines)->toContain('niveau');
    })->group('notifications', 'rejection', 'templates');

    test('template full_teams adds team-capacity reason line', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true]);
        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id]);
        $subscription->load('season');

        $mail = (new SubscriptionRejectedNotification($subscription, template: 'full_teams'))->toMail($user);

        $lines = implode(' ', $mail->introLines);
        expect($lines)->toContain('complètes');
    })->group('notifications', 'rejection', 'templates');

    test('custom message is included in the mail', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true]);
        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id]);
        $subscription->load('season');

        $customMsg = 'Veuillez réessayer en janvier.';
        $mail = (new SubscriptionRejectedNotification($subscription, message: $customMsg))->toMail($user);

        $lines = implode(' ', $mail->introLines);
        expect($lines)->toContain($customMsg);
    })->group('notifications', 'rejection');

    test('no template produces no extra reason lines', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true]);
        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id]);
        $subscription->load('season');

        $mail = (new SubscriptionRejectedNotification($subscription))->toMail($user);

        $lines = implode(' ', $mail->introLines);
        expect($lines)->not->toContain('niveau')
            ->and($lines)->not->toContain('complètes');
    })->group('notifications', 'rejection');

    test('reject flow: notification sent and subscription becomes cancelled', function () {
        Notification::fake();

        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'status' => 'pending',
        ]);
        $subscription->load(['user', 'season']);

        $user->notify(new SubscriptionRejectedNotification(
            $subscription,
            'Pas de place.',
            'full_teams',
        ));
        $subscription->cancel();

        Notification::assertSentTo($user, SubscriptionRejectedNotification::class, function ($n) {
            return $n->template === 'full_teams' && $n->message === 'Pas de place.';
        });
        expect($subscription->fresh()->status)->toBe('cancelled');
    })->group('notifications', 'rejection');

})->group('notifications');

// ─── TrainingPackRejectedNotification ─────────────────────────────────────────

describe('TrainingPackRejectedNotification', function () {

    test('notification is sent via mail channel only', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true]);
        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id]);
        $pack = TrainingPack::factory()->create(['name' => 'Pack Lundi']);
        $subscription->load('season');

        $notification = new TrainingPackRejectedNotification($subscription, $pack);

        expect($notification->via($user))->toBe(['mail', 'database']);
    })->group('notifications', 'rejection');

    test('mail subject contains the season name', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['name' => '2025-2026', 'is_active' => true]);
        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id]);
        $pack = TrainingPack::factory()->create(['name' => 'Pack Mercredi']);
        $subscription->load('season');

        $mail = (new TrainingPackRejectedNotification($subscription, $pack))->toMail($user);

        expect($mail->subject)->toContain('2025-2026');
    })->group('notifications', 'rejection');

    test('mail body contains pack name and season name', function () {
        $user = User::factory()->create(['first_name' => 'Bob']);
        $season = Season::factory()->create(['name' => '2025-2026', 'is_active' => true]);
        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id]);
        $pack = TrainingPack::factory()->create(['name' => 'Pack Vendredi']);
        $subscription->load('season');

        $mail = (new TrainingPackRejectedNotification($subscription, $pack))->toMail($user);

        $lines = implode(' ', $mail->introLines);
        expect($lines)->toContain('Pack Vendredi')
            ->and($lines)->toContain('2025-2026');
    })->group('notifications', 'rejection');

    test('template level adds level-mismatch reason', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true]);
        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id]);
        $pack = TrainingPack::factory()->create();
        $subscription->load('season');

        $mail = (new TrainingPackRejectedNotification($subscription, $pack, template: 'level'))->toMail($user);

        $lines = implode(' ', $mail->introLines);
        expect($lines)->toContain('niveau');
    })->group('notifications', 'rejection', 'templates');

    test('template full_teams adds team-capacity reason', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true]);
        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id]);
        $pack = TrainingPack::factory()->create();
        $subscription->load('season');

        $mail = (new TrainingPackRejectedNotification($subscription, $pack, template: 'full_teams'))->toMail($user);

        $lines = implode(' ', $mail->introLines);
        expect($lines)->toContain('complètes');
    })->group('notifications', 'rejection', 'templates');

    test('custom message is appended to the mail', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true]);
        $subscription = Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id]);
        $pack = TrainingPack::factory()->create();
        $subscription->load('season');

        $mail = (new TrainingPackRejectedNotification(
            $subscription,
            $pack,
            message: 'Essayez le pack du jeudi.',
        ))->toMail($user);

        $lines = implode(' ', $mail->introLines);
        expect($lines)->toContain('Essayez le pack du jeudi.');
    })->group('notifications', 'rejection');

    test('one notification per rejected pack is dispatched', function () {
        Notification::fake();

        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'status' => 'paid',
        ]);
        $subscription->load(['user', 'season']);

        $pack1 = TrainingPack::factory()->create(['name' => 'Pack A']);
        $pack2 = TrainingPack::factory()->create(['name' => 'Pack B']);

        $subscription->trainingPacks()->attach($pack1->id, ['status' => 'pending']);
        $subscription->trainingPacks()->attach($pack2->id, ['status' => 'pending']);

        $pendingPacks = $subscription->trainingPacks()->wherePivot('status', 'pending')->get();
        foreach ($pendingPacks as $pack) {
            $user->notify(new TrainingPackRejectedNotification($subscription, $pack, 'Complet.', 'full_teams'));
        }
        $subscription->trainingPacks()->wherePivot('status', 'pending')->detach();

        Notification::assertSentTo($user, TrainingPackRejectedNotification::class, fn ($n) => $n->pack->name === 'Pack A');
        Notification::assertSentTo($user, TrainingPackRejectedNotification::class, fn ($n) => $n->pack->name === 'Pack B');
        expect($subscription->trainingPacks()->count())->toBe(0);
    })->group('notifications', 'rejection');

})->group('notifications');
