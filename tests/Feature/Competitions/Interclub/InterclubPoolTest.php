<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\InterclubPoolService;
use App\Domains\Shared\Enums\InterclubAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Les joueurs libres d'une journée : ceux qui se sont déclarés disponibles pour
 * la rencontre de leur propre équipe, que leur capitaine n'a pas retenus, et que
 * personne n'a engagés ailleurs.
 *
 * Le pool n'est pas stocké : c'est une requête. Le retrait automatique d'un
 * joueur engagé ailleurs n'est donc pas un mécanisme, c'est le prédicat lui-même.
 */
beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
    $this->ownClub = Club::factory()->ownClub()->create();

    $this->pool = app(InterclubPoolService::class);
});

/**
 * Une équipe du club, sa rencontre de la semaine 42, et son effectif.
 *
 * @param  array<int, User>  $players
 */
function teamPlayingWeek(string $name, array $players, ?User $captain = null): array
{
    $team = Team::factory()->create([
        'season_id' => test()->season->id,
        'league_id' => test()->league->id,
        'club_id' => test()->ownClub->id,
        'captain_id' => $captain?->id,
        'name' => $name,
    ]);

    $team->users()->attach(collect($players)->pluck('id')->all());

    $fixture = Interclub::factory()->create([
        'season_id' => test()->season->id,
        'league_id' => test()->league->id,
        'visited_team_id' => $team->id,
        'week_number' => 42,
        'total_players' => 4,
        'is_bye' => false,
        'start_date_time' => now()->addDays(7),
    ]);

    return [$team, $fixture];
}

function declareAvailable(Interclub $fixture, User $player, ?string $note = null): void
{
    $fixture->markAvailability($player, InterclubAvailability::AVAILABLE, $note);
}

/** Retenir des joueurs et publier la composition, comme le fait le capitaine. */
function publishLineup(Interclub $fixture, array $selected): void
{
    foreach ($selected as $player) {
        $fixture->select($player);
        $fixture->users()->updateExistingPivot($player->id, ['selection_confirmed_at' => now()]);
    }
}

it('offers a player their own captain left out once the lineup is published', function (): void {
    $kept = User::factory()->isCompetitor()->create();
    $leftOut = User::factory()->isCompetitor()->create();

    [, $fixtureA] = teamPlayingWeek('A', [$kept, $leftOut]);
    [, $fixtureB] = teamPlayingWeek('B', [User::factory()->isCompetitor()->create()]);

    declareAvailable($fixtureA, $kept);
    declareAvailable($fixtureA, $leftOut);
    publishLineup($fixtureA, [$kept]);

    $candidates = $this->pool->freePlayersFor($fixtureB);

    expect($candidates->pluck('user.id')->all())->toBe([$leftOut->id]);
});

/**
 * Tant que le capitaine n'a pas publié, un joueur non coché n'est pas libre : il
 * est en attente. Le proposer ailleurs lui apprendrait sa non-sélection par la
 * bande.
 */
it('keeps a player sequestered while their captain has not published', function (): void {
    $kept = User::factory()->isCompetitor()->create();
    $leftOut = User::factory()->isCompetitor()->create();

    [, $fixtureA] = teamPlayingWeek('A', [$kept, $leftOut]);
    [, $fixtureB] = teamPlayingWeek('B', [User::factory()->isCompetitor()->create()]);

    declareAvailable($fixtureA, $leftOut);
    $fixtureA->select($kept); // coché, mais jamais confirmé

    expect($this->pool->freePlayersFor($fixtureB))->toBeEmpty();
});

it('drops a player the moment someone lines them up elsewhere', function (): void {
    $leftOut = User::factory()->isCompetitor()->create();

    [, $fixtureA] = teamPlayingWeek('A', [$leftOut, User::factory()->isCompetitor()->create()]);
    [, $fixtureB] = teamPlayingWeek('B', [User::factory()->isCompetitor()->create()]);
    [, $fixtureC] = teamPlayingWeek('C', [User::factory()->isCompetitor()->create()]);

    declareAvailable($fixtureA, $leftOut);
    publishLineup($fixtureA, [$fixtureA->visitedTeam->users->firstWhere('id', '!=', $leftOut->id)]);

    expect($this->pool->freePlayersFor($fixtureB)->pluck('user.id')->all())->toBe([$leftOut->id]);

    // Le capitaine de C le prend : il quitte le pool de B sans que rien ne soit
    // écrit nulle part — c'est le prédicat qui a changé de réponse.
    $fixtureC->select($leftOut);

    expect($this->pool->freePlayersFor($fixtureB))->toBeEmpty();
});

it('never offers a player already ticked on the fixture being composed', function (): void {
    $leftOut = User::factory()->isCompetitor()->create();

    [, $fixtureA] = teamPlayingWeek('A', [$leftOut, User::factory()->isCompetitor()->create()]);
    [, $fixtureB] = teamPlayingWeek('B', [User::factory()->isCompetitor()->create()]);

    declareAvailable($fixtureA, $leftOut);
    publishLineup($fixtureA, [$fixtureA->visitedTeam->users->firstWhere('id', '!=', $leftOut->id)]);

    $fixtureB->select($leftOut);

    expect($this->pool->freePlayersFor($fixtureB))->toBeEmpty();
});

it('offers only the players who answered yes', function (string $answer, int $expected): void {
    $kept = User::factory()->isCompetitor()->create();
    $answering = User::factory()->isCompetitor()->create();

    [, $fixtureA] = teamPlayingWeek('A', [$kept, $answering]);
    [, $fixtureB] = teamPlayingWeek('B', [User::factory()->isCompetitor()->create()]);

    $fixtureA->markAvailability($answering, InterclubAvailability::from($answer));
    publishLineup($fixtureA, [$kept]);

    expect($this->pool->freePlayersFor($fixtureB))->toHaveCount($expected);
})->with([
    'disponible' => ['available', 1],
    'peut-être' => ['maybe', 0],
    'indisponible' => ['unavailable', 0],
]);

it('ignores a player who never answered at all', function (): void {
    $kept = User::factory()->isCompetitor()->create();
    $silent = User::factory()->isCompetitor()->create();

    [, $fixtureA] = teamPlayingWeek('A', [$kept, $silent]);
    [, $fixtureB] = teamPlayingWeek('B', [User::factory()->isCompetitor()->create()]);

    publishLineup($fixtureA, [$kept]);

    expect($this->pool->freePlayersFor($fixtureB))->toBeEmpty();
});

it('carries the note the player left to their own captain', function (): void {
    $kept = User::factory()->isCompetitor()->create();
    $leftOut = User::factory()->isCompetitor()->create();

    [$teamA, $fixtureA] = teamPlayingWeek('A', [$kept, $leftOut]);
    [, $fixtureB] = teamPlayingWeek('B', [User::factory()->isCompetitor()->create()]);

    declareAvailable($fixtureA, $leftOut, 'pas avant 20h');
    publishLineup($fixtureA, [$kept]);

    $candidate = $this->pool->freePlayersFor($fixtureB)->first();

    expect($candidate->availabilityNote)->toBe('pas avant 20h')
        ->and($candidate->originTeam->id)->toBe($teamA->id);
});

/**
 * Le périmètre : même saison, même journée, même catégorie. C'est déjà la règle
 * du double alignement, et c'est la seule qui garde son sens — une disponibilité
 * répond à une date, pas à une saison.
 */
it('does not reach into another match day', function (): void {
    $kept = User::factory()->isCompetitor()->create();
    $leftOut = User::factory()->isCompetitor()->create();

    [, $fixtureA] = teamPlayingWeek('A', [$kept, $leftOut]);
    [, $fixtureB] = teamPlayingWeek('B', [User::factory()->isCompetitor()->create()]);

    declareAvailable($fixtureA, $leftOut);
    publishLineup($fixtureA, [$kept]);

    $fixtureB->update(['week_number' => 43]);

    expect($this->pool->freePlayersFor($fixtureB->fresh()))->toBeEmpty();
});

it('does not reach into another category', function (): void {
    $kept = User::factory()->isCompetitor()->create();
    $leftOut = User::factory()->isCompetitor()->create();

    [, $fixtureA] = teamPlayingWeek('A', [$kept, $leftOut]);
    [, $fixtureB] = teamPlayingWeek('B', [User::factory()->isCompetitor()->create()]);

    declareAvailable($fixtureA, $leftOut);
    publishLineup($fixtureA, [$kept]);

    $ladies = League::factory()->create(['season_id' => $this->season->id, 'category' => 'WOMEN']);
    $fixtureB->update(['league_id' => $ladies->id]);

    expect($this->pool->freePlayersFor($fixtureB->fresh()))->toBeEmpty();
});

/**
 * Le calendrier contient aussi des rencontres entre deux clubs adverses. Aucun
 * des deux camps n'est le nôtre : il n'y a pas d'effectif à proposer, et surtout
 * pas celui d'un autre club.
 */
it('never offers players from a fixture the club does not play in', function (): void {
    [, $fixtureB] = teamPlayingWeek('B', [User::factory()->isCompetitor()->create()]);

    $foreignClub = Club::factory()->create(['is_own_club' => false]);
    $foreignTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $foreignClub->id,
        'name' => 'A',
    ]);
    $stranger = User::factory()->isCompetitor()->create();
    $foreignTeam->users()->attach($stranger->id);

    $foreignFixture = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $foreignTeam->id,
        'visiting_team_id' => $foreignTeam->id,
        'week_number' => 42,
        'total_players' => 4,
        'is_bye' => false,
        'start_date_time' => now()->addDays(7),
    ]);

    $other = User::factory()->isCompetitor()->create();
    $foreignTeam->users()->attach($other->id);
    declareAvailable($foreignFixture, $stranger);
    publishLineup($foreignFixture, [$other]);

    expect($this->pool->freePlayersFor($fixtureB))->toBeEmpty();
});

/**
 * Un pool vide a deux causes opposées : tout le monde joue, ou les autres
 * capitaines n'ont pas encore composé. Dans le premier cas il faut chercher
 * ailleurs tout de suite, dans le second il suffit d'attendre — et de relancer.
 */
it('names the teams still holding players back, and who to call', function (): void {
    $captainA = User::factory()->isCompetitor()->create(['phone_number' => '0470 12 34 56']);
    $waiting = User::factory()->isCompetitor()->create();

    [$teamA, $fixtureA] = teamPlayingWeek('A', [$captainA, $waiting], $captainA);
    [, $fixtureB] = teamPlayingWeek('B', [User::factory()->isCompetitor()->create()]);

    declareAvailable($fixtureA, $waiting);
    declareAvailable($fixtureA, $captainA);
    // Aucune confirmation : l'équipe A tient encore ses deux disponibles.

    $waitingTeams = $this->pool->waitingTeamsFor($fixtureB);

    expect($waitingTeams)->toHaveCount(1)
        ->and($waitingTeams->first()->team->id)->toBe($teamA->id)
        ->and($waitingTeams->first()->availableCount)->toBe(2)
        ->and($waitingTeams->first()->team->captain->phone_number)->toBe('0470 12 34 56');
});

it('stops naming a team once it has published', function (): void {
    $kept = User::factory()->isCompetitor()->create();
    $leftOut = User::factory()->isCompetitor()->create();

    [, $fixtureA] = teamPlayingWeek('A', [$kept, $leftOut]);
    [, $fixtureB] = teamPlayingWeek('B', [User::factory()->isCompetitor()->create()]);

    declareAvailable($fixtureA, $leftOut);
    publishLineup($fixtureA, [$kept]);

    expect($this->pool->waitingTeamsFor($fixtureB))->toBeEmpty();
});

/**
 * Un « peut-être » reste une piste à J-2, mais ce n'est pas un oui : il vit sous
 * le pool, dans sa propre ligne, et jamais mélangé aux joueurs libres.
 */
it('keeps the maybes out of the pool but within reach', function (): void {
    $kept = User::factory()->isCompetitor()->create();
    $maybe = User::factory()->isCompetitor()->create();

    [, $fixtureA] = teamPlayingWeek('A', [$kept, $maybe]);
    [, $fixtureB] = teamPlayingWeek('B', [User::factory()->isCompetitor()->create()]);

    $fixtureA->markAvailability($maybe, InterclubAvailability::MAYBE);
    publishLineup($fixtureA, [$kept]);

    expect($this->pool->freePlayersFor($fixtureB))->toBeEmpty()
        ->and($this->pool->maybePlayersFor($fixtureB)->pluck('user.id')->all())->toBe([$maybe->id]);
});

it('drops a maybe who got lined up elsewhere, like anyone else', function (): void {
    $kept = User::factory()->isCompetitor()->create();
    $maybe = User::factory()->isCompetitor()->create();

    [, $fixtureA] = teamPlayingWeek('A', [$kept, $maybe]);
    [, $fixtureB] = teamPlayingWeek('B', [User::factory()->isCompetitor()->create()]);
    [, $fixtureC] = teamPlayingWeek('C', [User::factory()->isCompetitor()->create()]);

    $fixtureA->markAvailability($maybe, InterclubAvailability::MAYBE);
    publishLineup($fixtureA, [$kept]);
    $fixtureC->select($maybe);

    expect($this->pool->maybePlayersFor($fixtureB))->toBeEmpty();
});

/**
 * L'écran de composition se recharge à chaque case cochée, et il a déjà connu
 * l'épisode des mille requêtes par rendu. Les trois lectures du pool parcourent
 * le même ensemble de rencontres : elles doivent pouvoir le parcourir une fois.
 *
 * Ce comptage est volontairement une borne haute et non une valeur exacte : il
 * doit attraper une régression en N+1, pas se casser parce qu'un eager load a
 * bougé d'une relation.
 */
it('reads the whole pool in a handful of queries', function (): void {
    $kept = User::factory()->isCompetitor()->create();
    $leftOut = User::factory()->isCompetitor()->create();
    $maybe = User::factory()->isCompetitor()->create();

    [, $fixtureA] = teamPlayingWeek('A', [$kept, $leftOut, $maybe]);
    [, $fixtureB] = teamPlayingWeek('B', [User::factory()->isCompetitor()->create()]);
    [, $fixtureC] = teamPlayingWeek('C', [User::factory()->isCompetitor()->create()]);

    declareAvailable($fixtureA, $leftOut);
    $fixtureA->markAvailability($maybe, InterclubAvailability::MAYBE);
    publishLineup($fixtureA, [$kept]);
    declareAvailable($fixtureC, $fixtureC->visitedTeam->users->first());

    DB::enableQueryLog();
    DB::flushQueryLog();

    $pool = $this->pool->poolFor($fixtureB->fresh());

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($pool->freePlayers->pluck('user.id')->all())->toBe([$leftOut->id])
        ->and($pool->maybePlayers->pluck('user.id')->all())->toBe([$maybe->id])
        ->and($pool->waitingTeams->pluck('team.name')->all())->toBe(['C'])
        ->and($queries)->toBeLessThanOrEqual(8);
});
