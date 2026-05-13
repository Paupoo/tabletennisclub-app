<?php

declare(strict_types=1);

use App\Models\ClubEvents\Interclub\Season;
use Illuminate\Support\Facades\Cache;

describe('Season Registration Management', function () {

    // ==================== OPEN / CLOSE ====================

    test('openRegistrations sets registrations_open to true in database', function () {
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => false]);

        $season->openRegistrations();

        expect($season->fresh()->registrations_open)->toBeTrue();
        $this->assertDatabaseHas('seasons', [
            'id' => $season->id,
            'registrations_open' => true,
        ]);
    });

    test('closeRegistrations sets registrations_open to false in database', function () {
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

        $season->closeRegistrations();

        expect($season->fresh()->registrations_open)->toBeFalse();
        $this->assertDatabaseHas('seasons', [
            'id' => $season->id,
            'registrations_open' => false,
        ]);
    });

    test('openRegistrations invalidates the season cache', function () {
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => false]);

        // Warm the cache manually
        Cache::put('season.current', $season, now()->addHour());
        expect(Cache::has('season.current'))->toBeTrue();

        $season->openRegistrations();

        expect(Cache::has('season.current'))->toBeFalse();
    });

    test('closeRegistrations invalidates the season cache', function () {
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

        // Warm the cache manually
        Cache::put('season.current', $season, now()->addHour());
        expect(Cache::has('season.current'))->toBeTrue();

        $season->closeRegistrations();

        expect(Cache::has('season.current'))->toBeFalse();
    });

    test('Season::current() returns the cached instance after first call', function () {
        Cache::forget('season.current');
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

        $first = Season::current();
        $second = Season::current();

        expect($first?->id)->toBe($season->id)
            ->and($second?->id)->toBe($season->id);
    });

    test('Season::current() reflects updated registrations_open after cache bust', function () {
        Cache::forget('season.current');
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => false]);

        // Cache a stale version
        Cache::put('season.current', $season, now()->addHour());

        $season->openRegistrations(); // busts cache

        $fresh = Season::current();

        expect($fresh?->registrations_open)->toBeTrue();
    });

    test('Season::current() returns null when no active season exists', function () {
        Cache::forget('season.current');
        Season::query()->update(['is_active' => false]);

        expect(Season::current())->toBeNull();
    });

    // ==================== SEASON HELPERS ====================

    test('isCurrent returns true for active season', function () {
        $season = Season::factory()->create(['is_active' => true]);

        expect($season->isCurrent())->toBeTrue();
    });

    test('isCurrent returns false for inactive season', function () {
        $season = Season::factory()->create(['is_active' => false]);

        expect($season->isCurrent())->toBeFalse();
    });

    test('season registrations_open defaults to false', function () {
        $season = Season::factory()->create(['is_active' => true]);

        // The model attribute is null until we read back from DB (factory didn't set it, DB default applies)
        expect($season->fresh()->registrations_open)->toBeFalse();
    });

})->group('seasons');
