<?php

declare(strict_types=1);

use App\Enums\InterclubResult;
use App\Models\ClubEvents\Interclub\Club;
use App\Models\ClubEvents\Interclub\League;
use App\Models\ClubEvents\Interclub\MatchResult;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSeasonWithTeamAndResults(string $name, bool $isActive, string $startAt): array
{
    $season = Season::factory()->create([
        'name' => $name,
        'is_active' => $isActive,
        'start_at' => $startAt,
        'end_at' => Carbon::parse($startAt)->addMonths(10),
    ]);

    $league = League::factory()->create(['season_id' => $season->id, 'category' => 'MEN', 'division' => '1A']);

    $ourClub = Club::factory()->create(['licence' => config('app.club_licence')]);
    $team = Team::factory()->create([
        'name' => 'A',
        'season_id' => $season->id,
        'league_id' => $league->id,
        'club_id' => $ourClub->id,
    ]);

    MatchResult::factory()->create([
        'team_id' => $team->id,
        'season_id' => $season->id,
        'match_date' => Carbon::parse($startAt)->addMonth(),
        'result' => InterclubResult::WIN,
    ]);

    return compact('season', 'team', 'league');
}

test('public results page is accessible without authentication', function (): void {
    $this->get(route('results'))->assertOk();
});

test('shows current active season by default', function (): void {
    ['season' => $currentSeason] = makeSeasonWithTeamAndResults('2025-2026', true, '2025-09-01');

    $this->get(route('results'))
        ->assertOk()
        ->assertSee($currentSeason->name);
});

test('shows no more than 5 past seasons plus the current one in dropdown', function (): void {
    Season::factory()->create([
        'name' => '2025-2026',
        'is_active' => true,
        'start_at' => '2025-09-01',
        'end_at' => '2026-06-30',
    ]);

    for ($i = 1; $i <= 7; $i++) {
        $year = 2025 - $i;
        Season::factory()->create([
            'name' => "{$year}-" . ($year + 1),
            'is_active' => false,
            'start_at' => "{$year}-09-01",
            'end_at' => ($year + 1) . '-06-30',
        ]);
    }

    $response = $this->get(route('results'))->assertOk();

    $response->assertSee('2025-2026');   // current
    $response->assertSee('2024-2025');   // 1st past
    $response->assertSee('2020-2021');   // 5th past
    $response->assertDontSee('2019-2020'); // 6th past — must be hidden
});

test('future seasons do not appear in the dropdown', function (): void {
    Season::factory()->create([
        'name' => '2024-2025',
        'is_active' => true,
        'start_at' => '2024-09-01',
        'end_at' => '2025-06-30',
    ]);

    Season::factory()->create([
        'name' => '2025-2026',
        'is_active' => false,
        'start_at' => '2025-09-01',
        'end_at' => '2026-06-30',
    ]);

    $this->get(route('results'))
        ->assertOk()
        ->assertDontSee('2025-2026');
});

test('season query param switches the displayed season', function (): void {
    makeSeasonWithTeamAndResults('2024-2025', true, '2024-09-01');

    $past = Season::factory()->create([
        'name' => '2023-2024',
        'is_active' => false,
        'start_at' => '2023-09-01',
        'end_at' => '2024-06-30',
    ]);

    $ourClub = Club::firstWhere('licence', config('app.club_licence'));
    $leaguePast = League::factory()->create(['season_id' => $past->id, 'category' => 'MEN', 'division' => '2A']);
    Team::factory()->create([
        'name' => 'B',
        'season_id' => $past->id,
        'league_id' => $leaguePast->id,
        'club_id' => $ourClub->id,
    ]);

    $this->get(route('results', ['season' => '2023-2024']))
        ->assertOk()
        ->assertSee('2023-2024');
});

test('unknown season query param falls back to current season', function (): void {
    ['season' => $currentSeason] = makeSeasonWithTeamAndResults('2025-2026', true, '2025-09-01');

    $this->get(route('results', ['season' => 'does-not-exist']))
        ->assertOk()
        ->assertSee($currentSeason->name);
});

test('shows no results message when season has no team data', function (): void {
    Season::factory()->create([
        'name' => '2025-2026',
        'is_active' => true,
        'start_at' => '2025-09-01',
        'end_at' => '2026-06-30',
    ]);

    $this->get(route('results'))
        ->assertOk()
        ->assertSee('Aucun résultat disponible');
});

test('shows match results for the active season', function (): void {
    ['season' => $season, 'team' => $team] = makeSeasonWithTeamAndResults('2025-2026', true, '2025-09-01');

    MatchResult::factory()->create([
        'team_id' => $team->id,
        'season_id' => $season->id,
        'opponent_name' => 'Adversaire Test A',
        'result' => InterclubResult::WIN,
        'match_date' => '2025-10-15',
    ]);

    $this->get(route('results'))
        ->assertOk()
        ->assertSee('Adversaire Test A')
        ->assertSee('Victoire');
});
