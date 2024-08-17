<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;
use Tests\Trait\CreateUser;

class ViewAnyUserTest extends TestCase
{
    use CreateUser;

    /**
     * User management
     */
    public function test_unlogged_user_cannot_access_members_index(): void
    {
        $response = $this->get(route('users.index'))
                        ->assertRedirect('/login');
    }

    public function test_logged_user_can_access_members_index(): void
    {
        $user = $this->createFakeUser();

        $response = $this->actingAs($user)
                        ->get(route('users.index'))
                        ->assertOk();
    }

    public function test_member_cannot_see_create_member_and_force_index_buttons(): void
    {
        $user = $this->createFakeUser();

        $response = $this->actingAs($user)
                        ->get(route('users.index'))
                        ->assertDontSee([
                            'Create new user',
                            'Set Force Index',
                            'Delete Force Index'
                        ]);
    }

    public function test_admin_and_comittee_members_can_see_create_member_and_force_index_buttons_from_index(): void
    {
        $admin = $this->createFakeAdmin();
        $comittee_member = $this->createFakeComitteeMember();

        $response = $this->actingAs($admin)
                        ->get(route('users.index'))
                        ->assertSee([
                            'Create new user',
                            'Set Force Index',
                            'Delete Force Index'
                        ]);

        $response = $this->actingAs($comittee_member)
                        ->get(route('users.index'))
                        ->assertSee([
                            'Create new user',
                            'Set Force Index',
                            'Delete Force Index'
                        ]);
    }

    public function test_member_cannot_access_create_member_page(): void   
    {
        $user = $this->createFakeUser();

        $response = $this->actingAs($user)
                        ->get(route('users.create'))
                        ->assertStatus(403);
    }

    public function test_member_cannot_see_edit_and_delete_member_buttons_from_index(): void
    {
        $user = $this->createFakeUser();

        $response = $this->actingAs($user)
                        ->get(route('users.index'))
                        ->assertDontSee([
                            'Edit',
                            'Delete',
                        ])
                        ->assertSee([
                            'Contact'
                        ]);
    }

    public function test_admin_and_comittee_members_can_see_edit_and_delete_member_buttons_from_index(): void
    {
        $user = $this->createFakeAdmin();

        $response = $this->actingAs($user)
                        ->get(route('users.index'))
                        ->assertSee([
                            'Contact',
                            'Edit',
                            'Delete',
                        ]);

        $user = $this->createFakeComitteeMember();

        $response = $this->actingAs($user)
                        ->get(route('users.index'))
                        ->assertSee([
                            'Contact',
                            'Edit',
                            'Delete',
                        ]);
    }

}
