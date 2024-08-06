<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CreateTeamTest extends TestCase
{
    protected Model $user;
    protected Model $committee_member;
    protected Model $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'is_admin' => false,
            'is_comittee_member' => false,
        ]);
    
        $this->committee_member = User::factory()->create([
            'is_admin' => false,
            'is_comittee_member' => true,
        ]);
        
        $this->admin = User::factory()->create([
            'is_admin' => true,
            'is_comittee_member' => false,
        ]);
    }

    public function test_unlogged_user_cant_create_a_team(): void
    {
        $response = $this->get(route('teams.create'));

        $response->assertRedirect('/login');
    }

    public function test_member_cant_create_a_team(): void
    {
        $this->actingAs($this->user)
            ->get(route('teams.create'))
            ->assertStatus(403);

        $this->actingAs($this->user)
            ->post(route('teams.store'))
            ->assertStatus(403);
    }

    public function test_members_dont_see_create_teams_button(): void
    {
        $this->actingAs($this->user)
            ->get(route('teams.index'))
            ->assertDontSee('Create new team')
            ->assertDontSee('Team Builder');
    }

    public function test_admin_or_comittee_member_can_create_a_team(): void
    {
        $admin = User::firstWhere('is_admin', true)
            ->firstWhere('is_comittee_member', false);
        $response = $this->actingAs($admin)
            ->get(route('teams.create'));

        $response->assertStatus(200);

        $comittee_member = User::firstWhere('is_admin', false)
            ->firstWhere('is_comittee_member', true);

        $response = $this->actingAs($comittee_member)
            ->get(route('teams.create'))
            ->assertStatus(200);

        $response = $this->actingAs($comittee_member)
            ->from('teams.create')
            ->post(route('teams.store'), [
                'name' => 'A',
                'league_id' => 1,
                'players' => [
                    0 => '1',
                    1 => '2',
                    2 => '3',
                    3 => '4',
                    4 => '5',
                ],
                'captain_id' => 1,
            ])
            ->assertRedirectToRoute('teams.index');
    }

    public function test_creation_of_a_team_creates_one_new_entry_in_the_database(): void
    {
        $totalTeams = Team::count();

        Team::factory()->create();

        $this->assertDatabaseCount('teams', ++$totalTeams);
    }

    public function test_validation_should_fail_in_case_of_invalid_parameters(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('teams.create'))
            ->post(route('teams.store'), [
                'name' => 'AA',
                'league_id' => 666,
                'players' => [
                    0 => '666',
                    1 => '667',
                    2 => '668',
                    3 => '1',       // this one is correct.
                ],
                'captain_id' => 666,
            ]);

        $response->assertInvalid([
            'name',
            'league_id',
            'players.0',
            'players.1',
            'players.2',
            'captain_id'
        ]);

        $response->assertRedirect(route('teams.create'))
            ->assertSessionHasErrors([
                'name',
                'league_id',
                'players.0',
                'players.1',
                'players.2',
                'captain_id'
            ]);
    }

    public function test_team_should_contains_minimum_5_players(): void
    {
        $admin = User::firstWhere('is_admin', true)
            ->firstWhere('is_comittee_member', false);

        $this->actingAs($admin)
            ->from('teams.create')
            ->post(route('teams.store'), [
                'name' => 'A',
                'league_id' => 1,
                'players' => [
                    0 => '1',
                    1 => '2',
                    2 => '3',
                    3 => '4',
                ],
                'captain_id' => 1,
            ])
            ->assertInvalid('players');
    }

    public function test_validation_should_fail_in_case_of_duplicate_teams_into_same_season(): void
    {
        // to do
        $comittee_member = User::firstWhere('is_admin', false)
            ->firstWhere('is_comittee_member', true);

        $this->actingAs($comittee_member)
            ->from('teams.create')
            ->post(route('teams.store'), [
                'name' => 'A',
                'league_id' => 1,
                'players' => [
                    0 => '1',
                    1 => '2',
                    2 => '3',
                    3 => '4',
                    4 => '5',
                ],
                'captain_id' => 1,
            ])
            ->assertRedirectToRoute('teams.index');

        $this->actingAs($comittee_member)
            ->from('teams.create')
            ->post(route('teams.store'), [
                'name' => 'A',
                'league_id' => 1,
                'players' => [
                    0 => '1',
                    1 => '2',
                    2 => '3',
                    3 => '4',
                    4 => '5',
                ],
                'captain_id' => 1,
            ])
            ->assertInvalid();
    }
}
