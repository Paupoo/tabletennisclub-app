<?php

declare(strict_types=1);

namespace Tests\Feature\User;

use App\Models\User;
use Tests\TestCase;
use Tests\Trait\CreateUser;

class DeleteUserTest extends TestCase
{
    use CreateUser;

    public function test_admin_and_comittee_member_can_see_delete_button_from_users_index_view(): void
    {
        $admin = $this->createFakeAdmin();
        $comitteeMember = $this->createFakeComitteeMember();

        $response = $this
            ->actingAs($admin)
            ->get(route('users.index'));

        $response->assertSee('Delete');

        $response = $this
            ->actingAs($comitteeMember)
            ->get(route('users.index'));

        $response->assertSee('Delete');
    }

    public function test_admin_and_comittee_member_can_see_delete_button_from_users_show_view(): void
    {
        $admin = $this->createFakeAdmin();
        $comitteeMember = $this->createFakeComitteeMember();

        $response = $this
            ->actingAs($admin)
            ->get(route('users.show', $admin));

        $response->assertSee('Delete');

        $response = $this
            ->actingAs($comitteeMember)
            ->get(route('users.show', $comitteeMember));

        $response->assertSee('Delete');
    }

    /**
     * A basic feature test example.
     */
    public function test_admin_or_comittee_member_delete_a_user_from_users_index_view(): void
    {
        $admin = $this->createFakeAdmin();
        $comitteeMember = $this->createFakeComitteeMember();

        $userToDelete1 = User::factory()->create();
        $userToDelete2 = User::factory()->create();

        $totalUsers = User::count();

        $response = $this
            ->actingAs($admin)
            ->from(route('users.index'))
            ->delete(route('users.destroy', $userToDelete1));

        $response
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $response = $this
            ->actingAs($comitteeMember)
            ->from(route('users.index'))
            ->delete(route('users.destroy', $userToDelete2));

        $response
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('users', $totalUsers - 2);
    }

    public function test_user_cant_delete_any_user(): void
    {
        $user = $this->createFakeUser();
        $userToDelete = User::find(1);

        $response = $this
            ->actingAs($user)
            ->from(route('users.index'))
            ->delete(route('users.destroy', $userToDelete));

        $response
            ->assertStatus(403);
    }

    public function test_user_cant_see_delete_button_from_users_index_view(): void
    {
        $user = $this->createFakeUser();

        $response = $this
            ->actingAs($user)
            ->get(route('users.index'));

        $response->assertDontSee('Delete');
    }

    public function test_user_cant_see_delete_button_from_users_show_view(): void
    {
        $user = $this->createFakeUser();

        $response = $this
            ->actingAs($user)
            ->get(route('users.show', $user));

        $response->assertDontSee('Delete');
    }
}
