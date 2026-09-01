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
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

pest()->group('Tournament', 'LiveCenter');

const PLAYER_LIVE_COMPONENT = 'pages::club-events.tournaments.live';

function playerTournament(): Tournament
{
    return Tournament::factory()->create([
        'status' => TournamentStatusEnum::PENDING,
        'match_type' => 'single',
        'has_handicap_points' => false,
    ]);
}

function playerRegister(Tournament $tournament, User ...$users): void
{
    $tournament->users()->attach(
        collect($users)->pluck('id')->all(),
        ['registration_status' => 'confirmed'],
    );
}

function playerMatch(Pool $pool, int $p1, int $p2, int $order, string $status = 'scheduled'): TournamentMatch
{
    return TournamentMatch::create([
        'tournament_id' => $pool->tournament_id,
        'pool_id' => $pool->id,
        'player1_id' => $p1,
        'player2_id' => $p2,
        'player1_handicap_points' => 0,
        'player2_handicap_points' => 0,
        'status' => $status,
        'match_order' => $order,
    ]);
}

function playerOccupy(Tournament $tournament, string $name, TournamentMatch $match): void
{
    $table = Table::create([
        'name' => $name,
        'state' => TableStateEnum::GOOD,
        'purchased_on' => now()->subYears(2)->toDateString(),
        'room_id' => Room::factory()->create(['name' => 'Demeester'])->id,
    ]);

    $tournament->tables()->attach($table->id, [
        'is_table_free' => false,
        'tournament_match_id' => $match->id,
        'match_started_at' => now()->subMinutes(9),
    ]);
}

// ── La porte ──────────────────────────────────────────────────────────────────

/*
 * La régie est derrière `can:tournaments.manage`, ce qui ferme la porte à
 * exactement les gens que cette page-ci vise. Elle a donc sa propre route, et
 * sa propre condition : être inscrit à *ce* tournoi n'est pas une permission,
 * c'est une ligne de pivot.
 */
describe('access', function (): void {
    it('opens for somebody registered to this tournament', function (): void {
        $tournament = playerTournament();
        $player = User::factory()->create();
        playerRegister($tournament, $player);

        actingAs($player)
            ->get(route('admin.tournaments.live', $tournament))
            ->assertOk();
    });

    it('is closed to a member who did not sign up', function (): void {
        $tournament = playerTournament();
        playerRegister($tournament, User::factory()->create());

        actingAs(User::factory()->create())
            ->get(route('admin.tournaments.live', $tournament))
            ->assertForbidden();
    });

    /* Le comité y passe pour voir ce que ses joueurs voient. */
    it('opens for the committee without signing up', function (): void {
        $tournament = playerTournament();

        actingAs(User::factory()->isAdmin()->create())
            ->get(route('admin.tournaments.live', $tournament))
            ->assertOk();
    });

    it('is closed to somebody not logged in', function (): void {
        $tournament = playerTournament();

        get(route('admin.tournaments.live', $tournament))->assertRedirect();
    });
});

// ── Combien avant moi ─────────────────────────────────────────────────────────

describe('myNextMatch', function (): void {
    /*
     * Le repère demandé : de quoi décider si on a le temps d'aller boire un
     * verre. Le compte suit l'ordre de la file, c'est-à-dire l'ordre dans
     * lequel le comité lance les matchs.
     */
    it('counts the matches that go before mine', function (): void {
        $tournament = playerTournament();
        $pool = Pool::factory()->for($tournament)->create(['name' => 'A']);
        [$me, $opponent, $a, $b, $c, $d] = User::factory(6)->create()->all();
        playerRegister($tournament, $me, $opponent, $a, $b, $c, $d);

        playerMatch($pool, $a->id, $b->id, 1);
        playerMatch($pool, $c->id, $d->id, 2);
        $mine = playerMatch($pool, $me->id, $opponent->id, 3);

        $next = Livewire::actingAs($me)
            ->test(PLAYER_LIVE_COMPONENT, ['tournament' => $tournament])
            ->get('myNextMatch');

        expect($next['match']->is($mine))->toBeTrue()
            ->and($next['ahead'])->toBe(2);
    });

    it('counts nothing ahead when I am first in the queue', function (): void {
        $tournament = playerTournament();
        $pool = Pool::factory()->for($tournament)->create(['name' => 'A']);
        [$me, $opponent, $a, $b] = User::factory(4)->create()->all();
        playerRegister($tournament, $me, $opponent, $a, $b);

        playerMatch($pool, $me->id, $opponent->id, 1);
        playerMatch($pool, $a->id, $b->id, 2);

        Livewire::actingAs($me)
            ->test(PLAYER_LIVE_COMPONENT, ['tournament' => $tournament])
            ->assertSee(__('You are next. Stay close to the tables.'));
    });

    /* Un match déjà lancé n'est plus dans la file : il est sur une table. */
    it('says nothing is scheduled once my only match is being played', function (): void {
        $tournament = playerTournament();
        $pool = Pool::factory()->for($tournament)->create(['name' => 'A']);
        [$me, $opponent] = User::factory(2)->create()->all();
        playerRegister($tournament, $me, $opponent);

        $mine = playerMatch($pool, $me->id, $opponent->id, 1, 'in_progress');
        playerOccupy($tournament, 'DEMEESTER 5', $mine);

        $component = Livewire::actingAs($me)
            ->test(PLAYER_LIVE_COMPONENT, ['tournament' => $tournament]);

        expect($component->get('myNextMatch'))->toBeNull()
            ->and($component->get('myLiveMatch')['table'])->toBe('DEMEESTER 5');
    });

    it('says nothing is scheduled when I have no match at all', function (): void {
        $tournament = playerTournament();
        $pool = Pool::factory()->for($tournament)->create(['name' => 'A']);
        [$me, $a, $b] = User::factory(3)->create()->all();
        playerRegister($tournament, $me, $a, $b);

        playerMatch($pool, $a->id, $b->id, 1);

        Livewire::actingAs($me)
            ->test(PLAYER_LIVE_COMPONENT, ['tournament' => $tournament])
            ->assertSee(__('No match scheduled for you right now.'));
    });
});

// ── Ce que la page montre ─────────────────────────────────────────────────────

it('shows the count on the page, and marks my line in the queue', function (): void {
    $tournament = playerTournament();
    $pool = Pool::factory()->for($tournament)->create(['name' => 'A']);
    [$me, $opponent, $a, $b, $c, $d] = User::factory(6)->create()->all();
    playerRegister($tournament, $me, $opponent, $a, $b, $c, $d);

    playerMatch($pool, $a->id, $b->id, 1);
    playerMatch($pool, $c->id, $d->id, 2);
    $mine = playerMatch($pool, $me->id, $opponent->id, 3);

    $html = Livewire::actingAs($me)
        ->test(PLAYER_LIVE_COMPONENT, ['tournament' => $tournament])
        ->html();

    expect($html)->toContain(e(trans_choice('{1} match before yours|[2,*] matches before yours', 2)));

    // La ligne de la file qui est la mienne, et elle seule, porte le repère.
    $start = strpos($html, 'wire:key="queue-' . $mine->id . '"');
    $next = strpos($html, 'wire:key="', (int) $start + 10);
    $row = substr($html, (int) $start, $next === false ? null : $next - (int) $start);

    expect($row)->toContain('border-primary');
});

/*
 * La page est ouverte à des gens qui ne peuvent rien gérer, donc elle ne doit
 * rien savoir écrire. Plutôt que d'inventorier ses méthodes, on lui demande
 * celles de la régie : si l'une d'elles répondait un jour ici, un inscrit
 * pourrait lancer un match ou clôturer le tournoi depuis son téléphone.
 */
it('answers to none of the control room actions', function (string $action): void {
    $tournament = playerTournament();
    $player = User::factory()->create();
    playerRegister($tournament, $player);

    Livewire::actingAs($player)
        ->test(PLAYER_LIVE_COMPONENT, ['tournament' => $tournament])
        ->call($action);
})->with([
    'startMatch',
    'generateBracket',
    'saveDraft',
    'closeTournament',
    'freeTable',
])->throws(MethodNotFoundException::class);

// ── Comment on y arrive ───────────────────────────────────────────────────────

/*
 * Une page que personne ne trouve n'existe pas. Deux portes : le calendrier,
 * où le membre cherche son tournoi le jour J, et un bandeau en tête de son
 * espace événements — parce que la liste qui s'y trouve est celle des
 * inscriptions ouvertes, et qu'un tournoi en cours n'en fait plus partie.
 */
it('is linked from the member events page while the tournament is being played', function (): void {
    $tournament = playerTournament();
    $player = User::factory()->create();
    playerRegister($tournament, $player);

    Livewire::actingAs($player)
        ->test('pages::club-admin.users.user-space.event-subscription', ['user' => $player])
        ->assertSee(route('admin.tournaments.live', $tournament), escape: false);
});

it('is not linked before the tournament starts', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);
    $player = User::factory()->create();
    playerRegister($tournament, $player);

    Livewire::actingAs($player)
        ->test('pages::club-admin.users.user-space.event-subscription', ['user' => $player])
        ->assertDontSee(route('admin.tournaments.live', $tournament), escape: false);
});

it('is not linked to somebody who did not sign up', function (): void {
    $tournament = playerTournament();
    playerRegister($tournament, User::factory()->create());
    $stranger = User::factory()->create();

    Livewire::actingAs($stranger)
        ->test('pages::club-admin.users.user-space.event-subscription', ['user' => $stranger])
        ->assertDontSee(route('admin.tournaments.live', $tournament), escape: false);
});

// ── Les onglets partagés avec la régie ────────────────────────────────────────

/*
 * Le joueur et le comité regardent le même tournoi : l'arbre et les classements
 * sont les fichiers de la régie, inclus tels quels. Deux rendus finiraient par
 * ne plus dire la même chose.
 */
it('shows the bracket and the rankings, from the same views as the control room', function (): void {
    $tournament = playerTournament();
    $pool = Pool::factory()->for($tournament)->create(['name' => 'A']);
    [$champion, $finalist] = User::factory(2)->create()->all();
    playerRegister($tournament, $champion, $finalist);
    $pool->users()->attach([$champion->id, $finalist->id]);

    $final = TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'pool_id' => null,
        'round' => 'final',
        'player1_id' => $champion->id,
        'player2_id' => $finalist->id,
        'winner_id' => $champion->id,
        'player1_handicap_points' => 0,
        'player2_handicap_points' => 0,
        'status' => 'completed',
        'match_order' => 1,
    ]);

    $html = Livewire::actingAs($champion)
        ->test(PLAYER_LIVE_COMPONENT, ['tournament' => $tournament])
        ->html();

    // L'arbre rend la carte de la finale, avec la même clé que dans la régie.
    expect($html)->toContain('wire:key="bracket-' . $final->id . '"')
        // Et le classement place le vainqueur.
        ->and($html)->toContain(e(__('Champion')))
        ->and($html)->toContain(e(__('Runner-up')));
});

it('leaves the empty states alone before anything is played', function (): void {
    $tournament = playerTournament();
    $player = User::factory()->create();
    playerRegister($tournament, $player);

    Livewire::actingAs($player)
        ->test(PLAYER_LIVE_COMPONENT, ['tournament' => $tournament])
        ->assertSee(__('Bracket not yet generated.'))
        ->assertSee(__('Rankings will appear as matches are completed.'));
});
