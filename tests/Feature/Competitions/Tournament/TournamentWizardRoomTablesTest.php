<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\TableStateEnum;
use Livewire\Livewire;

/*
 * The wizard plans on what each room really offers a tournament: the room
 * typed in for a tournament, taken down to the tables that can be played
 * on. At the launch it ticks that many tables, the sound ones first.
 */

/**
 * @param  array<string, TableStateEnum>  $tables  name => state
 */
function tournamentRoom(string $name, int $capacity, array $tables): Room
{
    $room = Room::factory()->create(['name' => $name, 'capacity_for_tournaments' => $capacity]);

    foreach ($tables as $tableName => $state) {
        Table::factory()->create(['room_id' => $room->id, 'name' => (string) $tableName, 'state' => $state]);
    }

    return $room;
}

it('names each room with what it offers a tournament, and plans on it', function (): void {
    $room = tournamentRoom('Demeester -1', 6, [
        1 => TableStateEnum::GOOD, 2 => TableStateEnum::GOOD, 3 => TableStateEnum::GOOD,
        4 => TableStateEnum::GOOD, 5 => TableStateEnum::GOOD, 6 => TableStateEnum::OUT_OF_SERVICE,
    ]);

    $wizard = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.wizard')
        ->set('selectedRooms', [$room->id]);

    // Room for six, five tables playable.
    expect(collect($wizard->get('availableRooms'))->firstWhere('id', $room->id)['name'])->toBe('Demeester -1 (5 tables)')
        ->and($wizard->instance()->nbTables)->toBe(5)
        ->and($wizard->get('nb_tables'))->toBe(5);
});

it('ticks as many tables as the room offers, the sound ones first', function (): void {
    $room = tournamentRoom('Demeester -1', 3, [
        1 => TableStateEnum::NEEDS_REPAIR, 2 => TableStateEnum::GOOD, 3 => TableStateEnum::OUT_OF_SERVICE,
        4 => TableStateEnum::GOOD, 5 => TableStateEnum::NEEDS_REPAIR, 6 => TableStateEnum::GOOD,
    ]);

    $wizard = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.wizard')
        ->set('selectedRooms', [$room->id]);

    $ticked = Table::whereIn('id', $wizard->get('launchTableIds'))->orderBy('name')->pluck('name')->all();

    expect($ticked)->toBe(['2', '4', '6'])
        ->and($wizard->get('nb_tables'))->toBe(3);
});

it('falls back on a repair-needing table once the sound ones are taken', function (): void {
    $room = tournamentRoom('Demeester 0', 3, [
        1 => TableStateEnum::NEEDS_REPAIR, 2 => TableStateEnum::GOOD, 3 => TableStateEnum::OUT_OF_SERVICE,
        4 => TableStateEnum::GOOD,
    ]);

    $wizard = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.wizard')
        ->set('selectedRooms', [$room->id]);

    $ticked = Table::whereIn('id', $wizard->get('launchTableIds'))->orderBy('name')->pluck('name')->all();

    expect($ticked)->toBe(['1', '2', '4']);
});
