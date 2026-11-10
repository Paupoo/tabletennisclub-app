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
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/*
 * L'écran de salle : une télé qui dit à chacun où il joue et ce qui suit, avec
 * une poule qui tourne à droite. Il affiche des noms de mineurs : il ne
 * s'ouvre qu'avec le lien secret que le comité copie depuis la fiche.
 */

function hallScreenTournament(): array
{
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PENDING, 'match_type' => 'single']);
    $room = Room::factory()->create(['name' => 'Blocry']);
    $pool = Pool::factory()->for($tournament)->create(['name' => 'A']);
    [$ana, $ben, $cleo, $dan] = User::factory(4)->sequence(
        ['first_name' => 'Ana', 'last_name' => 'Ait'],
        ['first_name' => 'Ben', 'last_name' => 'Bos'],
        ['first_name' => 'Cleo', 'last_name' => 'Cox'],
        ['first_name' => 'Dan', 'last_name' => 'Dua'],
    )->create()->all();
    $pool->users()->attach([$ana->id, $ben->id, $cleo->id, $dan->id]);

    $playing = TournamentMatch::create(['tournament_id' => $tournament->id, 'pool_id' => $pool->id, 'player1_id' => $ana->id, 'player2_id' => $ben->id, 'status' => 'in_progress', 'match_order' => 1]);
    TournamentMatch::create(['tournament_id' => $tournament->id, 'pool_id' => $pool->id, 'player1_id' => $cleo->id, 'player2_id' => $dan->id, 'status' => 'scheduled', 'match_order' => 2]);

    $busy = Table::create(['name' => 'T1', 'state' => TableStateEnum::GOOD, 'purchased_on' => now()->subYear()->toDateString(), 'room_id' => $room->id]);
    $free = Table::create(['name' => 'T2', 'state' => TableStateEnum::GOOD, 'purchased_on' => now()->subYear()->toDateString(), 'room_id' => $room->id]);
    $tournament->tables()->attach($busy->id, ['is_table_free' => false, 'tournament_match_id' => $playing->id]);
    $tournament->tables()->attach($free->id, ['is_table_free' => true]);

    return [$tournament, $playing];
}

it('opens with the secret link, and without being signed in', function (): void {
    [$tournament] = hallScreenTournament();

    $this->get(URL::signedRoute('tournament.screen', $tournament))
        ->assertOk()
        ->assertSee('T1')
        ->assertSee('Ana Ait')
        ->assertSee('Cleo Cox')
        ->assertSee(__('Free'));
});

it('refuses the plain address', function (): void {
    [$tournament] = hallScreenTournament();

    $this->get(route('tournament.screen', $tournament))->assertForbidden();
});

it('shows the pools to rotate through', function (): void {
    [$tournament] = hallScreenTournament();

    $this->get(URL::signedRoute('tournament.screen', $tournament))
        ->assertSee('data-rotating-panel', escape: false)
        ->assertSee(__('Pool :name', ['name' => 'A']));
});

it('lets the committee open the screen from the banner menu', function (): void {
    [$tournament] = hallScreenTournament();

    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
        ->assertSee(__('Open the hall screen'))
        ->assertSee(URL::signedRoute('tournament.screen', $tournament), escape: false);
});
