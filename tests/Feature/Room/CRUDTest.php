<?php

declare(strict_types=1);
use App\Models\Room;

uses(\Tests\Trait\CreateUser::class);

beforeEach(function () {
    $this->valid_room_request = [
        'name' => 'Jules Demeester 0',
        'street' => 'Rue de l\'invastion 80',
        'city_code' => '1340',
        'city_name' => 'Ottignies',
        'building_name' => 'Centre Sportif Jules Demeester',
        'access_description' => null,
        'capacity_for_trainings' => '5',
        'capacity_for_interclubs' => '2',
    ];

    $this->valid_room_request_2 = [
        'name' => 'Jules Demeester -1',
        'street' => 'Rue de l\'invastion 80',
        'city_code' => '1340',
        'city_name' => 'Ottignies',
        'building_name' => 'Centre Sportif Jules Demeester',
        'access_description' => null,
        'capacity_for_trainings' => '5',
        'capacity_for_interclubs' => '2',
    ];

    $this->invalid_room_request = [
        'name' => null,
        'street' => null,
        'city_code' => 'Hello World !',
        'city_name' => null,
        'building_name' => null,
        'access_description' => null,
        'capacity_for_trainings' => 'Hello Again !',
        'capacity_for_interclubs' => null,
    ];
});
test('add a room adds an entry into the db', function () {
    $total_rooms_in_db = Room::count();

    $this->actingAs($this->createFakeAdmin())
        ->from(route('rooms.create'))
        ->post(route('rooms.store', $this->valid_room_request));

    $this->assertDatabaseCount('rooms', $total_rooms_in_db + 1);
});
test('admin and committee member can see create or edit buttons', function () {
    $this->actingAs($this->createFakeAdmin())
        ->get(route('rooms.index'))
        ->assertSee('Create a new room')
        ->assertSee('Edit')
        ->assertSee('Delete');

    $this->actingAs($this->createFakeCommitteeMember())
        ->get(route('rooms.index'))
        ->assertSee('Create a new room')
        ->assertSee('Edit')
        ->assertSee('Delete');
});
test('admin or committee member can create a room', function () {
    $this->actingAs($this->createFakeAdmin())
        ->from(route('rooms.create'))
        ->post(route('rooms.store', $this->valid_room_request))
        ->assertValid()
        ->assertRedirectToRoute('rooms.index')
        ->assertSessionHasNoErrors();

    $this->actingAs($this->createFakeCommitteeMember())
        ->from(route('rooms.create'))
        ->post(route('rooms.store', $this->valid_room_request_2))
        ->assertValid()
        ->assertRedirect(route('rooms.index'))
        ->assertSessionHasNoErrors();
});
test('admin or committee member can delete a room', function () {
    $room = Room::find(1);

    $this->actingAs($this->createFakeAdmin())
        ->delete(route('rooms.destroy', $room))
        ->assertRedirect(route('rooms.index'))
        ->assertSessionHasNoErrors();

    $room = Room::find(2);

    $this->actingAs($this->createFakeCommitteeMember())
        ->delete(route('rooms.destroy', $room))
        ->assertRedirect(route('rooms.index'))
        ->assertSessionHasNoErrors();
});
test('admin or committee member can update a room', function () {
    $room = Room::find(1);

    $this->actingAs($this->createFakeAdmin())
        ->from(route('rooms.edit', $room))
        ->patch(route('rooms.update', $room), $this->valid_room_request)
        ->assertValid()
        ->assertRedirect(route('rooms.index'))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->createFakeCommitteeMember())
        ->from(route('rooms.edit', $room))
        ->patch(route('rooms.update', $room), $this->valid_room_request_2)
        ->assertValid()
        ->assertRedirect(route('rooms.index'))
        ->assertSessionHasNoErrors();
});
test('delete a room removes an entry into the db', function () {
    $total_rooms_in_db = Room::count();

    $room = Room::find(1);

    $this->actingAs($this->createFakeAdmin())
        ->delete(route('rooms.destroy', $room));

    $this->assertDatabaseCount('rooms', $total_rooms_in_db - 1);
});
test('invalid data are returning error during creation', function () {
    $this->actingAs($this->createFakeAdmin())
        ->from(route('rooms.create'))
        ->post(route('rooms.store', $this->invalid_room_request))
        ->assertInvalid([
            'name',
            'street',
            'city_code',
            'city_name',
            'capacity_for_trainings',
            'capacity_for_interclubs',
        ])
        ->assertRedirectToRoute('rooms.create')
        ->assertSessionHasErrors([
            'name',
            'street',
            'city_code',
            'city_name',
            'capacity_for_trainings',
            'capacity_for_interclubs',
        ]);
});
test('member cant create nor edit nor delete rooms', function () {
    $room = Room::find(1);
    $this->actingAs($this->createFakeUser())
        ->get(route('rooms.create'))
        ->assertStatus(403);

    $this->actingAs($this->createFakeUser())
        ->post(route('rooms.store', $room))
        ->assertStatus(403);

    $this->actingAs($this->createFakeUser())
        ->get(route('rooms.edit', $room))
        ->assertStatus(403);

    $this->actingAs($this->createFakeUser())
        ->patch(route('rooms.update', $room))
        ->assertStatus(403);

    $this->actingAs($this->createFakeUser())
        ->delete(route('rooms.destroy', $room))
        ->assertStatus(403);
});
test('members cant see create nor edit buttons', function () {
    $this->actingAs($this->createFakeUser())
        ->get(route('rooms.index'))
        ->assertDontSee('Create a new room')
        ->assertDontSee('Edit')
        ->assertDontSee('Delete');
});
test('unlogged users cant access room resource', function () {
    $room = Room::find(1);

    $this->get(route('rooms.index'))
        ->assertRedirect('/login');

    $this->get(route('rooms.show', $room))
        ->assertRedirect('/login');

    $this->get(route('rooms.create'))
        ->assertRedirect('/login');

    $this->get(route('rooms.edit', $room))
        ->assertRedirect('/login');

    $this->post(route('rooms.store', $room))
        ->assertRedirect('/login');

    $this->patch(route('rooms.update', $room))
        ->assertRedirect('/login');

    $this->patch(route('rooms.destroy', $room))
        ->assertRedirect('/login');
});
