<?php

namespace Tests\Feature\Room;

use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;
use Tests\Trait\CreateUser;

class CRUDTest extends TestCase
{
    use CreateUser;

    protected array $valid_room_request = [];
    protected array $valid_room_request_2 = [];
    protected array $invalid_room_request = [];

    protected function setUp(): void
    {
        parent::setUp();

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
        
    }

    public function test_unlogged_users_cant_access_room_resource(): void
    {
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
    }

    public function test_members_cant_see_create_nor_edit_buttons(): void
    {
        $this->actingAs($this->createFakeUser())
            ->get(route('rooms.index'))
            ->assertDontSee('Create a new room')
            ->assertDontSee('Edit')
            ->assertDontSee('Delete');
    }

    public function test_admin_and_comittee_member_can_see_create_or_edit_buttons(): void
    {
        $this->actingAs($this->createFakeAdmin())
            ->get(route('rooms.index'))
            ->assertSee('Create a new room')
            ->assertSee('Edit')
            ->assertSee('Delete');

        $this->actingAs($this->createFakeComitteeMember())
            ->get(route('rooms.index'))
            ->assertSee('Create a new room')
            ->assertSee('Edit')
            ->assertSee('Delete');
    }

    public function test_member_cant_create_nor_edit_nor_delete_rooms(): void
    {
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
    }

    public function test_admin_or_comittee_member_can_create_a_room():void
    {
        $this->actingAs($this->createFakeAdmin())
            ->from(route('rooms.create'))
            ->post(route('rooms.store', $this->valid_room_request))
            ->assertValid()
            ->assertRedirectToRoute('rooms.index')
            ->assertSessionHasNoErrors();

        $this->actingAs($this->createFakeComitteeMember())
            ->from(route('rooms.create'))
            ->post(route('rooms.store', $this->valid_room_request_2))
            ->assertValid()
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_admin_or_comittee_member_can_update_a_room():void
    {
        $room = Room::find(1);

        $this->actingAs($this->createFakeAdmin())
            ->from(route('rooms.edit', $room))
            ->patch(route('rooms.update', $room), $this->valid_room_request)
            ->assertValid()
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->createFakeComitteeMember())
            ->from(route('rooms.edit', $room))
            ->patch(route('rooms.update', $room), $this->valid_room_request_2)
            ->assertValid()
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_admin_or_comittee_member_can_delete_a_room():void
    {
        $room = Room::find(1);

        $this->actingAs($this->createFakeAdmin())
            ->delete(route('rooms.destroy', $room))
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHasNoErrors();
           
        $room = Room::find(2);
        
        $this->actingAs($this->createFakeComitteeMember())
            ->delete(route('rooms.destroy', $room))
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_add_a_room_adds_an_entry_into_the_db(): void
    {
        $total_rooms_in_db = Room::count();
        
        $this->actingAs($this->createFakeAdmin())
            ->from(route('rooms.create'))
            ->post(route('rooms.store', $this->valid_room_request));

        $this->assertDatabaseCount('rooms', $total_rooms_in_db + 1);
    }

    public function test_delete_a_room_removes_an_entry_into_the_db(): void
    {
        $total_rooms_in_db = Room::count();

        $room = Room::find(1);

        $this->actingAs($this->createFakeAdmin())
            ->delete(route('rooms.destroy', $room));

        $this->assertDatabaseCount('rooms', $total_rooms_in_db - 1);
    }

    public function test_invalid_data_are_returning_error_during_creation(): void
    {
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
    }
}