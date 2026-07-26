<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Support\Facades\Cache;

describe('Season Registration Management', function (): void {

    // ==================== OPEN / CLOSE ====================

    test('openAffiliations sets affiliations_open to true in database', function (): void {
        $season = Season::factory()->create(['is_active' => true, 'affiliations_open' => false]);

        $season->openAffiliations();

        expect($season->fresh()->affiliations_open)->toBeTrue();
        $this->assertDatabaseHas('seasons', [
            'id' => $season->id,
            'affiliations_open' => true,
        ]);
    });

    test('closeAffiliations sets affiliations_open to false in database', function (): void {
        $season = Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);

        $season->closeAffiliations();

        expect($season->fresh()->affiliations_open)->toBeFalse();
        $this->assertDatabaseHas('seasons', [
            'id' => $season->id,
            'affiliations_open' => false,
        ]);
    });

    test('openAffiliations invalidates the season cache', function (): void {
        $season = Season::factory()->create(['is_active' => true, 'affiliations_open' => false]);

        // Warm the cache manually
        Cache::put('season.current', $season, now()->addHour());
        expect(Cache::has('season.current'))->toBeTrue();

        $season->openAffiliations();

        expect(Cache::has('season.current'))->toBeFalse();
    });

    test('closeAffiliations invalidates the season cache', function (): void {
        $season = Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);

        // Warm the cache manually
        Cache::put('season.current', $season, now()->addHour());
        expect(Cache::has('season.current'))->toBeTrue();

        $season->closeAffiliations();

        expect(Cache::has('season.current'))->toBeFalse();
    });

    test('Season::current() returns the cached instance after first call', function (): void {
        Cache::forget('season.current');
        $season = Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);

        $first = Season::current();
        $second = Season::current();

        expect($first?->id)->toBe($season->id)
            ->and($second?->id)->toBe($season->id);
    });

    test('Season::current() reflects updated affiliations_open after cache bust', function (): void {
        Cache::forget('season.current');
        $season = Season::factory()->create(['is_active' => true, 'affiliations_open' => false]);

        // Cache a stale version
        Cache::put('season.current', $season, now()->addHour());

        $season->openAffiliations(); // busts cache

        $fresh = Season::current();

        expect($fresh?->affiliations_open)->toBeTrue();
    });

    test('Season::current() returns null when no active season exists', function (): void {
        Cache::forget('season.current');
        Season::query()->update(['is_active' => false]);

        expect(Season::current())->toBeNull();
    });

    // ==================== SEASON HELPERS ====================

    test('isCurrent returns true for active season', function (): void {
        $season = Season::factory()->create(['is_active' => true]);

        expect($season->isCurrent())->toBeTrue();
    });

    test('isCurrent returns false for inactive season', function (): void {
        $season = Season::factory()->create(['is_active' => false]);

        expect($season->isCurrent())->toBeFalse();
    });

    test('season affiliations_open defaults to false', function (): void {
        $season = Season::factory()->create(['is_active' => true]);

        // The model attribute is null until we read back from DB (factory didn't set it, DB default applies)
        expect($season->fresh()->affiliations_open)->toBeFalse();
    });

})->group('seasons');
