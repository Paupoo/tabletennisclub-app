<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\InterclubDayAvailabilityService;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\TeamLineupNeed;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * La vue d'ensemble d'une journée, pour le sélectionneur qui doit débloquer une
 * équipe : qui a dit oui ou peut-être, à quel niveau, et où il est déjà aligné.
 */
beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
    $this->ownClub = Club::factory()->ownClub()->create();

    $this->dayAvailability = app(InterclubDayAvailabilityService::class);

    dayAvailabilityForceQueue(reset: true);
});

/**
 * Une équipe du club et sa rencontre de la semaine 42. Chaque joueur est donné
 * par sa position sur la liste de force messieurs.
 *
 * @param  array<int, int|null>  $forceIndices
 * @return array{0: Team, 1: Interclub, 2: array<int, User>}
 */
function dayAvailabilitySquad(string $name, array $forceIndices, ?League $league = null): array
{
    $league ??= test()->league;

    $players = array_map(fn (): User => User::factory()->isCompetitor()->create(), $forceIndices);

    foreach ($players as $position => $player) {
        dayAvailabilityForceQueue([$player, $forceIndices[$position]]);
    }

    $team = Team::factory()->create([
        'season_id' => test()->season->id,
        'league_id' => $league->id,
        'club_id' => test()->ownClub->id,
        'name' => $name,
    ]);

    $team->users()->attach(collect($players)->pluck('id')->all());

    $fixture = Interclub::factory()->create([
        'season_id' => test()->season->id,
        'league_id' => $league->id,
        'visited_team_id' => $team->id,
        'week_number' => 42,
        'total_players' => 4,
        'is_bye' => false,
        'start_date_time' => now()->addDays(7),
    ]);

    return [$team, $fixture, $players];
}

/**
 * @param  array{0: User, 1: int|null}|null  $entry
 * @return array<int, array{0: User, 1: int|null}>
 */
function dayAvailabilityForceQueue(?array $entry = null, bool $reset = false): array
{
    static $queue = [];

    if ($reset) {
        return $queue = [];
    }

    if ($entry !== null) {
        $queue[] = $entry;
    }

    return $queue;
}

/** Les positions se posent une fois tout le monde créé : chaque création recalcule la liste. */
function settleDayAvailabilityForce(): void
{
    foreach (dayAvailabilityForceQueue() as [$player, $index]) {
        $player->forceFill(['force_list' => $index])->saveQuietly();
    }
}

it('lists who said yes or maybe, strongest first, with their team', function (): void {
    [, $fixtureA, $playersA] = dayAvailabilitySquad('A', [5, 2, 9]);
    [, $fixtureB, $playersB] = dayAvailabilitySquad('B', [12, 7]);
    settleDayAvailabilityForce();

    $fixtureA->markAvailability($playersA[0], InterclubAvailability::AVAILABLE);
    $fixtureA->markAvailability($playersA[1], InterclubAvailability::MAYBE);
    $fixtureA->markAvailability($playersA[2], InterclubAvailability::UNAVAILABLE);
    $fixtureB->markAvailability($playersB[0], InterclubAvailability::AVAILABLE);
    $fixtureB->markAvailability($playersB[1], InterclubAvailability::AVAILABLE);

    $days = $this->dayAvailability->forWeek($this->season, 42);

    expect($days)->toHaveCount(1)
        ->and($days->first()->category)->toBe(LeagueCategory::MEN);

    $rows = $days->first()->players
        ->map(fn ($row): array => [$row->forceIndex, $row->teamName, $row->availability])
        ->all();

    expect($rows)->toBe([
        [2, 'A', InterclubAvailability::MAYBE],
        [5, 'A', InterclubAvailability::AVAILABLE],
        [7, 'B', InterclubAvailability::AVAILABLE],
        [12, 'B', InterclubAvailability::AVAILABLE],
    ]);
});

/**
 * Le pool du tiroir cache ce qu'un capitaine n'a pas publié ; le sélectionneur,
 * lui, arbitre, et doit donc voir aussi les brouillons — ici le joueur de A que
 * le capitaine de B a coché sans rien envoyer.
 */
it('tells where each player is lined up, published or still a draft', function (): void {
    [, $fixtureA, $playersA] = dayAvailabilitySquad('A', [1, 2, 3]);
    [, $fixtureB] = dayAvailabilitySquad('B', [10]);
    settleDayAvailabilityForce();

    [$published, $borrowed, $free] = $playersA;

    foreach ($playersA as $player) {
        $fixtureA->markAvailability($player, InterclubAvailability::AVAILABLE);
    }

    $fixtureA->select($published);
    $fixtureA->users()->updateExistingPivot($published->id, ['selection_confirmed_at' => now()]);
    $fixtureB->select($borrowed);

    $rows = $this->dayAvailability->forWeek($this->season, 42)->first()->players
        ->keyBy(fn ($row): int => $row->user->id);

    expect([$rows[$published->id]->lineupTeamName, $rows[$published->id]->lineupPublished])->toBe(['A', true])
        ->and([$rows[$borrowed->id]->lineupTeamName, $rows[$borrowed->id]->lineupPublished])->toBe(['B', false])
        ->and($rows[$free->id]->lineupTeamName)->toBeNull()
        ->and($rows)->toHaveCount(3);
});

/**
 * Le brouillon compte : une équipe que son capitaine a composée sans l'envoyer
 * n'est pas en manque. Une équipe en manque se couvre si ses propres oui et
 * peut-être encore libres suffisent ; sinon, c'est là qu'il faut aller chercher.
 */
it('tells which teams are short, and whether their own players can cover it', function (): void {
    [, $complete, $completePlayers] = dayAvailabilitySquad('A', [1, 2, 3, 4]);
    [, $coverable, $coverablePlayers] = dayAvailabilitySquad('B', [5, 6, 7, 8]);
    [, $uncovered, $uncoveredPlayers] = dayAvailabilitySquad('C', [9, 10, 11, 12]);
    settleDayAvailabilityForce();

    foreach ($completePlayers as $player) {
        $complete->select($player); // brouillon, jamais envoyé
    }

    $coverable->select($coverablePlayers[0]);
    $coverable->select($coverablePlayers[1]);
    $coverable->markAvailability($coverablePlayers[2], InterclubAvailability::AVAILABLE);
    $coverable->markAvailability($coverablePlayers[3], InterclubAvailability::MAYBE);

    $uncovered->select($uncoveredPlayers[0]);
    $uncovered->select($uncoveredPlayers[1]);
    $uncovered->markAvailability($uncoveredPlayers[2], InterclubAvailability::AVAILABLE);
    $uncovered->markAvailability($uncoveredPlayers[3], InterclubAvailability::UNAVAILABLE);
    $uncovered->forceFill(['short_handed_confirmed_at' => now()])->save();

    $teams = $this->dayAvailability->forWeek($this->season, 42)->first()->teams
        ->map(fn ($team): array => [$team->fixtureId, $team->teamName, $team->selectedCount, $team->totalPlayers, $team->need, $team->isShortHanded])
        ->all();

    expect($teams)->toBe([
        [$complete->id, 'A', 4, 4, TeamLineupNeed::COMPLETE, false],
        [$coverable->id, 'B', 2, 4, TeamLineupNeed::COVERABLE, false],
        [$uncovered->id, 'C', 2, 4, TeamLineupNeed::UNCOVERED, true],
    ]);
});

/**
 * Un joueur sans indice dans la catégorie ne peut pas être aligné (C.18.2.2) :
 * il sort de la liste, mais on dit combien. Les silencieux ne figurent pas non
 * plus dans la liste, mais une relance est parfois la solution — on les compte.
 */
it('counts the silent and the unranked instead of listing them', function (): void {
    [, $fixtureA, $playersA] = dayAvailabilitySquad('A', [1, null, 3, 4]);
    [, $fixtureB, $playersB] = dayAvailabilitySquad('B', [5, 6]);
    settleDayAvailabilityForce();

    $fixtureA->markAvailability($playersA[0], InterclubAvailability::AVAILABLE);
    $fixtureA->markAvailability($playersA[1], InterclubAvailability::AVAILABLE); // sans indice
    $fixtureA->markAvailability($playersA[2], InterclubAvailability::UNAVAILABLE);
    // $playersA[3] ne répond pas
    $fixtureB->markAvailability($playersB[0], InterclubAvailability::MAYBE);
    // $playersB[1] ne répond pas

    $day = $this->dayAvailability->forWeek($this->season, 42)->first();

    expect($day->players->map(fn ($row): int => $row->user->id)->all())->toBe([$playersA[0]->id, $playersB[0]->id])
        ->and($day->unrankedCount)->toBe(1)
        ->and($day->silentCount)->toBe(2);
});

/**
 * La réponse au blocage : qui peut aller où. Seuls les joueurs libres et les
 * équipes en manque sont concernés, et le verdict est celui de l'article C.22.
 *
 * A n'a que deux joueurs cochés : le seuil qu'elle impose à B se lit donc sur
 * son noyau (1, 2, 4, 6, 7), entre 4 si elle aligne ses meilleurs et 6 si elle
 * aligne ses plus faibles. Un renfort d'indice 3 est interdit en B, un 5
 * incertain, un 8 autorisé. A, qui n'a personne au-dessus, les accepte tous.
 */
it('tells where each free player could fill in, flagging what C.22 cannot settle yet', function (): void {
    [, $fixtureA, $playersA] = dayAvailabilitySquad('A', [1, 2, 4, 6, 7]);
    [, $fixtureB, $playersB] = dayAvailabilitySquad('B', [10, 11, 12]);
    [, $fixtureC, $playersC] = dayAvailabilitySquad('C', [3, 5, 8, 20, 21]);
    settleDayAvailabilityForce();

    $fixtureA->select($playersA[0]);
    $fixtureA->select($playersA[1]);
    $fixtureB->select($playersB[0]);
    $fixtureB->select($playersB[1]);
    $fixtureC->select($playersC[3]);
    $fixtureC->select($playersC[4]);

    foreach (array_slice($playersC, 0, 3) as $player) {
        $fixtureC->markAvailability($player, InterclubAvailability::AVAILABLE);
    }

    $fixtureC->markAvailability($playersC[3], InterclubAvailability::AVAILABLE);

    $rows = $this->dayAvailability->forWeek($this->season, 42)->first()->players
        ->keyBy(fn ($row): int => $row->user->id);

    expect($rows[$playersC[0]->id]->canHelp)->toBe(['A' => false])
        ->and($rows[$playersC[1]->id]->canHelp)->toBe(['A' => false, 'B' => true])
        ->and($rows[$playersC[2]->id]->canHelp)->toBe(['A' => false, 'B' => false])
        // Déjà coché en C : il ne dépanne personne.
        ->and($rows[$playersC[3]->id]->canHelp)->toBe([]);
});

it('offers no fill-in on a match day already played', function (): void {
    [, $fixtureA, $playersA] = dayAvailabilitySquad('A', [1, 2]);
    settleDayAvailabilityForce();

    $fixtureA->update(['start_date_time' => now()->subDay()]);
    $fixtureA->markAvailability($playersA[0], InterclubAvailability::AVAILABLE);

    $row = $this->dayAvailability->forWeek($this->season, 42)->first()->players->first();

    expect($row->canHelp)->toBe([]);
});

/**
 * Le verdict C.22 coûte des requêtes par équipe en manque. L'accordéon replié
 * n'affiche que ses compteurs : il demande la journée sans les verdicts.
 */
it('skips the fill-in verdicts when asked for the counts alone', function (): void {
    [, $fixtureA, $playersA] = dayAvailabilitySquad('A', [1, 2]);
    settleDayAvailabilityForce();

    $fixtureA->markAvailability($playersA[0], InterclubAvailability::AVAILABLE);

    $day = $this->dayAvailability->forWeek($this->season, 42, withFillIns: false)->first();

    expect($day->players->first()->canHelp)->toBe([])
        ->and($day->teams->first()->need)->toBe(TeamLineupNeed::UNCOVERED);
});
