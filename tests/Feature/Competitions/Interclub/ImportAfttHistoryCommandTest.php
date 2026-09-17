<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Only the season list is ever reached in these tests: the dry run stops
    // before the calendar, and the refusals stop before the federation.
    Http::fake([
        'api.aftt.be/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/Aftt/get-seasons.xml'))
        ),
    ]);

    Club::factory()->create(['is_own_club' => true, 'licence' => 'BBW214']);
});

it('refuses to run without being told how far back to go', function (): void {
    $this->artisan('interclubs:import-history')
        ->expectsOutputToContain('--from=')
        ->assertFailed();
});

it('refuses a season the federation has never published', function (): void {
    $this->artisan('interclubs:import-history', ['--from' => '1975-1976'])
        ->assertFailed();
});

it('refuses a range that runs backwards', function (): void {
    $this->artisan('interclubs:import-history', [
        '--from' => '2024-2025',
        '--to' => '2021-2022',
    ])->assertFailed();
});

it('writes nothing on a dry run, not even a missing season', function (): void {
    $before = Season::count();

    $this->artisan('interclubs:import-history', [
        '--from' => '2023-2024',
        '--to' => '2023-2024',
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(Season::count())->toBe($before)
        ->and(Season::where('name', '2023-2024')->exists())->toBeFalse();
});

it('reports every season in the range', function (): void {
    $this->artisan('interclubs:import-history', [
        '--from' => '2022-2023',
        '--to' => '2024-2025',
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('3 season(s)')
        ->assertSuccessful();
});

it('stops before the active season by default', function (): void {
    // The nightly import already keeps the running season; loading it here
    // would be a second, slower copy of the same work.
    $this->artisan('interclubs:import-history', [
        '--from' => '2025-2026',
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('1 season(s)')
        ->assertSuccessful();
});

it('names the local fixtures that would be left beside the imported ones', function (): void {
    $season = Season::factory()->create(['name' => '2023-2024', 'is_active' => false]);
    $league = League::factory()->create(['season_id' => $season->id, 'category' => 'MEN']);
    $theirClub = Club::factory()->create(['is_own_club' => false]);

    $ours = Team::factory()->create(['club_id' => Club::where('is_own_club', true)->value('id'), 'league_id' => $league->id, 'season_id' => $season->id]);
    $theirs = Team::factory()->create(['club_id' => $theirClub->id, 'league_id' => $league->id, 'season_id' => $season->id]);

    Interclub::factory()->create([
        'aftt_match_id' => null,
        'season_id' => $season->id,
        'league_id' => $league->id,
        'visited_team_id' => $ours->id,
        'visiting_team_id' => $theirs->id,
    ]);

    $this->artisan('interclubs:import-history', [
        '--from' => '2023-2024',
        '--to' => '2023-2024',
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('1 local fixture(s) still carry no federation id')
        ->assertSuccessful();
});

it('says nothing about leftovers when the season is empty', function (): void {
    Season::factory()->create(['name' => '2023-2024', 'is_active' => false]);

    $this->artisan('interclubs:import-history', [
        '--from' => '2023-2024',
        '--to' => '2023-2024',
        '--dry-run' => true,
    ])
        ->doesntExpectOutputToContain('still carry no federation id')
        ->assertSuccessful();
});
