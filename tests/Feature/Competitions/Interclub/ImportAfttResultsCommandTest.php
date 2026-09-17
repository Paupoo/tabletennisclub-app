<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function fakeFederation(): void
{
    Http::fake([
        'api.aftt.be/*' => Http::sequence()
            ->push(file_get_contents(base_path('tests/Fixtures/Aftt/get-seasons.xml')))
            ->whenEmpty(Http::response(
                file_get_contents(base_path('tests/Fixtures/Aftt/get-matches-with-details.xml'))
            )),
    ]);
}

beforeEach(function (): void {
    fakeFederation();

    $this->season = Season::factory()->create(['is_active' => true, 'name' => '2025-2026']);
    $this->league = League::factory()->create([
        'season_id' => $this->season->id,
        'category' => 'MEN',
        'aftt_division_id' => 8860,
    ]);

    $ourClub = Club::factory()->create(['is_own_club' => true]);
    $theirClub = Club::factory()->create(['is_own_club' => false]);

    $ours = Team::factory()->create(['club_id' => $ourClub->id, 'league_id' => $this->league->id, 'season_id' => $this->season->id]);
    $theirs = Team::factory()->create(['club_id' => $theirClub->id, 'league_id' => $this->league->id, 'season_id' => $this->season->id]);

    $this->match = Interclub::factory()->create([
        'aftt_match_id' => 'PBBWH01/021',
        'league_id' => $this->league->id,
        'season_id' => $this->season->id,
        'start_date_time' => now()->subDays(3),
        'visited_team_id' => $ours->id,
        'visiting_team_id' => $theirs->id,
    ]);
});

it('imports the sheets of the active season', function (): void {
    $this->artisan('interclubs:import-results')
        ->expectsOutputToContain('2025-2026')
        ->assertSuccessful();

    expect(InterclubIndividualMatch::where('interclub_id', $this->match->id)->count())->toBe(16);
});

it('names every licence of ours that matches no member', function (): void {
    User::factory()->create(['licence' => '166488']);

    $this->artisan('interclubs:import-results')
        ->expectsOutputToContain('match no member')
        ->assertSuccessful();
});

it('says nothing about unknown licences when the roster is complete', function (): void {
    foreach (['166488', '105175', '136783', '101137'] as $licence) {
        User::factory()->create(['licence' => $licence]);
    }

    $this->artisan('interclubs:import-results')
        ->doesntExpectOutputToContain('match no member')
        ->assertSuccessful();

    expect(InterclubIndividualMatch::whereNull('user_id')->where('is_double', false)->count())->toBe(0);
});

it('refuses a season the federation does not publish', function (): void {
    $this->artisan('interclubs:import-results', ['--season' => '2019-2020'])
        ->assertFailed();
});
