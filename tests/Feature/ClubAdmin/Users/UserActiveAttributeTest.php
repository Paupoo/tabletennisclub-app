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
    $otherSeason = Season::factory()->create([
        'is_active' => false,
        'start_at' => now()->subYear()->startOfYear(),
        'end_at' => now()->subYear()->endOfYear(),
    ]);
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

// ── Compétiteur ⊂ actif ──────────────────────────────────────────────────────

/**
 * Un compétiteur est d'abord un membre actif. L'attribut et le scope doivent
 * rester alignés : UserObserver lit l'attribut, RecalculateForceListAction lit
 * le scope, et les faire diverger laisse des force_list à null.
 */
describe('competitor implies active membership', function (): void {
    it('is not a competitor when the competitive subscription is terminated', function (string $status): void {
        $season = makeActiveSeason();
        $user = User::factory()->create();
        Subscription::factory()->for($user)->create([
            'season_id' => $season->id,
            'status' => $status,
            'is_competitive' => true,
        ]);

        expect($user->fresh()->is_competitor)->toBeFalse();
        expect(User::competitor()->whereKey($user->id)->exists())->toBeFalse();
    })->with(['cancelled', 'refunded', 'pending']);

    it('is a competitor with an active competitive subscription', function (string $status): void {
        $season = makeActiveSeason();
        $user = User::factory()->create();
        Subscription::factory()->for($user)->create([
            'season_id' => $season->id,
            'status' => $status,
            'is_competitive' => true,
        ]);

        expect($user->fresh()->is_competitor)->toBeTrue();
        expect(User::competitor()->whereKey($user->id)->exists())->toBeTrue();
    })->with(['confirmed', 'paid']);

    it('agrees between the attribute and the scope when subscriptions are eager-loaded', function (): void {
        $season = makeActiveSeason();
        $user = User::factory()->create();
        Subscription::factory()->for($user)->cancelled()->create([
            'season_id' => $season->id,
            'is_competitive' => true,
        ]);

        $eagerLoaded = User::with('subscriptions')->find($user->id);

        expect($eagerLoaded->is_competitor)->toBeFalse();
    });
});
