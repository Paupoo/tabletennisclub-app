<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Club\Room;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

beforeEach(function (): void {
    $this->room = Room::factory()->create();
    $this->room2 = Room::factory()->create();

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
test('add a room adds an entry into the db', function (): void {
    $total_rooms_in_db = Room::count();

    $this->actingAs($this->createFakeAdmin())
        ->from(route('rooms.create'))
        ->post(route('rooms.store', $this->valid_room_request));

    $this->assertDatabaseCount('rooms', $total_rooms_in_db + 1);
});
test('admin and committee member can see create or edit buttons', function (): void {
    $this->actingAs($this->createFakeAdmin())
        ->get(route('admin.rooms.index'))
        ->assertSee(__('Create'))
        ->assertSee(__('Modify'))
        ->assertSee(__('Delete'));

    $this->actingAs($this->createFakeCommitteeMember())
        ->get(route('admin.rooms.index'))
        ->assertSee(__('Create'))
        ->assertSee(__('Modify'))
        ->assertSee(__('Delete'));
});
test('admin or committee member can create a room', function (): void {
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
test('admin or committee member can delete a room', function (): void {
    $this->actingAs($this->createFakeAdmin())
        ->delete(route('rooms.destroy', $this->room))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($this->createFakeCommitteeMember())
        ->delete(route('rooms.destroy', $this->room2))
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});
test('admin or committee member can update a room', function (): void {
    $this->actingAs($this->createFakeAdmin())
        ->from(route('rooms.edit', $this->room))
        ->patch(route('rooms.update', $this->room), $this->valid_room_request)
        ->assertValid()
        ->assertRedirect(route('rooms.index'))
        ->assertSessionHasNoErrors();

    $this->actingAs($this->createFakeCommitteeMember())
        ->from(route('rooms.edit', $this->room2))
        ->patch(route('rooms.update', $this->room2), $this->valid_room_request_2)
        ->assertValid()
        ->assertRedirect(route('rooms.index'))
        ->assertSessionHasNoErrors();
});
test('delete a room removes an entry into the db', function (): void {
    $total_rooms_in_db = Room::count();

    $this->actingAs($this->createFakeAdmin())
        ->delete(route('rooms.destroy', $this->room));

    $this->assertDatabaseCount('rooms', $total_rooms_in_db - 1);
});
test('invalid data are returning error during creation', function (): void {
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
test('member cant create nor edit nor delete rooms', function (): void {
    $this->actingAs($this->createFakeUser())
        ->get(route('rooms.create'))
        ->assertStatus(403);

    $this->actingAs($this->createFakeUser())
        ->post(route('rooms.store', $this->room))
        ->assertStatus(403);

    $this->actingAs($this->createFakeUser())
        ->get(route('rooms.edit', $this->room))
        ->assertStatus(403);

    $this->actingAs($this->createFakeUser())
        ->patch(route('rooms.update', $this->room))
        ->assertStatus(403);

    $this->actingAs($this->createFakeUser())
        ->delete(route('rooms.destroy', $this->room))
        ->assertStatus(403);
});
test('members cant see create nor edit buttons', function (): void {
    $this->actingAs($this->createFakeUser())
        ->get(route('admin.rooms.index'))
        ->assertDontSee(__('Modify'))
        ->assertDontSee(__('Delete'));
});
test('unlogged users cant access room resource', function (): void {
    $this->get(route('rooms.index'))
        ->assertRedirect('/login');

    $this->get(route('rooms.show', $this->room))
        ->assertRedirect('/login');

    $this->get(route('rooms.create'))
        ->assertRedirect('/login');

    $this->get(route('rooms.edit', $this->room))
        ->assertRedirect('/login');

    $this->post(route('rooms.store', $this->room))
        ->assertRedirect('/login');

    $this->patch(route('rooms.update', $this->room))
        ->assertRedirect('/login');

    $this->patch(route('rooms.destroy', $this->room))
        ->assertRedirect('/login');
});
