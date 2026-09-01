<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Shared\Enums\TableStateEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Livewire\Livewire;

pest()->group('Tournament', 'LiveCenter');

/*
 * Le classement des poules est la page qu'un joueur ouvre sur son téléphone
 * pendant le tournoi. Elle disait qui menait la poule et jamais où quelque
 * chose se jouait : l'information vivait dans l'onglet « Tables », trié par
 * salle, c'est-à-dire à l'endroit où on ne cherche pas un nom.
 */
function poolsLiveTournament(): Tournament
{
    return Tournament::factory()->create([
        'status' => TournamentStatusEnum::PENDING,
        'match_type' => 'single',
        'has_handicap_points' => false,
    ]);
}

function poolsLiveTable(Tournament $tournament, string $name, string $room, ?TournamentMatch $match = null): Table
{
    $table = Table::create([
        'name' => $name,
        'state' => TableStateEnum::GOOD,
        'purchased_on' => now()->subYears(2)->toDateString(),
        'room_id' => Room::factory()->create(['name' => $room])->id,
    ]);

    $tournament->tables()->attach($table->id, [
        'is_table_free' => $match === null,
        'tournament_match_id' => $match?->id,
        'match_started_at' => $match === null ? null : now()->subMinutes(12),
    ]);

    return $table;
}

function poolsLiveMatch(Pool $pool, int $p1, int $p2, string $status): TournamentMatch
{
    return TournamentMatch::create([
        'tournament_id' => $pool->tournament_id,
        'pool_id' => $pool->id,
        'player1_id' => $p1,
        'player2_id' => $p2,
        'player1_handicap_points' => 0,
        'player2_handicap_points' => 0,
        'status' => $status,
        'match_order' => 1,
    ]);
}

it('says which table each player is on', function (): void {
    $tournament = poolsLiveTournament();
    $pool = Pool::factory()->for($tournament)->create(['name' => 'A']);
    [$playing, $opponent, $waiting] = User::factory(3)->create()->all();
    $tournament->users()->attach(
        [$playing->id, $opponent->id, $waiting->id],
        ['registration_status' => 'confirmed'],
    );

    $live = poolsLiveMatch($pool, $playing->id, $opponent->id, 'in_progress');
    poolsLiveTable($tournament, 'DEMEESTER 5', 'Demeester', $live);

    $placements = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
        ->get('livePlacements');

    expect($placements)->toHaveKeys([$playing->id, $opponent->id])
        ->and($placements[$playing->id]['table'])->toBe('DEMEESTER 5')
        ->and($placements[$playing->id]['room'])->toBe('Demeester')
        // Personne d'autre n'est en piste : une pastille de trop envoie
        // quelqu'un à une table où il n'a rien à faire.
        ->and($placements)->not->toHaveKey($waiting->id);
});

it('leaves a free table out of the live placements', function (): void {
    $tournament = poolsLiveTournament();
    $pool = Pool::factory()->for($tournament)->create(['name' => 'A']);
    [$a, $b] = User::factory(2)->create()->all();

    $scheduled = poolsLiveMatch($pool, $a->id, $b->id, 'scheduled');
    poolsLiveTable($tournament, 'DEMEESTER 1', 'Demeester');
    // La table garde la trace du dernier match sans être occupée.
    $tournament->tables()->updateExistingPivot(
        Table::where('name', 'DEMEESTER 1')->value('id'),
        ['tournament_match_id' => $scheduled->id, 'is_table_free' => true],
    );

    $placements = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
        ->get('livePlacements');

    expect($placements)->toBe([]);
});

/*
 * Le bandeau par poule a été retiré : le classement reste un classement, et
 * seul le numéro de table à côté du nom en sort. Ce qui doit rester vrai, c'est
 * que jouer dans une poule ne place personne dans une autre.
 */
it('places only the players of the match in progress, whatever their pool', function (): void {
    $tournament = poolsLiveTournament();
    $poolA = Pool::factory()->for($tournament)->create(['name' => 'A']);
    $poolB = Pool::factory()->for($tournament)->create(['name' => 'B']);
    [$a, $b, $c, $d] = User::factory(4)->create()->all();

    $live = poolsLiveMatch($poolA, $a->id, $b->id, 'in_progress');
    poolsLiveMatch($poolB, $c->id, $d->id, 'scheduled');
    poolsLiveTable($tournament, 'DEMEESTER 5', 'Demeester', $live);

    $placements = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
        ->get('livePlacements');

    expect(array_keys($placements))->toEqualCanonicalizing([$a->id, $b->id]);
});

/*
 * assertSee('DEMEESTER 5') passerait sans rien afficher ici : l'onglet Tables
 * est rendu dans la même page et porte déjà ce nom. On vise donc la ligne du
 * joueur par sa clé, qui n'existe que dans cet onglet.
 */
it('puts the table next to the name of whoever is playing, and nobody else', function (): void {
    $tournament = poolsLiveTournament();
    $pool = Pool::factory()->for($tournament)->create(['name' => 'A']);
    [$a, $b, $away] = User::factory(3)->create()->all();
    $tournament->users()->attach([$a->id, $b->id, $away->id], ['registration_status' => 'confirmed']);
    $pool->users()->attach([$a->id, $b->id, $away->id]);

    $live = poolsLiveMatch($pool, $a->id, $b->id, 'in_progress');
    poolsLiveTable($tournament, 'DEMEESTER 5', 'Demeester', $live);

    $html = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
        ->set('activeTab', 'pools')
        ->html();

    $label = e(__('Table :name', ['name' => 'DEMEESTER 5']));

    expect(poolsLiveFragment($html, "pool-{$pool->id}-player-{$a->id}"))->toContain($label)
        ->and(poolsLiveFragment($html, "pool-{$pool->id}-player-{$b->id}"))->toContain($label)
        ->and(poolsLiveFragment($html, "pool-{$pool->id}-player-{$away->id}"))->not->toContain($label);
});

/** Le fragment de HTML qui va d'une clé Livewire jusqu'à la suivante. */
function poolsLiveFragment(string $html, string $key): string
{
    $start = strpos($html, 'wire:key="' . $key . '"');

    if ($start === false) {
        return '';
    }

    $next = strpos($html, 'wire:key="', $start + 10);

    return $next === false ? substr($html, $start) : substr($html, $start, $next - $start);
}
