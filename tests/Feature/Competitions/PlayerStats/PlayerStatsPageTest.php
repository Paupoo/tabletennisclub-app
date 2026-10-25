<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\PlayerStats;
use App\Domains\Competitions\Interclub\Services\PlayerStatsFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
| Statistiques des joueurs (grilling du 2026-10-25) : le comité décide des
| sélections, des noyaux et des inscriptions des jeunes sur qui gagne au-delà
| de ce que son classement promettait, et sur qui joue.
*/
beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $this->ourClub = Club::factory()->create(['is_own_club' => true]);
    $this->theirClub = Club::factory()->create(['is_own_club' => false]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
    $this->team = Team::factory()->create([
        'club_id' => $this->ourClub->id, 'league_id' => $this->league->id, 'season_id' => $this->season->id,
    ]);
    $this->committee = User::factory()->isCommitteeMember()->create();
});

function pageTie(): Interclub
{
    $theirs = Team::factory()->create([
        'club_id' => test()->theirClub->id, 'league_id' => test()->league->id, 'season_id' => test()->season->id,
    ]);

    return Interclub::factory()->create([
        'season_id' => test()->season->id,
        'league_id' => test()->league->id,
        'visited_team_id' => test()->team->id,
        'visiting_team_id' => $theirs->id,
        'start_date_time' => now()->subWeek(),
    ]);
}

/**
 * A competitor with the given number of even matches, won and lost.
 */
function competitorWith(string $lastName, int $won, int $lost): User
{
    $player = User::factory()->isCompetitor()->create(['last_name' => $lastName, 'ranking' => 'C2']);
    $tie = pageTie();
    $position = 0;

    foreach ([...array_fill(0, $won, true), ...array_fill(0, $lost, false)] as $result) {
        InterclubIndividualMatch::factory()->create([
            'interclub_id' => $tie->id, 'position' => ++$position, 'user_id' => $player->id,
            'our_player_ranking' => 'C2', 'opponent_ranking' => 'C2', 'we_won' => $result,
        ]);
    }

    return $player;
}

it('opens the statistics to the committee and to nobody else', function (): void {
    $this->actingAs($this->committee)->get(route('admin.interclubs.player-stats'))->assertOk();

    $member = User::factory()->isCompetitor()->create();
    $this->actingAs($member)->get(route('admin.interclubs.player-stats'))->assertForbidden();
});

function statsPage(array $params = []): Testable
{
    return Livewire::actingAs(test()->committee)->test('pages::club-events.interclubs.player-stats', $params);
}

it('ranks the best by what they won beyond the odds, once they have played enough', function (): void {
    $steady = competitorWith('Steady', won: 4, lost: 2);
    $star = competitorWith('Star', won: 6, lost: 0);
    competitorWith('Newcomer', won: 1, lost: 0);

    $names = statsPage()->set('tab', 'best')->viewData('rows')->pluck('name');

    expect($names->map(fn (string $name): string => str($name)->afterLast(' ')->toString())->all())
        ->toBe(['Star', 'Steady']);
});

it('lets the threshold go down to show the newcomers', function (): void {
    competitorWith('Newcomer', won: 1, lost: 0);

    expect(statsPage()->set('minMatches', 1)->viewData('rows'))->toHaveCount(1);
});

it('starts the least-played list with the competitors who never played', function (): void {
    competitorWith('Regular', won: 3, lost: 3);
    $idle = User::factory()->isCompetitor()->create(['last_name' => 'Idle']);

    $rows = statsPage()->set('tab', 'least')->viewData('rows');

    expect($rows->first()['user_id'])->toBe($idle->id);
});

it('sends the ticked players to the comparison', function (): void {
    $one = competitorWith('One', won: 3, lost: 3);
    $two = competitorWith('Two', won: 5, lost: 1);

    statsPage()
        ->set('selected', [(string) $one->id, (string) $two->id])
        ->call('compare')
        ->assertRedirect(route('admin.interclubs.player-stats.compare', ['ids' => $one->id . ',' . $two->id, 'kind' => 'interclub', 'period' => 'current']));
});

it('puts the compared players side by side', function (): void {
    $one = competitorWith('Oneson', won: 3, lost: 3);
    $two = competitorWith('Twoson', won: 5, lost: 1);

    Livewire::actingAs($this->committee)
        ->withQueryParams(['ids' => $one->id . ',' . $two->id])
        ->test('pages::club-events.interclubs.player-compare')
        ->assertSee('Oneson')
        ->assertSee('Twoson')
        ->assertViewHas('players', fn ($players): bool => count($players) === 2);
});

it('zooms on one player', function (): void {
    $player = competitorWith('Zoomed', won: 2, lost: 1);

    $this->actingAs($this->committee)
        ->get(route('admin.interclubs.player-stats.show', $player))
        ->assertOk()
        ->assertSee('Zoomed');
});

it('never shows an anonymised member', function (): void {
    $erased = User::factory()->create(['email' => 'deleted-1@anonymous.local']);
    $erased->delete();

    $this->actingAs($this->committee)
        ->get(route('admin.interclubs.player-stats.show', $erased->id))
        ->assertNotFound();
});

it('leaves the expected-win table to the administrators', function (): void {
    $this->actingAs($this->committee)
        ->get(route('admin.interclubs.player-stats.expected-win-rates'))
        ->assertForbidden();
});

it('lets an administrator change the expected-win table, and the scores follow', function (): void {
    $admin = User::factory()->isAdmin()->create();
    $player = User::factory()->isCompetitor()->create(['ranking' => 'C2']);
    InterclubIndividualMatch::factory()->create([
        'interclub_id' => pageTie()->id, 'position' => 1, 'user_id' => $player->id,
        'our_player_ranking' => 'C2', 'opponent_ranking' => 'C2', 'we_won' => true,
    ]);

    Livewire::actingAs($admin)
        ->test('pages::club-events.interclubs.expected-win-rates')
        ->set('rates.0', 40)
        ->call('save')
        ->assertHasNoErrors();

    $row = app(PlayerStats::class)
        ->rows(PlayerStatsFilter::from([]))
        ->firstWhere('user_id', $player->id);

    expect($row['interclub']['score'])->toBe(0.6);
});

it('refuses a rate outside 0 to 100', function (): void {
    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.interclubs.expected-win-rates')
        ->set('rates.1', 140)
        ->call('save')
        ->assertHasErrors('rates.1');
});
