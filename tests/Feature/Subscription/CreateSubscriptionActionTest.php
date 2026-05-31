<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\CreateSubscriptionAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a subscription for an active season with open registrations', function (): void {
    $user = User::factory()->create();
    $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

    $subscription = (new CreateSubscriptionAction)->execute($user, $season, []);

    expect($subscription->user_id)->toBe($user->id);
    expect($subscription->season_id)->toBe($season->id);
    expect(Subscription::where('user_id', $user->id)->where('season_id', $season->id)->exists())->toBeTrue();
});

it('throws when the season has registrations closed', function (): void {
    $user = User::factory()->create();
    $season = Season::factory()->create(['is_active' => true, 'registrations_open' => false]);

    expect(fn (): mixed => (new CreateSubscriptionAction)->execute($user, $season, []))
        ->toThrow(Exception::class);
});

it('throws when user already has an active subscription for the season', function (): void {
    $user = User::factory()->create();
    $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

    (new CreateSubscriptionAction)->execute($user, $season, []);

    expect(fn (): mixed => (new CreateSubscriptionAction)->execute($user, $season, []))
        ->toThrow(Exception::class);
});

it('sets the initial status to pending', function (): void {
    $user = User::factory()->create();
    $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

    $subscription = (new CreateSubscriptionAction)->execute($user, $season, []);

    expect($subscription->status)->toBe('pending');
});
