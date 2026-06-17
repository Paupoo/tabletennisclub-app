<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;

// Regression: the users.is_active column was dropped (commit 1c726eaa); the flag is now
// derived from the current season's subscription status via getIsActiveAttribute(),
// and must stay in sync with the User::active() query scope.

it('is active with a confirmed subscription for the current season', function (): void {
    $season = makeActiveSeason();
    $user = User::factory()->create();
    Subscription::factory()->for($user)->create(['season_id' => $season->id, 'status' => 'confirmed']);

    expect($user->is_active)->toBeTrue();
});

it('is active with a paid subscription for the current season', function (): void {
    $season = makeActiveSeason();
    $user = User::factory()->create();
    Subscription::factory()->for($user)->create(['season_id' => $season->id, 'status' => 'paid']);

    expect($user->is_active)->toBeTrue();
});

it('is not active without any subscription', function (): void {
    makeActiveSeason();
    $user = User::factory()->create();

    expect($user->is_active)->toBeFalse();
});

it('is not active when the subscription is only pending', function (): void {
    $season = makeActiveSeason();
    $user = User::factory()->create();
    Subscription::factory()->for($user)->create(['season_id' => $season->id, 'status' => 'pending']);

    expect($user->is_active)->toBeFalse();
});

it('is not active when the confirmed subscription belongs to another season', function (): void {
    makeActiveSeason();
    $otherSeason = Season::factory()->create(['is_active' => false]);
    $user = User::factory()->create();
    Subscription::factory()->for($user)->create(['season_id' => $otherSeason->id, 'status' => 'confirmed']);

    expect($user->is_active)->toBeFalse();
});

it('stays in sync with the User::active() scope', function (): void {
    $season = makeActiveSeason();

    $active = User::factory()->create();
    Subscription::factory()->for($active)->create(['season_id' => $season->id, 'status' => 'confirmed']);

    $inactive = User::factory()->create();

    $scopeIds = User::active()->pluck('id');

    expect($active->is_active)->toBeTrue()
        ->and($inactive->is_active)->toBeFalse()
        ->and($scopeIds)->toContain($active->id)
        ->and($scopeIds)->not->toContain($inactive->id);
});
