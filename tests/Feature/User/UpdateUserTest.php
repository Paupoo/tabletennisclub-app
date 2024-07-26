<?php

namespace Tests\Feature\User;

use App\Enums\Roles;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Doctrine\Common\Cache\Psr6\InvalidArgument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UpdateUserTest extends TestCase
{

    public function test_unlogged_user_cannot_access_members_edit(): void
    {
        $response = $this->get(route('members.edit', 1))
                        ->assertRedirect('/login');
    }

    public function test_member_cannot_access_edit_member_page(): void   
    {
        $member = $this->createMemberUser();

        $response = $this->actingAs($member)
                        ->get(route('members.edit', 1))
                        ->assertStatus(403);
    }

    public function test_admin_and_comittee_members_can_access_edit_member_page(): void   
    {
        $admin = $this->createMemberUser('is_admin');
        $comittee_member = $this->createMemberUser('is_comittee_member');
        $member = $this->createMemberUser();

        $response = $this->actingAs($admin)
                        ->get(route('members.edit', $member->id))
                        ->assertOK();

        $response = $this->actingAs($comittee_member)
                        ->get(route('members.edit', $member->id))
                        ->assertOK();
    }

    public function test_members_cant_access_edit_member_page(): void   
    {
        
        $member = $this->createMemberUser();

        $response = $this->actingAs($member)
                        ->get(route('members.edit', $member->id))
                        ->assertStatus(403);

        $response = $this->actingAs($member)
                        ->get(route('members.edit', $member->id))
                        ->assertStatus(403);
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
