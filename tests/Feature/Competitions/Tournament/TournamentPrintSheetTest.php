<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Shared\Enums\TournamentStatusEnum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

pest()->group('Tournament');

/*
 * La feuille du mur. Une page à part et non une feuille de style sur la régie :
 * la régie porte une barre latérale, cinq onglets, deux tiroirs et trois
 * modales, tous dans le DOM en même temps, et « tout cacher sauf ça » est une
 * règle que personne ne tient à mesure que la page grossit.
 */
function printSheetTournament(): Tournament
{
    return Tournament::factory()->create([
        'name' => 'Tournoi des crêpes',
        'status' => TournamentStatusEnum::PENDING,
        'match_type' => 'single',
        'start_date' => '2026-09-26 00:00:00',
        'start_time' => '10:00:00',
    ]);
}

it('is reachable by the committee', function (): void {
    actingAs(User::factory()->isAdmin()->create())
        ->get(route('admin.tournaments.print', printSheetTournament()))
        ->assertOk();
});

/*
 * Elle porte l'adresse de la page joueur en clair et en QR : c'est une feuille
 * que le comité imprime, pas une page que les joueurs ouvrent.
 */
it('is closed to a member, entrant or not', function (): void {
    $tournament = printSheetTournament();
    $player = User::factory()->create();
    $tournament->users()->attach($player->id, ['registration_status' => 'confirmed']);

    actingAs($player)
        ->get(route('admin.tournaments.print', $tournament))
        ->assertForbidden();
});

it('is closed to somebody not logged in', function (): void {
    get(route('admin.tournaments.print', printSheetTournament()))->assertRedirect();
});

it('carries the live page as a QR code and as a readable address', function (): void {
    $tournament = printSheetTournament();

    $body = actingAs(User::factory()->isAdmin()->create())
        ->get(route('admin.tournaments.print', $tournament))
        ->getContent();

    expect($body)->toContain('data:image/png;base64,')
        ->and($body)->toContain(route('admin.tournaments.live', $tournament))
        // La date et l'heure du tournoi, prises au bon endroit.
        ->and($body)->toContain('10h00');
});

it('lists every pool, its players and the matches to play', function (): void {
    $tournament = printSheetTournament();
    $poolA = Pool::factory()->for($tournament)->create(['name' => 'Pool A']);
    $poolB = Pool::factory()->for($tournament)->create(['name' => 'Pool B']);

    $players = User::factory(4)->create();
    $poolA->users()->attach($players->take(2)->pluck('id'));
    $poolB->users()->attach($players->skip(2)->pluck('id'));

    TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'pool_id' => $poolA->id,
        'player1_id' => $players[0]->id,
        'player2_id' => $players[1]->id,
        'player1_handicap_points' => 0,
        'player2_handicap_points' => 0,
        'status' => 'scheduled',
        'match_order' => 1,
    ]);

    $body = actingAs(User::factory()->isAdmin()->create())
        ->get(route('admin.tournaments.print', $tournament))
        ->getContent();

    expect($body)->toContain('Pool A')->toContain('Pool B');

    foreach ($players as $player) {
        expect($body)->toContain(e($player->full_name));
    }

    // La rencontre à jouer, avec la case où l'arbitre écrira le score.
    expect(substr_count($body, 'class="score"'))->toBe(1);
});

it('prints the sheet before the draw, with the QR alone', function (): void {
    $tournament = printSheetTournament();

    $body = actingAs(User::factory()->isAdmin()->create())
        ->get(route('admin.tournaments.print', $tournament))
        ->getContent();

    expect($body)->toContain('data:image/png;base64,')
        ->and($body)->toContain(e(__('No pools generated yet.')))
        ->and($body)->not->toContain('class="score"');
});
