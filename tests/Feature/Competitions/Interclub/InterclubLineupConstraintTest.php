<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\InterclubLineupLegalityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * L'article C.22 appliqué à de vraies équipes : celle qu'on compose, celles qui
 * la dominent, et les indices de référence que porte la liste des forces.
 */
beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $this->ownClub = Club::factory()->ownClub()->create();
    $this->rule = app(InterclubLineupLegalityService::class);
    forceQueue(reset: true);
});

/**
 * Poser les positions de liste de force, une fois tout le monde créé.
 *
 * `force_list` est casté mais pas `fillable`, et surtout : créer un compétiteur
 * déclenche `RecalculateForceListAction` depuis `UserObserver::saved()`, qui
 * réécrit la liste de tout le club. Poser les positions au fil de l'eau les fait
 * écraser par la création suivante, et le test mesure alors un ordre qu'il n'a
 * pas choisi. Même piège que `placeOnForceList()` dans TeamCoreListTest.
 */
function settleForceLists(): void
{
    foreach (forceQueue() as [$player, $column, $index]) {
        $player->forceFill([$column => $index])->saveQuietly();
    }
}

/**
 * La file des positions à poser, gardée hors du test lui-même : `test()` rend un
 * proxy Pest, sur lequel une écriture indirecte de tableau n'a aucun effet.
 *
 * @param  array{0: User, 1: string, 2: int}|null  $entry
 * @return array<int, array{0: User, 1: string, 2: int}>
 */
function forceQueue(?array $entry = null, bool $reset = false): array
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

function leagueFor(string $category): League
{
    return League::factory()->create(['season_id' => test()->season->id, 'category' => $category]);
}

/**
 * Une équipe du club, son noyau posé sur la liste des forces, et sa rencontre.
 *
 * @param  array<int, int>  $forceIndices
 * @return array{0: Team, 1: Interclub, 2: array<int, User>}
 */
function squad(string $name, League $league, array $forceIndices, string $column = 'force_list'): array
{
    $team = Team::factory()->create([
        'season_id' => test()->season->id,
        'league_id' => $league->id,
        'club_id' => test()->ownClub->id,
        'name' => $name,
    ]);

    $players = [];

    foreach ($forceIndices as $index) {
        $player = User::factory()->isCompetitor()->create();
        forceQueue([$player, $column, $index]);
        $players[] = $player;
    }

    $team->users()->attach(collect($players)->pluck('id')->all());

    $fixture = Interclub::factory()->create([
        'season_id' => test()->season->id,
        'league_id' => $league->id,
        'visited_team_id' => $team->id,
        'week_number' => 42,
        'total_players' => $league->category === 'MEN' ? 4 : 3,
        'is_bye' => false,
        'start_date_time' => now()->addDays(7),
    ]);

    return [$team, $fixture, $players];
}

it('reads the threshold from the superior team lineup once it exists', function (): void {
    $men = leagueFor('MEN');
    [, $fixtureA, $playersA] = squad('A', $men, [1, 2, 5, 8, 11, 14, 17]);
    [, $fixtureB] = squad('B', $men, [20, 22, 24, 26]);

    settleForceLists();

    foreach (array_slice($playersA, 0, 4) as $selected) {
        $fixtureA->select($selected);
    }

    $constraint = $this->rule->constraintFor($fixtureB);

    // Alignés : #1, #2, #5, #8 → troisième = #5, et il n'y a plus rien à prédire.
    expect($constraint->strongest)->toBe(5)
        ->and($constraint->weakest)->toBe(5)
        ->and($constraint->rankIsReadable)->toBeTrue()
        ->and($constraint->superiorTeamNames)->toBe(['A']);
});

it('falls back to the squad bounds while the superior team has not composed', function (): void {
    $men = leagueFor('MEN');
    squad('A', $men, [1, 2, 5, 8, 11, 14, 17]);
    [, $fixtureB] = squad('B', $men, [20, 22, 24, 26]);

    settleForceLists();

    $constraint = $this->rule->constraintFor($fixtureB);

    expect($constraint->strongest)->toBe(5)
        ->and($constraint->weakest)->toBe(14);
});

/**
 * Chez les messieurs, seule l'équipe immédiatement supérieure contraint
 * (C.22.1.4). Chez les dames et les catégories d'âge, c'est n'importe laquelle
 * des équipes supérieures (C.22.2.4).
 */
it('constrains a men team by its immediate senior only', function (): void {
    $men = leagueFor('MEN');
    squad('A', $men, [1, 2, 3, 4]);
    squad('B', $men, [10, 11, 12, 13]);
    [, $fixtureC] = squad('C', $men, [30, 31, 32, 33]);

    settleForceLists();

    $constraint = $this->rule->constraintFor($fixtureC);

    expect($constraint->superiorTeamNames)->toBe(['B'])
        ->and($constraint->strongest)->toBe(12);
});

it('constrains a women team by every superior team', function (): void {
    $women = leagueFor('WOMEN');
    squad('A', $women, [1, 2, 3], 'force_list_women');
    squad('B', $women, [10, 11, 12], 'force_list_women');
    [, $fixtureC] = squad('C', $women, [30, 31, 32], 'force_list_women');

    settleForceLists();

    $constraint = $this->rule->constraintFor($fixtureC);

    // Deuxièmes des deux noyaux : #2 et #11 — on garde le plus exigeant.
    expect($constraint->superiorTeamNames)->toBe(['A', 'B'])
        ->and($constraint->strongest)->toBe(11);
});

it('has nothing to say to the strongest team of a category', function (): void {
    $men = leagueFor('MEN');
    [, $fixtureA] = squad('A', $men, [1, 2, 3, 4]);
    squad('B', $men, [10, 11, 12, 13]);

    settleForceLists();

    $constraint = $this->rule->constraintFor($fixtureA);

    expect($constraint->superiorTeamNames)->toBe([])
        ->and($constraint->strongest)->toBeNull()
        ->and($constraint->weakest)->toBeNull();
});

/**
 * Décision 18 : un nom d'équipe qui ne se range pas désactive la règle pour la
 * catégorie, et le dit. Calculer sur un ordre inventé coûterait plus cher que de
 * laisser passer une infraction que personne ne détecte aujourd'hui.
 */
it('disables itself when the team names cannot be ordered', function (): void {
    $men = leagueFor('MEN');
    squad('Équipe première', $men, [1, 2, 3, 4]);
    [, $fixtureB] = squad('B', $men, [10, 11, 12, 13]);

    settleForceLists();

    $constraint = $this->rule->constraintFor($fixtureB);

    expect($constraint->rankIsReadable)->toBeFalse()
        ->and($constraint->strongest)->toBeNull()
        ->and($constraint->weakest)->toBeNull();
});
