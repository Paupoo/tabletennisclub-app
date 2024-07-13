<?php

namespace Tests\Feature\User;

use App\Enums\Roles;
use App\Models\Role;
use App\Models\User;
use Doctrine\Common\Cache\Psr6\InvalidArgument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserIndexTest extends TestCase
{
    use RefreshDatabase;
    
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

    public function test_admin_and_comittee_members_can_see_create_member_and_force_index_buttons(): void
    {
        $admin = $this->createMemberUser(Roles::ADMIN->value);
        $comittee_member = $this->createMemberUser(Roles::COMITTEE_MEMBER->value);

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
