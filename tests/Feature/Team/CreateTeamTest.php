<?php

namespace Tests\Feature\Team;

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class CreateTeamTest extends TestCase
{
    protected Model $user;
    protected Model $committee_member;
    protected Model $admin;
    protected array $valid_request = [
        'captain_id' => 1,
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
        ],
        'season_id' => 1,
    ];
    protected array $invalid_request = [
        'captain_id' => 666,
        'category' => 'somethingWrong',
        'division' => null,
        'level' => 'somethingWrong',
        'name' => 'AA',
        'players' => [
            0 => '666',
            1 => '667',
            2 => '668',
            3 => '1',       // this one is correct.
        ],
        'season_id' => 99,
    ];
    protected array $less_than_5_players_request = [
        'captain_id' => 1,
        'category' => LeagueCategory::MEN->name,
        'division' => '5E',
        'level' => LeagueLevel::PROVINCIAL_BW->name,
        'name' => 'A',
        'players' => [
            0 => '1',
            1 => '2',
            2 => '3',
            3 => '4',
        ],
        'season_id' => 1,
    ];  

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
            ->post(route('teams.store'), $this->valid_request)
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
        $this->actingAs($this->admin)
            ->from(route('teams.create'))
            ->post(route('teams.store'), $this->invalid_request)
            ->assertInvalid([
                'captain_id',
                'category',
                'division',
                'level',
                'name',
                'players.0',
                'players.1',
                'players.2',
                'season_id',
            ])
            ->assertRedirect(route('teams.create'))
            ->assertSessionHasErrors([
                'captain_id',
                'category',
                'division',
                'level',
                'name',
                'players.0',
                'players.1',
                'players.2',
                'season_id',
            ]);
    }

    public function test_team_should_contains_minimum_5_players(): void
    {
        $this->actingAs($this->admin)
            ->from('teams.create')
            ->post(route('teams.store'), $this->less_than_5_players_request)
            ->assertInvalid(['players'])
            ->assertSessionHasErrors(['players']);
    }

    public function test_validation_should_fail_in_case_of_duplicate_teams_into_same_league(): void
    {
        $this->actingAs($this->committee_member)
            ->from('teams.create')
            ->post(route('teams.store'), $this->valid_request)
            ->assertRedirectToRoute('teams.index');

        $this->actingAs($this->committee_member)
            ->from('teams.create')
            ->post(route('teams.store'), $this->valid_request)
            ->assertInvalid('name')
            ->assertRedirect('teams.create')
            ->assertSessionHasErrors('name');
    }
}
