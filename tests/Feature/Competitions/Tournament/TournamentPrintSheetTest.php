<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Illuminate\Support\Collection;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

pest()->group('Tournament');

/*
 * Deux papiers, deux usages. L'affiche du tirage part au mur et se lit debout à
 * deux mètres par plusieurs personnes à la fois -- tout ce qu'on y ajoute se
 * paie sur la taille des noms. Les feuilles de match se découpent et vont dans
 * les mains d'une poule, qui doit pouvoir la jouer seule.
 */
function printTournament(int $setsToWin = 3): Tournament
{
    return Tournament::factory()->create([
        'name' => 'Tournoi des crêpes',
        'status' => TournamentStatusEnum::PENDING,
        'match_type' => 'single',
        'sets_to_win' => $setsToWin,
        'start_date' => '2026-09-26 00:00:00',
        'start_time' => '10:00:00',
    ]);
}

/** @return array{0: Pool, 1: Collection<int, User>} */
function printPoolWithMatch(Tournament $tournament, string $name = 'Pool A'): array
{
    $pool = Pool::factory()->for($tournament)->create(['name' => $name]);
    $players = User::factory(2)->create();
    $pool->users()->attach($players->pluck('id'));

    TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'pool_id' => $pool->id,
        'player1_id' => $players[0]->id,
        'player2_id' => $players[1]->id,
        'player1_handicap_points' => 0,
        'player2_handicap_points' => 0,
        'status' => 'scheduled',
        'match_order' => 1,
    ]);

    return [$pool, $players];
}

// ── La porte, la même pour les deux ───────────────────────────────────────────

describe('access', function (): void {
    it('opens for the committee', function (string $route): void {
        actingAs(User::factory()->isAdmin()->create())
            ->get(route($route, printTournament()))
            ->assertOk();
    })->with(['admin.tournaments.print.pools', 'admin.tournaments.print.matches']);

    /* Ce sont des feuilles que le comité imprime, pas des pages que les joueurs ouvrent. */
    it('is closed to an entrant', function (string $route): void {
        $tournament = printTournament();
        $player = User::factory()->create();
        $tournament->users()->attach($player->id, ['registration_status' => 'confirmed']);

        actingAs($player)->get(route($route, $tournament))->assertForbidden();
    })->with(['admin.tournaments.print.pools', 'admin.tournaments.print.matches']);

    it('is closed to somebody not logged in', function (string $route): void {
        get(route($route, printTournament()))->assertRedirect();
    })->with(['admin.tournaments.print.pools', 'admin.tournaments.print.matches']);
});

// ── L'affiche du tirage ───────────────────────────────────────────────────────

describe('the draw poster', function (): void {
    it('carries the club logo, the QR and every pool with its players', function (): void {
        $tournament = printTournament();
        [, $playersA] = printPoolWithMatch($tournament, 'Pool A');
        [, $playersB] = printPoolWithMatch($tournament, 'Pool B');

        $body = actingAs(User::factory()->isAdmin()->create())
            ->get(route('admin.tournaments.print.pools', $tournament))
            ->getContent();

        expect($body)->toContain('logo-club.svg')
            ->and($body)->toContain('data:image/png;base64,')
            ->and($body)->toContain(route('admin.tournaments.live', $tournament))
            ->and($body)->toContain('Pool A')
            ->and($body)->toContain('Pool B')
            ->and($body)->toContain('10h00');

        foreach ($playersA->merge($playersB) as $player) {
            expect($body)->toContain(e($player->full_name));
        }
    });

    /*
     * Et rien d'autre. Une première version y mettait aussi les rencontres à
     * jouer ; sur une affiche lue à deux mètres, chaque ligne en trop se paie
     * sur la taille des noms.
     */
    it('leaves the matches to the other sheet', function (): void {
        $tournament = printTournament();
        printPoolWithMatch($tournament);

        $body = actingAs(User::factory()->isAdmin()->create())
            ->get(route('admin.tournaments.print.pools', $tournament))
            ->getContent();

        expect($body)->not->toContain('class="match"')
            ->and($body)->not->toContain('class="set"');
    });

    it('still prints before the draw, with the QR alone', function (): void {
        $tournament = printTournament();

        $body = actingAs(User::factory()->isAdmin()->create())
            ->get(route('admin.tournaments.print.pools', $tournament))
            ->getContent();

        expect($body)->toContain('data:image/png;base64,')
            ->and($body)->toContain(e(__('No pools generated yet.')));
    });
});

// ── Les feuilles de match ─────────────────────────────────────────────────────

describe('the match sheets', function (): void {
    it('gives each pool a card of its own to cut out', function (): void {
        $tournament = printTournament();
        printPoolWithMatch($tournament, 'Pool A');
        printPoolWithMatch($tournament, 'Pool B');
        printPoolWithMatch($tournament, 'Pool C');

        $body = actingAs(User::factory()->isAdmin()->create())
            ->get(route('admin.tournaments.print.matches', $tournament))
            ->getContent();

        expect(substr_count($body, '<section class="cut">'))->toBe(3);
    });

    /*
     * La poule joue seule : la carte porte la composition en plus des
     * rencontres, sinon il faut retourner lire l'affiche au mur.
     */
    it('repeats the pool composition on the card', function (): void {
        $tournament = printTournament();
        [, $players] = printPoolWithMatch($tournament);

        $body = actingAs(User::factory()->isAdmin()->create())
            ->get(route('admin.tournaments.print.matches', $tournament))
            ->getContent();

        foreach ($players as $player) {
            expect($body)->toContain(e($player->full_name));
        }

        expect($body)->toContain(e(__('Bring this sheet back to the desk once the pool is finished.')));
    });

    /*
     * Une ligne par joueur et une colonne par set : c'est la disposition qui
     * rend l'erreur difficile, on écrit ses points sur SA ligne. Le nombre de
     * colonnes suit le format du tournoi et non une constante.
     */
    it('draws one box per set of the format, on each player row', function (int $setsToWin, int $columns): void {
        $tournament = printTournament($setsToWin);
        printPoolWithMatch($tournament);

        $body = actingAs(User::factory()->isAdmin()->create())
            ->get(route('admin.tournaments.print.matches', $tournament))
            ->getContent();

        // Une rencontre, deux joueurs : deux lignes de cases.
        expect(substr_count($body, 'class="set"'))->toBe($columns * 2)
            // Et la colonne qui tranche, une par joueur.
            ->and(substr_count($body, 'class="won"'))->toBe(2);
    })->with([
        'best of 3' => [2, 3],
        'best of 5' => [3, 5],
        'best of 7' => [4, 7],
    ]);
});
