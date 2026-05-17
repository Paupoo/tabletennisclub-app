<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Notifications\Subscription\SubscriptionCreatedNotification;
use Illuminate\Support\Facades\Notification;

describe('SubscriptionCreatedNotification', function () {
    it('sends a mail notification when triggered on a user', function () {
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

    it('sends via mail channel only', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
        ]);

        $notification = new SubscriptionCreatedNotification($subscription);

        expect($notification->via($user))->toBe(['mail']);
    });

    it('includes season name in the mail subject', function () {
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
