<?php

namespace Tests\Feature\User;

use App\Models\User;
use Tests\TestCase;
use Tests\Trait\CreateUser;

class ForceIndexTest extends TestCase
{
    use CreateUser;

    /**
     * A basic feature test example.
     */
    public function test_delete_force_indexes_set_all_value_to_null_in_database(): void
    {
        $admin = $this->createFakeAdmin();
        $response = $this->actingAs($admin)
            ->get(route('deleteForceIndex'));

        foreach (User::all() as $user) {
            $this->assertNull($user->force_list);
        }

        $response->assertRedirect(route('users.index'));
    }

    public function test_set_force_index_are_correctly_calculated(): void
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

    public function test_force_index_are_calculated_only_for_competitors(): void
    {
        $admin = $this->createFakeAdmin();
        $response = $this->actingAs($admin)
            ->get(route('setForceIndex'));

        foreach (User::where('is_competitor', true)->get() as $competitor) {
            $this->assertIsInt($competitor->force_list);
        }

        foreach (User::where('is_competitor', false)->get() as $competitor) {
            $this->assertNull($competitor->force_list);
        }

        $response->assertRedirect(route('users.index'));
    }

    public function test_force_index_cant_be_deleted_by_unlogged_users(): void
    {
        $this->get(route('deleteForceIndex'))
            ->assertRedirect(route('login'));
    }

    public function test_force_index_cant_be_deleted_by_members(): void
    {
        $user = $this->createFakeUser();

        $this->actingAs($user)
            ->get(route('deleteForceIndex'))
            ->assertStatus(403);
    }

    public function test_force_index_can_be_deleted_by_admin_or_comittee_member(): void
    {
        $admin = $this->createFakeAdmin();

        $comittee_member = $this->createFakeComitteeMember();

        $this->actingAs($admin)
            ->get(route('deleteForceIndex'))
            ->assertRedirect(route('users.index'));

        $this->actingAs($comittee_member)
            ->get(route('deleteForceIndex'))
            ->assertRedirect(route('users.index'));
    }

    public function test_force_index_cant_be_set_or_updated_by_unlogged_users(): void
    {
        $this->get(route('setForceIndex'))
            ->assertRedirect(route('login'));
    }

    public function test_force_index_cant_be_set_or_updated_by_members(): void
    {
        $user = $this->createFakeUser();

        $this->actingAs($user)
            ->get(route('setForceIndex'))
            ->assertStatus(403);
    }

    public function test_force_index_can_be_set_or_updated_by_admin_or_comittee_member(): void
    {
        $admin = $this->createFakeAdmin();

        $comittee_member = $this->createFakeComitteeMember();

        $this->actingAs($admin)
            ->get(route('setForceIndex'))
            ->assertRedirect(route('users.index'));

        $this->actingAs($comittee_member)
            ->get(route('setForceIndex'))
            ->assertRedirect(route('users.index'));
    }
}
