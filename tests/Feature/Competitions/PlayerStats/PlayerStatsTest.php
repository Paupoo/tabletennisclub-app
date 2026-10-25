<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\OfficialTournamentMatch;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\PlayerStats;
use App\Domains\Competitions\Interclub\Services\PlayerStatsFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $this->ourClub = Club::factory()->create(['is_own_club' => true]);
    $this->theirClub = Club::factory()->create(['is_own_club' => false]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
    $this->team = Team::factory()->create([
        'club_id' => $this->ourClub->id, 'league_id' => $this->league->id, 'season_id' => $this->season->id,
    ]);
    $this->player = User::factory()->isCompetitor()->create(['ranking' => 'C2']);
});

/**
 * A played tie of ours at home, in the active season unless told otherwise.
 */
function statsTie(?Team $team = null, ?string $playedOn = null): Interclub
{
    $team ??= test()->team;
    $theirs = Team::factory()->create([
        'club_id' => test()->theirClub->id, 'league_id' => $team->league_id, 'season_id' => $team->season_id,
    ]);

    return Interclub::factory()->create([
        'season_id' => $team->season_id,
        'league_id' => $team->league_id,
        'visited_team_id' => $team->id,
        'visiting_team_id' => $theirs->id,
        'start_date_time' => $playedOn ?? now()->subWeek()->toDateTimeString(),
    ]);
}

/**
 * One individual line of the player's, against an opponent of the given ranking.
 */
function playedLine(User $player, string $ours, string $theirs, bool $won, ?Interclub $tie = null, array $attributes = []): InterclubIndividualMatch
{
    static $position = 0;

    return InterclubIndividualMatch::factory()->create([
        'interclub_id' => ($tie ?? statsTie())->id,
        'position' => ++$position % 16 + 1,
        'user_id' => $player->id,
        'our_player_ranking' => $ours,
        'opponent_ranking' => $theirs,
        'we_won' => $won,
        ...$attributes,
    ]);
}

function statsFor(User $player, array $filter = []): array
{
    return app(PlayerStats::class)
        ->rows(PlayerStatsFilter::from($filter))
        ->firstWhere('user_id', $player->id);
}

it('measures a win against one ranking above as a win over the odds', function (): void {
    playedLine($this->player, 'C2', 'C0', won: true);

    $row = statsFor($this->player);

    // One cran above: 38 % expected by default, so the win is worth +0.62.
    expect($row['interclub']['played'])->toBe(1)
        ->and($row['interclub']['won'])->toBe(1)
        ->and($row['interclub']['expected'])->toBe(0.38)
        ->and($row['interclub']['score'])->toBe(0.62);
});

it('keeps forfeits out of the rate and the score, and counts them apart', function (): void {
    $tie = statsTie();
    playedLine($this->player, 'C2', 'C2', won: true, tie: $tie);
    playedLine($this->player, 'C2', 'C2', won: true, tie: $tie, attributes: ['is_forfeit' => true, 'our_sets' => null, 'their_sets' => null]);
    InterclubIndividualMatch::factory()->double()->create(['interclub_id' => $tie->id, 'we_won' => true]);

    $row = statsFor($this->player)['interclub'];

    expect($row['played'])->toBe(1)
        ->and($row['forfeits'])->toBe(1)
        ->and($row['rate'])->toBe(100)
        ->and($row['score'])->toBe(0.5);
});

it('counts wins over stronger players and losses to weaker ones', function (): void {
    playedLine($this->player, 'C2', 'B6', won: true);   // perf
    playedLine($this->player, 'C2', 'C6', won: false);  // contre-perf
    playedLine($this->player, 'C2', 'C6', won: true);   // expected win
    playedLine($this->player, 'C2', 'C2', won: false);  // even

    $row = statsFor($this->player)['interclub'];

    expect($row['perfs'])->toBe(1)
        ->and($row['contre_perfs'])->toBe(1)
        ->and($row['rated'])->toBe(4)
        // won 2, expected 0.27 + 0.73 + 0.73 + 0.5 = 2.23
        ->and($row['score'])->toBe(-0.23)
        ->and($row['score_per_match'])->toBe(-0.06);
});

it('reads the gap off the sheet, not off the ranking held today', function (): void {
    $this->player->update(['ranking' => 'B0']);
    playedLine($this->player, 'D0', 'C6', won: true);

    expect(statsFor($this->player)['interclub']['perfs'])->toBe(1);
});

it('rates nothing when a ranking is missing from the sheet', function (): void {
    playedLine($this->player, 'C2', 'C2', won: true, attributes: ['our_player_ranking' => null]);

    $row = statsFor($this->player)['interclub'];

    expect($row['played'])->toBe(1)->and($row['rated'])->toBe(0)->and($row['score_per_match'])->toBeNull();
});

it('tallies tournaments apart, and sums both only in a third figure', function (): void {
    playedLine($this->player, 'C2', 'C2', won: true);
    OfficialTournamentMatch::factory()->playedBy($this->player)->create([
        'season_id' => $this->season->id, 'played_on' => now()->subDays(3),
        'tournament_name' => 'Critérium', 'player_ranking' => 'C2', 'opponent_ranking' => 'C0', 'we_won' => false,
    ]);
    OfficialTournamentMatch::factory()->playedBy($this->player)->create([
        'season_id' => $this->season->id, 'played_on' => now()->subDays(3),
        'tournament_name' => 'Critérium', 'player_ranking' => 'C2', 'opponent_ranking' => 'C4', 'we_won' => true,
    ]);

    $row = statsFor($this->player);

    expect($row['interclub']['played'])->toBe(1)
        ->and($row['tournaments']['played'])->toBe(2)
        ->and($row['tournaments']['tournaments'])->toBe(1)
        ->and($row['both']['played'])->toBe(3)
        ->and($row['both']['won'])->toBe(2)
        // 0.5 + 0.38 + 0.62 = 1.5 expected, 2 won
        ->and($row['both']['score'])->toBe(0.5);
});

it('counts the running season unless asked for another stretch', function (): void {
    $last = Season::factory()->create(['is_active' => false]);
    $oldLeague = League::factory()->create(['season_id' => $last->id, 'category' => 'MEN']);
    $oldTeam = Team::factory()->create(['club_id' => $this->ourClub->id, 'league_id' => $oldLeague->id, 'season_id' => $last->id]);

    playedLine($this->player, 'C2', 'C2', won: true);
    playedLine($this->player, 'C2', 'C2', won: true, tie: statsTie($oldTeam, now()->subMonths(14)->toDateTimeString()));

    expect(statsFor($this->player)['interclub']['played'])->toBe(1)
        ->and(statsFor($this->player, ['seasonId' => $last->id])['interclub']['played'])->toBe(1)
        ->and(statsFor($this->player, ['seasonId' => 0])['interclub']['played'])->toBe(2)
        ->and(statsFor($this->player, ['months' => 18])['interclub']['played'])->toBe(2)
        ->and(statsFor($this->player, ['months' => 6])['interclub']['played'])->toBe(1);
});

function listedIds(array $filter = []): array
{
    return app(PlayerStats::class)->rows(PlayerStatsFilter::from($filter))->pluck('user_id')->all();
}

it('lists every competitor, those who never played included', function (): void {
    $recreational = User::factory()->create();

    expect(listedIds())->toContain($this->player->id)
        ->not->toContain($recreational->id);
});

it('brings back former members only when asked, and never the anonymised', function (): void {
    $former = User::factory()->create();
    playedLine($former, 'C2', 'C2', won: true);

    $departed = User::factory()->create();
    playedLine($departed, 'C2', 'C2', won: true);
    $departed->delete();

    $erased = User::factory()->create(['email' => 'deleted-999@anonymous.local']);
    playedLine($erased, 'C2', 'C2', won: true);
    $erased->delete();

    expect(listedIds())->not->toContain($former->id);

    expect(listedIds(['includeFormer' => true]))
        ->toContain($former->id)
        ->toContain($departed->id)
        ->not->toContain($erased->id);
});

it('narrows the list by ranking, gender and year of birth', function (): void {
    $this->player->update(['gender' => 'MEN', 'birthdate' => '1990-05-01']);
    $girl = User::factory()->isCompetitor()->create(['ranking' => 'E2', 'gender' => 'WOMEN', 'birthdate' => '2013-02-01']);

    expect(listedIds(['rankings' => ['E2']]))->toBe([$girl->id])
        ->and(listedIds(['gender' => 'WOMEN']))->toBe([$girl->id])
        ->and(listedIds(['bornFrom' => 2012, 'bornTo' => 2014]))->toBe([$girl->id])
        ->and(listedIds(['userIds' => [$this->player->id]]))->toBe([$this->player->id]);
});

it('narrows the list to a team roster, and its figures to that team when asked', function (): void {
    $this->team->users()->attach($this->player);
    $outsider = User::factory()->isCompetitor()->create();

    $otherTeam = Team::factory()->create([
        'club_id' => $this->ourClub->id, 'league_id' => $this->league->id, 'season_id' => $this->season->id,
    ]);
    playedLine($this->player, 'C2', 'C2', won: true);
    playedLine($this->player, 'C2', 'C2', won: true, tie: statsTie($otherTeam));

    expect(listedIds(['teamId' => $this->team->id]))->toBe([$this->player->id])
        ->and(statsFor($this->player, ['teamId' => $this->team->id])['interclub']['played'])->toBe(2)
        ->and(statsFor($this->player, ['teamId' => $this->team->id, 'onlyTeamMatches' => true])['interclub']['played'])->toBe(1);
});

it('measures how often a player turns out for the ties of his team', function (): void {
    $this->team->users()->attach($this->player);
    $played = statsTie();
    $missed = statsTie();
    statsTie(playedOn: now()->addWeek()->toDateTimeString()); // not played yet

    playedLine($this->player, 'C2', 'C2', won: true, tie: $played);
    playedLine($this->player, 'C2', 'C2', won: false, tie: $played);

    $row = statsFor($this->player)['interclub'];

    expect($row['ties'])->toBe(1)
        ->and($row['team_ties'])->toBe(2)
        ->and($row['participation'])->toBe(50);
});

it('counts the evenings a player said yes and was left out', function (): void {
    $leftOut = statsTie();
    $picked = statsTie();
    $leftOut->users()->attach($this->player, ['availability' => 'available', 'is_selected' => false]);
    $picked->users()->attach($this->player, ['availability' => 'available', 'is_selected' => true]);

    $other = User::factory()->isCompetitor()->create();

    expect(statsFor($this->player)['interclub']['available_not_selected'])->toBe(1)
        // No answer on record at all: unknown, not zero.
        ->and(statsFor($other)['interclub']['available_not_selected'])->toBeNull();
});

it('compares the season with the one before, and the last ten matches with the ten before', function (): void {
    $last = Season::factory()->create([
        'is_active' => false,
        'start_at' => $this->season->start_at->copy()->subYear(),
        'end_at' => $this->season->end_at->copy()->subYear(),
    ]);
    $oldLeague = League::factory()->create(['season_id' => $last->id, 'category' => 'MEN']);
    $oldTeam = Team::factory()->create(['club_id' => $this->ourClub->id, 'league_id' => $oldLeague->id, 'season_id' => $last->id]);

    // Last season: ten even matches, all lost. This one: ten, all won.
    foreach (range(1, 10) as $week) {
        playedLine($this->player, 'C2', 'C2', won: false, tie: statsTie($oldTeam, now()->subMonths(12)->addDays($week)->toDateTimeString()));
        playedLine($this->player, 'C2', 'C2', won: true, tie: statsTie(playedOn: now()->subDays(30 - $week)->toDateTimeString()));
    }

    $trend = statsFor($this->player)['interclub']['trend'];

    expect($trend['season'])->toBe(1.0)
        ->and($trend['form'])->toBe(1.0);
});

it('has no trend to show without enough behind it', function (): void {
    playedLine($this->player, 'C2', 'C2', won: true);

    expect(statsFor($this->player)['interclub']['trend'])->toBe(['season' => null, 'form' => null]);
});

function detailFor(User $player, string $kind = 'interclub', array $filter = []): array
{
    return app(PlayerStats::class)->player($player, PlayerStatsFilter::from($filter), $kind);
}

it('splits a player\'s results by ranking gap', function (): void {
    playedLine($this->player, 'C2', 'C0', won: true);
    playedLine($this->player, 'C2', 'C0', won: false);
    playedLine($this->player, 'C2', 'E6', won: true);

    $byGap = detailFor($this->player)['by_gap'];

    expect($byGap[1])->toMatchArray(['played' => 2, 'won' => 1, 'expected' => 0.76])
        // Twenty crans down is still "four and more".
        ->and($byGap[-4])->toMatchArray(['played' => 1, 'won' => 1])
        ->and($byGap[0])->toMatchArray(['played' => 0]);
});

it('lists the wins over stronger players and the losses to weaker ones', function (): void {
    playedLine($this->player, 'C2', 'B6', won: true, attributes: ['opponent_name' => 'Alice Strong']);
    playedLine($this->player, 'C2', 'D0', won: false, attributes: ['opponent_name' => 'Bob Weak']);
    playedLine($this->player, 'C2', 'D0', won: true, attributes: ['opponent_name' => 'Carl Weak']);

    $detail = detailFor($this->player);

    expect(collect($detail['perfs'])->pluck('opponent')->all())->toBe(['Alice Strong'])
        ->and(collect($detail['contre_perfs'])->pluck('opponent')->all())->toBe(['Bob Weak']);
});

it('tells the player\'s record against each opponent met', function (): void {
    foreach ([true, true, false] as $won) {
        playedLine($this->player, 'C2', 'C2', won: $won, attributes: ['opponent_name' => 'Dana Rival', 'opponent_licence' => '123456']);
    }
    playedLine($this->player, 'C2', 'C2', won: true, attributes: ['opponent_name' => 'Eve Once', 'opponent_licence' => '654321']);

    $opponents = detailFor($this->player)['opponents'];

    expect($opponents[0])->toMatchArray(['name' => 'Dana Rival', 'played' => 3, 'won' => 2])
        ->and($opponents[1])->toMatchArray(['name' => 'Eve Once', 'played' => 1, 'won' => 1]);
});

it('splits a player\'s interclub results by the team he played for', function (): void {
    $this->team->update(['name' => 'B']);
    $otherTeam = Team::factory()->create([
        'club_id' => $this->ourClub->id, 'league_id' => $this->league->id, 'season_id' => $this->season->id, 'name' => 'D',
    ]);
    playedLine($this->player, 'C2', 'C2', won: true);
    playedLine($this->player, 'C2', 'C2', won: false, tie: statsTie($otherTeam));

    $byTeam = collect(detailFor($this->player)['by_team'])->keyBy('team');

    expect($byTeam)->toHaveCount(2)
        ->and($byTeam->first(fn (array $team): bool => str_ends_with($team['team'], ' B'))['won'])->toBe(1)
        ->and($byTeam->first(fn (array $team): bool => str_ends_with($team['team'], ' D'))['won'])->toBe(0);
});

it('traces the ranking a player carried, match after match', function (): void {
    playedLine($this->player, 'D0', 'C2', won: true, tie: statsTie(playedOn: now()->subMonths(5)->toDateTimeString()));
    playedLine($this->player, 'D0', 'C2', won: true, tie: statsTie(playedOn: now()->subMonths(4)->toDateTimeString()));
    playedLine($this->player, 'C6', 'C2', won: true, tie: statsTie(playedOn: now()->subMonths(1)->toDateTimeString()));

    $history = detailFor($this->player, 'both', ['seasonId' => 0])['ranking_history'];

    expect(collect($history)->pluck('ranking')->all())->toBe(['D0', 'C6']);
});

it('follows a player month by month and season by season', function (): void {
    playedLine($this->player, 'C2', 'C2', won: true, tie: statsTie(playedOn: now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(3)->toDateTimeString()));
    playedLine($this->player, 'C2', 'C2', won: false, tie: statsTie(playedOn: now()->subMonthsNoOverflow(1)->startOfMonth()->addDays(3)->toDateTimeString()));

    $detail = detailFor($this->player, 'interclub', ['seasonId' => 0]);

    expect($detail['monthly'])->toHaveCount(2)
        ->and(array_column($detail['monthly'], 'won'))->toBe([1, 0])
        ->and($detail['by_season'])->toHaveCount(1)
        ->and($detail['by_season'][0]['played'])->toBe(2);
});
