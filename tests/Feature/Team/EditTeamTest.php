<?php

namespace Tests\Feature;

use App\Models\League;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Monolog\Level;
use Tests\TestCase;

class EditTeamTest extends TestCase
{

    public function test_unlogged_user_cant_edit_a_team(): void
    {
        $team = Team::first();

        $response = $this->get(route('teams.edit', $team->id));

        $response->assertRedirect('/login');
    }

    public function test_member_cant_edit_a_team(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'is_comittee_member' => false,
        ]);
        $team = Team::first();


        $this->actingAs($user)
                ->get(route('teams.edit', $team->id))
                ->assertStatus(403);
    }

    public function test_member_cannot_see_edit_team_button(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'is_comittee_member' => false,
        ]);
        
        $this->actingAs($user)
            ->get(route('teams.index'))
            ->assertDontSee('Edit');

    }

    public function test_member_cannot_see_delete_team_button(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'is_comittee_member' => false,
        ]);
        
        $this->actingAs($user)
            ->get(route('teams.index'))
            ->assertDontSee('Delete');
    }

    public function test_admin_or_comittee_member_can_edit_a_team(): void
    {
        $admin = User::firstWhere('is_admin', true)
                    ->firstWhere('is_comittee_member', false);
        $team = Team::first();
        $response = $this->actingAs($admin)
            ->from('teams.edit', $team->id)
            ->put(route('teams.update', $team->id), [
                'name' => 'T',
            ])
            ->assertInvalid()
            ->assertSessionHasErrors();

        $comittee_member = User::firstWhere('is_admin', false)
                    ->firstWhere('is_comittee_member', true);
                    
        $response = $this->actingAs($comittee_member)
                        ->from('teams.edit', $team->id)
                        ->put(route('teams.update', $team->id), [
                            'name' => 'T',
                        ])
                        ->assertInvalid()
                        ->assertSessionHasErrors();
    }

    public function test_updates_are_correctly_stored(): void
    {
        $totalTeams = Team::count();

        $admin = User::firstWhere('is_admin', true)
        ->firstWhere('is_comittee_member', false);

        $team = Team::first();
        $this->assertNotEquals(5, $team->captain_id);
        
        $total_players = $team->users()->count();

        $this->actingAs($admin)
            ->from('teams.edit', $team->id)
            ->put(route('teams.update', $team->id), [
                'name' => 'T',
                'league_id' => League::firstWhere('division', '4B')->id,
                'players' => [
                    1,
                    2,
                    3,
                    4,
                    5,
                    6,
                ],
                'captain_id' => 5,
            ])
            ->assertValid()
            ->assertRedirect(route('teams.index'))
            ->assertSessionHasNoErrors();
        $storeTeam = Team::first();
        $total_players_final_count = $storeTeam->users()->count();
        
        $this->assertDatabaseCount('teams', $totalTeams);

        $this->assertEquals(--$total_players, $total_players_final_count);
        $this->assertEquals(5, $storeTeam->captain_id);
    }

    public function test_validation_should_fail_in_case_of_duplicate_teams_into_same_season(): void
    {
        // to do
    }
}
