<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Subscriptions\Notifications\SubscriptionCreatedNotification;
use Illuminate\Support\Facades\Notification;

describe('SubscriptionCreatedNotification', function (): void {
    it('sends a mail notification when triggered on a user', function (): void {
        Notification::fake();

        $user = User::factory()->create(['first_name' => 'Alice']);
        $season = Season::factory()->create(['name' => '2025-2026', 'is_active' => true]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'is_competitive' => false,
            'amount_due' => 60,
            'status' => 'pending',
        ]);

        $user->notify(new SubscriptionCreatedNotification($subscription));

        Notification::assertSentTo($user, SubscriptionCreatedNotification::class);
    });

    it('sends via mail channel only', function (): void {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
        ]);

        $notification = new SubscriptionCreatedNotification($subscription);

        expect($notification->via($user))->toBe(['mail', 'database']);
    });

    it('includes season name in the mail subject', function (): void {
        $user = User::factory()->create(['first_name' => 'Bob']);
        $season = Season::factory()->create(['name' => '2025-2026', 'is_active' => true]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'is_competitive' => true,
            'amount_due' => 125,
        ]);
        $subscription->load('season');

        $notification = new SubscriptionCreatedNotification($subscription);
        $mail = $notification->toMail($user);

        expect($mail->subject)->toContain('2025-2026');
    });
});
