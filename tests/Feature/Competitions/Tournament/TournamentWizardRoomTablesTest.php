<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\TableStateEnum;
use Livewire\Livewire;

/*
 * The wizard counts the tables a room really holds, as the rooms screen
 * sets them up. It used to read a stored counter nothing kept up to date:
 * a hall reorganised in the rooms screen kept its old number in the wizard,
 * and the simulation planned on tables that were not there.
 */

it('counts the playable tables a room holds, not a stored counter', function (): void {
    $room = Room::factory()->create(['name' => 'Demeester -1', 'total_playable_tables' => 9]);
    Table::factory()->count(5)->create(['room_id' => $room->id]);
    Table::factory()->create(['room_id' => $room->id, 'state' => TableStateEnum::OUT_OF_SERVICE]);

    $wizard = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-events.tournaments.wizard')
        ->set('selectedRooms', [$room->id]);

    expect(collect($wizard->get('availableRooms'))->firstWhere('id', $room->id)['name'])->toBe('Demeester -1 (5 tables)')
        ->and($wizard->instance()->nbTables)->toBe(5)
        ->and($wizard->get('nb_tables'))->toBe(5);
});
