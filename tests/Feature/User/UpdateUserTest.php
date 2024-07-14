<?php

namespace Tests\Feature\User;

use App\Enums\Roles;
use App\Models\Role;
use App\Models\User;
use Doctrine\Common\Cache\Psr6\InvalidArgument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UpdateUserTest extends TestCase
{

    use RefreshDatabase;

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

    public function test_admin_and_comittee_members_cant_access_edit_member_page(): void   
    {
        $admin = $this->createMemberUser(Roles::ADMIN->value);
        $comitte_member = $this->createMemberUser(Roles::COMITTEE_MEMBER->value);
        $member = $this->createMemberUser(Roles::MEMBER->value);

        $response = $this->actingAs($admin)
                        ->get(route('members.edit', 1))
                        ->assertOK();

        $response = $this->actingAs($comitte_member)
                        ->get(route('members.edit', 1))
                        ->assertOK();
    }

    /**
     * Create a member and associate a role (Member by default)
     *
     * @param string $role
     * @return Model
     */
    private function createMemberUser(string $role = 'Member'): Model
    {
        if(in_array($role, array_column(Roles::cases(), 'value'))) {

        } else {
            throw new InvalidArgument('This a problem');
        }
        
        $user = User::factory()->create();

        $roleMember = Role::create([
            'name' => $role,
            'description' => 'Just a test',
        ]);
        $user->role()->associate($roleMember);

        return $user;
    }

}
