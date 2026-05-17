<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;

describe('User::isAffiliatedForCurrentSeason()', function () {
    it('returns false when there is no active season', function () {
        $user = User::factory()->create();

        expect($user->isAffiliatedForCurrentSeason())->toBeFalse();
    });

    it('returns false when the user has no subscription', function () {
        Season::factory()->create(['is_active' => true]);
        $user = User::factory()->create();

        expect($user->isAffiliatedForCurrentSeason())->toBeFalse();
    });

    it('returns true for a pending subscription on the current season', function () {
        $season = Season::factory()->create(['is_active' => true]);
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id, 'status' => 'pending']);

        expect($user->isAffiliatedForCurrentSeason())->toBeTrue();
    });

    it('returns true for a confirmed subscription on the current season', function () {
        $season = Season::factory()->create(['is_active' => true]);
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id, 'status' => 'confirmed']);

        expect($user->isAffiliatedForCurrentSeason())->toBeTrue();
    });

    it('returns true for a paid subscription on the current season', function () {
        $season = Season::factory()->create(['is_active' => true]);
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id, 'status' => 'paid']);

        expect($user->isAffiliatedForCurrentSeason())->toBeTrue();
    });

    it('returns false for a cancelled subscription', function () {
        $season = Season::factory()->create(['is_active' => true]);
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id, 'status' => 'cancelled']);

        expect($user->isAffiliatedForCurrentSeason())->toBeFalse();
    });

    it('returns false when the subscription belongs to a past season', function () {
        Season::factory()->create(['is_active' => true]);
        $pastSeason = Season::factory()->create(['is_active' => false]);
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $pastSeason->id, 'status' => 'paid']);

        expect($user->isAffiliatedForCurrentSeason())->toBeFalse();
    });
});

describe('User::scopeAffiliatedForCurrentSeason()', function () {
    it('returns only users affiliated for the current season', function () {
        $season = Season::factory()->create(['is_active' => true]);

        $affiliated = User::factory()->create();
        $notAffiliated = User::factory()->create();

        Subscription::factory()->create(['user_id' => $affiliated->id, 'season_id' => $season->id, 'status' => 'paid']);

        $results = User::affiliatedForCurrentSeason()->pluck('id');

        expect($results)->toContain($affiliated->id)
            ->not->toContain($notAffiliated->id);
    });

    it('excludes users with only cancelled subscriptions', function () {
        $season = Season::factory()->create(['is_active' => true]);
        $user = User::factory()->create();
        Subscription::factory()->create(['user_id' => $user->id, 'season_id' => $season->id, 'status' => 'cancelled']);

        $results = User::affiliatedForCurrentSeason()->pluck('id');

        expect($results)->not->toContain($user->id);
    });
});
