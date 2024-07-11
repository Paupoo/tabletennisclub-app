<?php

namespace Tests\Feature\User;

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserIndexTest extends TestCase
{
    use RefreshDatabase;
    
    /**
     * User management
     */
    public function test_unlogged_user_cannot_access_members_index(): void
    {
        $response = $this->get('/admin/members')
                        ->assertRedirect('/login');
    }

    public function test_logged_user_can_access_members_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
                        ->get(route('members.index'))
                        ->assertOk();
    }

    public function test_member_cannot_see_create_member_button(): void
    {

    }

    public function test_member_cannot_access_create_member_page(): void   
    {

    }

}
