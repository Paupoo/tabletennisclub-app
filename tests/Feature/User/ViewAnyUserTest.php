<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class ViewAnyUserTest extends TestCase
{
    
    /**
     * User management
     */
    public function test_unlogged_user_cannot_access_members_index(): void
    {
        $response = $this->get(route('members.index'))
                        ->assertRedirect('/login');
    }

    public function test_logged_user_can_access_members_index(): void
    {
        $user = $this->createMemberUser();

        $response = $this->actingAs($user)
                        ->get(route('members.index'))
                        ->assertOk();
    }

    public function test_member_cannot_see_create_member_and_force_index_buttons(): void
    {
        $member = $this->createMemberUser();

        $response = $this->actingAs($member)
                        ->get(route('members.index'))
                        ->assertDontSee([
                            'Create new user',
                            'Set Force Index',
                            'Delete Force Index'
                        ]);
    }

    public function test_admin_and_comittee_members_can_see_create_member_and_force_index_buttons_from_index(): void
    {
        $admin = $this->createMemberUser('is_admin');
        $comittee_member = $this->createMemberUser('is_comittee_member');

        $response = $this->actingAs($admin)
                        ->get(route('members.index'))
                        ->assertSee([
                            'Create new user',
                            'Set Force Index',
                            'Delete Force Index'
                        ]);

        $response = $this->actingAs($comittee_member)
                        ->get(route('members.index'))
                        ->assertSee([
                            'Create new user',
                            'Set Force Index',
                            'Delete Force Index'
                        ]);
    }

    public function test_member_cannot_access_create_member_page(): void   
    {
        $member = $this->createMemberUser();

        $response = $this->actingAs($member)
                        ->get(route('members.create'))
                        ->assertStatus(403);
    }

    public function test_member_cannot_see_edit_and_delete_member_buttons_from_index(): void
    {
        $member = $this->createMemberUser();

        $response = $this->actingAs($member)
                        ->get(route('members.index'))
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
        $member = $this->createMemberUser('is_admin');

        $response = $this->actingAs($member)
                        ->get(route('members.index'))
                        ->assertSee([
                            'Contact',
                            'Edit',
                            'Delete',
                        ]);

        $member = $this->createMemberUser('is_comittee_member');

        $response = $this->actingAs($member)
                        ->get(route('members.index'))
                        ->assertSee([
                            'Contact',
                            'Edit',
                            'Delete',
                        ]);
    }

    /**
     * Create a member and associate a role (Member by default)
     *
     * @param string $role
     * @return Model
     */
    private function createMemberUser(string $role = ''): Model
    {
        $user = User::factory()->create();
        
        if ($role === 'is_admin') {
            $user->is_admin = true;
        } elseif  ($role === 'is_comittee_member') {
            $user->is_comittee_member = true;
        }

        return $user;
    }

}
