<?php

declare(strict_types=1);

namespace Tests\Feature\Team;

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class EditTeamTest extends TestCase
{
    protected Model $admin;

    protected Model $committee_member;

    protected array $less_than_5_players_request = [
        'captain_id' => 5,
        'category' => LeagueCategory::MEN->name,
        'division' => '5E',
        'level' => LeagueLevel::PROVINCIAL_BW->name,
        'name' => 'A',
        'players' => [
            0 => '1',
            1 => '2',
            2 => '3',
        ],
        'season_id' => 1,
    ];

    protected Model $user;

    protected array $valid_request = [
        'captain_id' => 5,
        'category' => LeagueCategory::MEN->name,
        'division' => '5E',
        'level' => LeagueLevel::PROVINCIAL_BW->name,
        'name' => 'A',
        'players' => [
            0 => '1',
            1 => '2',
            2 => '3',
            3 => '4',
            4 => '5',
            5 => '6',
        ],
        'season_id' => 1,
    ];

    protected array $valid_request_2 = [
        'captain_id' => 2,
        'category' => LeagueCategory::MEN->name,
        'division' => '4C',
        'level' => LeagueLevel::PROVINCIAL_BW->name,
        'name' => 'B',
        'players' => [
            0 => '1',
            1 => '2',
            2 => '3',
            3 => '4',
            4 => '5',
            5 => '6',
        ],
        'season_id' => 1,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = User::factory()->create([
            'is_admin' => false,
            'is_comittee_member' => false,
            'is_competitor' => false,
        ]);

        $this->committee_member = User::factory()->create([
            'is_admin' => false,
            'is_comittee_member' => true,
            'is_competitor' => false,
        ]);

        $this->admin = User::factory()->create([
            'is_admin' => true,
            'is_comittee_member' => false,
            'is_competitor' => false,
        ]);
    }

    public function test_admin_and_comittee_members_can_see_edit_button_from_team_show_view(): void
    {
        $this->actingAs($this->admin)
            ->get(route('teams.show', 1))
            ->assertSee('Edit');

        $this->actingAs($this->committee_member)
            ->get(route('teams.show', 1))
            ->assertSee('Edit');
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

    public function test_member_cant_see_edit_button_from_team_show_view(): void
    {
        $this->actingAs($this->member)
            ->get(route('teams.show', 1))
            ->assertDontSee('Edit');
    }

    public function test_team_should_contains_minimum_5_players(): void
    {
        $team = Team::firstwhere('name', 'Z');

        $this->actingAs($this->admin)
            ->from(route('teams.edit', $team))
            ->put(route('teams.update', $team), $this->less_than_5_players_request)
            ->assertInvalid('players')
            ->assertRedirect(route('teams.edit', $team))
            ->assertSessionHasErrorsIn('players');
    }

    public function test_unlogged_user_cant_edit_a_team(): void
    {
        $team = Team::first();

        $response = $this->get(route('teams.edit', $team->id));

        $response->assertRedirect('/login');
    }

    public function test_updates_are_correctly_stored(): void
    {
        $totalTeams = Team::count();

        $team = Team::first();

        $this->assertNotEquals(5, $team->captain_id);

        $total_players = $team->users()->count();

        $this->actingAs($this->admin)
            ->from('teams.edit', $team)
            ->put(route('teams.update', $team), $this->valid_request)
            ->assertValid()
            ->assertRedirect(route('teams.index'))
            ->assertSessionHasNoErrors();
        $storeTeam = Team::first();
        $total_players_final_count = $storeTeam->users()->count();

        $this->assertDatabaseCount('teams', $totalTeams);

        $this->assertEquals(--$total_players, $total_players_final_count);
        $this->assertEquals(5, $storeTeam->captain_id);
    }

    public function test_validation_should_fail_in_case_of_duplicate_teams_into_same_league(): void
    {
        // Create 2 different teams
        $this->actingAs($this->committee_member)
            ->from('teams.create')
            ->post(route('teams.store'), $this->valid_request)
            ->assertRedirectToRoute('teams.index');

        $this->actingAs($this->committee_member)
            ->from('teams.create')
            ->post(route('teams.store'), $this->valid_request_2)
            ->assertRedirectToRoute('teams.index');

        // Create the duplicated team
        $updated_team = Team::find(1);
        $this->actingAs($this->committee_member)
            ->from('teams.edit', $updated_team)
            ->put(route('teams.update', $updated_team), $this->valid_request_2)
            ->assertInvalid('name')
            ->assertRedirect('teams.edit', $updated_team)
            ->assertSessionHasErrors('name');

    }
}
