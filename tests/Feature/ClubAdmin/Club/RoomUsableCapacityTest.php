<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\TableStateEnum;
use Livewire\Livewire;

/*
 * A room has two limits: the room it offers for each use, typed in, and the
 * tables that can actually be played on. What it really offers is the
 * smaller of the two — eight tables with room for six in training is six,
 * and six with three of them broken is five.
 */

pest()->group('club', 'rooms');

/**
 * A room of eight tables: room for six in training, four for an official
 * match, six for a tournament.
 *
 * @param  list<TableStateEnum>  $states
 */
function roomWithTables(array $states, array $capacities = []): Room
{
    $room = Room::factory()->create(array_merge([
        'name' => 'Demeester -1',
        'capacity_for_trainings' => 6,
        'capacity_for_interclubs' => 4,
        'capacity_for_tournaments' => 6,
    ], $capacities));

    foreach ($states as $number => $state) {
        Table::factory()->create(['room_id' => $room->id, 'name' => (string) ($number + 1), 'state' => $state]);
    }

    return $room;
}

it('offers the room typed in when the tables are there', function (): void {
    $room = roomWithTables(array_fill(0, 8, TableStateEnum::GOOD));

    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-admin.rooms.show', ['room' => $room])
        ->assertSeeInOrder([__('Training capacity'), '6'])
        ->assertDontSee(__(':usable of :capacity', ['usable' => 6, 'capacity' => 6]));
});

it('offers fewer when tables are out of service, and says why', function (): void {
    $room = roomWithTables([
        TableStateEnum::GOOD, TableStateEnum::GOOD, TableStateEnum::GOOD, TableStateEnum::GOOD,
        TableStateEnum::NEEDS_REPAIR, TableStateEnum::OUT_OF_SERVICE, TableStateEnum::OUT_OF_SERVICE, TableStateEnum::OUT_OF_SERVICE,
    ]);

    // Five playable tables (one needs repair, it still plays): five for training
    // and for a tournament, the four of an official match untouched.
    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-admin.rooms.show', ['room' => $room])
        ->assertSee(__(':usable of :capacity', ['usable' => 5, 'capacity' => 6]))
        ->assertSee(trans_choice(':count table out of service|:count tables out of service', 3, ['count' => 3]))
        ->assertSee(trans_choice(':count needs repair|:count need repair', 1, ['count' => 1]));
});

it('shows each room with what it really offers in the list of rooms', function (): void {
    roomWithTables([TableStateEnum::GOOD, TableStateEnum::GOOD, TableStateEnum::OUT_OF_SERVICE], [
        'capacity_for_trainings' => 3, 'capacity_for_interclubs' => 2, 'capacity_for_tournaments' => 3,
    ]);

    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-admin.rooms.index')
        ->assertSee(__(':usable of :capacity', ['usable' => 2, 'capacity' => 3]));
});

it('stores the room for a tournament, within bounds', function (): void {
    $room = roomWithTables([TableStateEnum::GOOD]);

    $form = Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-admin.rooms.form', ['room' => $room])
        ->assertSet('capacity_for_tournaments', 6)
        ->set('capacity_for_tournaments', 100)
        ->call('save')
        ->assertHasErrors(['capacity_for_tournaments']);

    $form->set('capacity_for_tournaments', 5)->call('save')->assertHasNoErrors();

    expect($room->fresh()->capacity_for_tournaments)->toBe(5);
});

describe('a training pack without a cap of its own', function (): void {
    it('takes two players per table the room offers for a training', function (): void {
        $season = makeActiveSeason();
        $room = roomWithTables(array_fill(0, 5, TableStateEnum::GOOD), ['capacity_for_trainings' => 5, 'capacity_for_interclubs' => 2]);
        $pack = makeTrainingPack($season, ['room_id' => $room->id, 'max_participants' => null]);

        expect($pack->fresh()->effectiveMaxParticipants())->toBe(10);
    });

    it('takes fewer when tables are out of service', function (): void {
        $season = makeActiveSeason();
        $room = roomWithTables([
            TableStateEnum::GOOD, TableStateEnum::GOOD, TableStateEnum::GOOD,
            TableStateEnum::OUT_OF_SERVICE, TableStateEnum::OUT_OF_SERVICE,
        ], ['capacity_for_trainings' => 5, 'capacity_for_interclubs' => 2]);
        $pack = makeTrainingPack($season, ['room_id' => $room->id, 'max_participants' => null]);

        expect($pack->fresh()->effectiveMaxParticipants())->toBe(6);
    });

    it('trusts the room typed in when the room has no table recorded', function (): void {
        $season = makeActiveSeason();
        $room = Room::factory()->create(['capacity_for_trainings' => 5, 'capacity_for_interclubs' => 2]);
        $pack = makeTrainingPack($season, ['room_id' => $room->id, 'max_participants' => null]);

        expect($pack->fresh()->effectiveMaxParticipants())->toBe(10);
    });

    it('shows that number in the pack form', function (): void {
        $room = roomWithTables(array_fill(0, 5, TableStateEnum::GOOD), ['capacity_for_trainings' => 5, 'capacity_for_interclubs' => 2]);

        $form = Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.trainings.form')
            ->set('formRoomId', $room->id);

        expect($form->instance()->inheritedRoomCapacity)->toBe(10);
    });
});
