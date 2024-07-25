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

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_member_creation(): void
    {
        $user = User::factory()->create();       
        $user->is_admin = true;
                
        $team = Team::create();

        $password = Hash::make('password');

        $response = $this->actingAs($user)
                        ->post('/admin/members', [
                            'last_name' => 'Dupont',
                            'first_name' => 'Charles',
                            'email' => 'charles.dupont@gmail.com',
                            'email_verified_at' => now(),
                            'password' => $password,
                            'password_confirmation' => $password,
                            'remember_token' => Str::random(10),
                            'licence' => 123456,
                            'ranking' => 'B0',
                            'role_id' => $roleMember->id,
                            'is_competitor' => true,
                            'is_active' => true,
                            'has_debt' => false,
                            'birthday' => Date::create(1988,8,17),
                            'phone_number' => '0479123456',
                            'team_id' => $team->id,
                        ])
                        ->assertRedirect(route('members.create'))
                        ->assertSessionHasNoErrors();
    }

    public function test_member_cannot_create_new_member(): void
    {
        $user = User::factory()->create();
        $password = Hash::make('password');

        $response = $this->actingAs($user)
                        ->post('/admin/members/create', [
                            
                        ])
                        ->assertStatus(405);
        
    }

    public function test_new_member_creation_with_invalid_paramaters_returns_errors_in_the_session(): void
    {
        $user = User::factory()->hasTeam()->create();        
        $user->is_admin = true;

        $password = Hash::make('password');

        $response = $this->actingAs($user)
                        ->from(route('members.create'))
                        ->post('/admin/members', [
                            'last_name' => 'Dupont',
                            'first_name' => 'Charles',
                            'email' => 'charles.dupont@gmail.com',
                            'email_verified_at' => now(),
                            'password' => $password,
                            'password_confirmation' => $password,
                            'remember_token' => Str::random(10),
                            'licence' => 123456,
                            'ranking' => 'B0',
                            'is_admin' => false,
                            'is_competitor' => true,
                            'is_active' => true,
                            'has_debt' => false,
                            'birthday' => Date::create(1988,8,17),
                            'phone_number' => '0479123456',
                            'team_id' => $user->team()->id,
                        ])
                        ->assertSessionHasNoErrors()
                        ->assertRedirect(route('members.create'))
                        ->assertSessionHas('success');

        $user->is_comittee_member = true;
        $user->is_admin = false;

        $respons = $this->actingAs($user)
                        ->post('/admin/members', [
                            'last_name' => '',
                            'first_name' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Ea quibusdam temporibus reprehenderit ipsam sunt? Illo eos doloremque inventore obcaecati repudiandae culpa qui, rem explicabo consectetur numquam suscipit aut voluptatem nostrum! Lorem ipsum dolor sit amet consectetur adipisicing elit. Ea quibusdam temporibus reprehenderit ipsam sunt? Illo eos doloremque inventore obcaecati repudiandae culpa qui, rem explicabo consectetur numquam suscipit aut voluptatem nostrum!',
                            'email' => 'charles.dupont@gmail.com',
                            'email_verified_at' => now(),
                            'password' => '1234',
                            'password_confirmation' => '1235',
                            'remember_token' => Str::random(10),
                            'licence' => 123456,
                            'ranking' => 'B5',
                            'is_competitor' => true,
                            'is_active' => true,
                            'has_debt' => false,
                            'birthday' => Date::create(1988,8,17),
                            'phone_number' => '0479123456',
                            'team_id' => $user->team()->id,
                        ])
                        ->assertInvalid([
                        'email',
                        'licence',
                        ])
                        ->assertRedirect(route('members.create'))
                        ->assertSessionHasErrors([
                            'last_name',
                            'first_name',
                            'email',
                            'licence',
                            'ranking',
                            'password'
                        ]);
    }

    public function test_new_member_creation_adds_member_pool_if_no_team_if_linked(): void
    {

    }

}
