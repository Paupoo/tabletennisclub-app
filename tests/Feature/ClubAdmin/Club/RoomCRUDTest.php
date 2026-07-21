<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\Shared\Enums\Role;
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
test('admin and committee member can see create or edit buttons', function (): void {
    $this->actingAs($this->createFakeAdmin())
        ->get(route('admin.rooms.index'))
        ->assertSee(__('Create'))
        ->assertSee(__('Modify'))
        ->assertSee(__('Delete'));

    $this->actingAs(tap($this->createFakeCommitteeMember(), fn ($u) => $u->assignRole(Role::FACILITIES->value)))
        ->get(route('admin.rooms.index'))
        ->assertSee(__('Create'))
        ->assertSee(__('Modify'))
        ->assertSee(__('Delete'));
});

test('members cant see create nor edit buttons', function (): void {
    $this->actingAs($this->createFakeUser())
        ->get(route('admin.rooms.index'))
        ->assertDontSee(__('Modify'))
        ->assertDontSee(__('Delete'));
});

test('unlogged users cant access room index', function (): void {
    $this->get(route('admin.rooms.index'))
        ->assertRedirect('/login');

    $this->get(route('admin.rooms.create'))
        ->assertRedirect('/login');

    $this->get(route('admin.rooms.edit', $this->room))
        ->assertRedirect('/login');
});
