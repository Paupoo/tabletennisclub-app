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

/*
 * Le jour J, l'organisateur répète un seul geste : une table se libère, quel
 * match lancer dessus ? L'état de la salle vivait dans l'onglet « Tables » et
 * la file dans « À venir » -- il fallait naviguer pour voir les deux moitiés
 * d'un même geste. La régie les réunit.
 */

function controlRoomTournament(): Tournament
{
    $tournament = Tournament::factory()->create([
        'status' => TournamentStatusEnum::PENDING,
        'match_type' => 'single',
        'has_handicap_points' => false,
    ]);

    $room = Room::factory()->create(['name' => 'Hall 2']);
    $tournament->tables()->attach(
        Table::create([
            'name' => 'T1',
            'state' => TableStateEnum::GOOD,
            'purchased_on' => now()->subYears(2)->toDateString(),
            'room_id' => $room->id,
        ])->id,
        ['is_table_free' => true],
    );

    [$a, $b] = User::factory(2)->create()->all();
    TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'pool_id' => Pool::factory()->for($tournament)->create(['name' => 'A'])->id,
        'player1_id' => $a->id,
        'player2_id' => $b->id,
        'status' => 'scheduled',
        'match_order' => 1,
    ]);

    return $tournament;
}

it('shows the room and the queue on the same screen', function (): void {
    $tournament = controlRoomTournament();

    $html = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
        ->html();

    // La salle…
    expect($html)->toContain('Hall 2')
        // …et la file, dans la même vue, sans changer d'onglet. `e()` parce que
        // Blade échappe l'apostrophe de « File d'attente ».
        ->and($html)->toContain(e(__('Match queue')))
        // …côte à côte : la colonne de droite suit le défilement.
        ->and($html)->toContain('lg:sticky');
});

it('drops the tab that used to hold the queue', function (): void {
    $tournament = controlRoomTournament();

    $html = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
        ->html();

    expect($html)->not->toContain('name="upcoming"')
        ->and(substr_count($html, 'x-mary-tab'))->toBe(0);
});

it('opens on the control room for the committee', function (): void {
    $tournament = controlRoomTournament();

    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
        ->assertSet('activeTab', 'control-room');
});

it('opens on the pools for somebody who cannot manage the tournament', function (): void {
    $tournament = controlRoomTournament();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
        ->assertSet('activeTab', 'pools');
});

it('lets the room breathe past 1152px', function (): void {
    $tournament = controlRoomTournament();

    $html = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
        ->html();

    // `max-w-6xl` laissait 368 px vides à droite sur un 1920 : c'est
    // exactement la place dont la colonne de file avait besoin.
    expect($html)->not->toContain('max-w-6xl');
});

it('shows free tables two per row on a phone', function (): void {
    $tournament = controlRoomTournament();

    $html = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
        ->html();

    // Une colonne montrait deux tables sur douze sur un écran de 375 px.
    expect($html)->toContain('grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-4');
});
