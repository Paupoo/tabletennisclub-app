<?php

namespace Tests\Feature\User;

use App\Models\User;
use Tests\TestCase;
use Tests\Trait\CreateUser;

class ForceListTest extends TestCase
{
    use CreateUser;

    /**
     * Post seed expected status : (select id, force_list from `users` ORDER BY force_list ASC, id asc;)
     * 
     * |id |force_list|
     * |---|----------|
     * |10 |          |
     * |11 |          |
     * |12 |          |
     * |13 |          |
     * |14 |          |
     * |17 |          |
     * |18 |          |
     * |9  |1         |
     * |8  |2         |
     * |5  |3         |
     * |4  |6         |
     * |6  |6         |
     * |7  |6         |
     * |1  |7         |
     * |2  |11        |
     * |3  |11        |
     * |15 |11        |
     * |16 |11        |
     */

    public function test_set_force_list_are_correctly_calculated(): void
    {
        /**
         * 1 D4 => 1
         * 1 D6 => 2    (+1)
         * 1 E0 => 3    (+1)
         * 3 E2 => 6    (+3)
         * 1 E4 => 7    (+1)
         * 2 E6 => 11   (+4)
         * 2 NC => 11   (included with E6)
         */

        $checkReferences = [
            'D4' => 1,
            'D6' => 2,
            'E0' => 3,
            'E2' => 6,
            'E4' => 7,
            'E6' => 11,
            'NC' => 11,
        ];

        foreach ($checkReferences as $ranking => $force_list) {
            foreach (User::select('force_list')->where('is_competitor', true)->where('ranking', $ranking)->get() as $user) {
                $this->assertEquals($force_list, $user->force_list);
            }
        }
    }

    public function test_force_list_are_calculated_only_for_competitors(): void
    {
        $admin = $this->createFakeAdmin();
        $response = $this->actingAs($admin)
            ->get(route('setForceList'));

        foreach (User::where('is_competitor', true)->get() as $competitor) {
            $this->assertIsInt($competitor->force_list);
        }

        foreach (User::where('is_competitor', false)->get() as $competitor) {
            $this->assertNull($competitor->force_list);
        }

        $response->assertRedirect(route('users.index'));
    }

    public function test_force_list_cant_be_deleted_by_unlogged_users(): void
    {
        $this->get(route('deleteForceList'))
            ->assertRedirect(route('login'));
    }

    public function test_force_list_cant_be_deleted_by_members(): void
    {
        $user = $this->createFakeUser();

        $this->actingAs($user)
            ->get(route('deleteForceList'))
            ->assertStatus(403);
    }

    public function test_force_list_can_be_deleted_by_admin_or_comittee_member(): void
    {
        $admin = $this->createFakeAdmin();

        $comittee_member = $this->createFakeComitteeMember();

        $this->actingAs($admin)
            ->get(route('deleteForceList'))
            ->assertRedirect(route('users.index'));

        $this->actingAs($comittee_member)
            ->get(route('deleteForceList'))
            ->assertRedirect(route('users.index'));
    }

    public function test_force_list_delete_method_removes_all_force_lists_from_db(): void
    {
        $admin = $this->createFakeAdmin();

        // Check start status
        $totalForceListBeforeDelete = User::whereNotNull('force_list')->count();
        $this->assertEquals(11, $totalForceListBeforeDelete);
        
        // Act: Call the delete method
        $this
            ->actingAs($admin)
            ->get(route('deleteForceList'));
        
        // Check end status
        $totalForceListAfterlete = User::whereNotNull('force_list')->count();
        $this->assertEquals(0, $totalForceListAfterlete);

        $totalNoForceListAfterlete = User::whereNull('force_list')->count();
        $this->assertDatabaseCount('users', $totalNoForceListAfterlete);
    }

    public function test_force_list_cant_be_set_or_updated_by_unlogged_users(): void
    {
        $this->get(route('setForceList'))
            ->assertRedirect(route('login'));
    }

    public function test_force_list_cant_be_set_or_updated_by_members(): void
    {
        $user = $this->createFakeUser();

        $this->actingAs($user)
            ->get(route('setForceList'))
            ->assertStatus(403);
    }

    public function test_force_list_can_be_set_or_updated_by_admin_or_comittee_member(): void
    {
        $admin = $this->createFakeAdmin();

        $comittee_member = $this->createFakeComitteeMember();

        $this->actingAs($admin)
            ->get(route('setForceList'))
            ->assertRedirect(route('users.index'));

        $this->actingAs($comittee_member)
            ->get(route('setForceList'))
            ->assertRedirect(route('users.index'));
    }
}
