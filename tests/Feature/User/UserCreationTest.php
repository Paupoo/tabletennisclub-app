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

class UserCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_member_creation(): void
    {
        $user = User::factory()->create();
        $roleMember = Role::create([
            'name' => 'Member',
            'description' => 'Just a test',
        ]);
        $roleAdmin = Role::create([
            'name' => 'Admin',
            'description' => 'Just a test',
        ]);

        $roleAdmin->id = 2;
        
        $user->role()->associate($roleAdmin);
        
        $team = Team::create([
            'name' => 'pool',
            'season' => '',
            'division' => '',
        ]);
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
        $role = Role::create([
            'name' => 'Member',
            'description' => 'Just a test',
        ]);
        $user->role()->associate($role);
        $password = Hash::make('password');

        $response = $this->actingAs($user)
                        ->post('/admin/members/create', [
                            
                        ])
                        ->assertStatus(405);
        
    }

    public function test_new_member_creation_with_email_and_licence_already_existing_returns_an_error(): void
    {
        $user = User::factory()->create();
        $roleMember = Role::create([
            'name' => 'Member',
            'description' => 'Just a test',
        ]);
        $roleAdmin = Role::create([
            'name' => 'Admin',
            'description' => 'Just a test',
        ]);

        $roleAdmin->id = 2;
        
        $user->role()->associate($roleAdmin);
        
        $team = Team::create([
            'name' => 'pool',
            'season' => '',
            'division' => '',
        ]);
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
                            'role_id' => $roleMember->id,
                            'is_competitor' => true,
                            'is_active' => true,
                            'has_debt' => false,
                            'birthday' => Date::create(1988,8,17),
                            'phone_number' => '0479123456',
                            'team_id' => $team->id,
                        ])
                        ->assertSessionHasNoErrors()
                        ->assertRedirect(route('members.create'))
                        ->assertSessionHas('success');
        $respons = $this->actingAs($user)
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
                        ->assertInvalid([
                        'email',
                        'licence',
                        ])
                        ->assertRedirect(route('members.create'))
                        ->assertSessionHasErrors('email','licence'  );
    }

    public function test_new_member_creation_with_unexisting_ranking(): void
    {

    }

    public function test_new_member_creation_with_strong_password(): void
    {

    }

    public function test_new_member_creation_adds_member_pool_if_no_team_if_linked(): void
    {

    }

}
