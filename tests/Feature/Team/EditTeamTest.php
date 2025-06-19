<?php

declare(strict_types=1);

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Models\Team;
use App\Models\User;

beforeEach(function (): void {
    $this->member = User::factory()->create([
        'is_admin' => false,
        'is_committee_member' => false,
        'is_competitor' => false,
        'licence' => null,
    ]);

    $this->committee_member = User::factory()->create([
        'is_admin' => false,
        'is_committee_member' => true,
        'is_competitor' => false,
        'licence' => null,
    ]);

    $this->admin = User::factory()->create([
        'is_admin' => true,
        'is_committee_member' => false,
        'is_competitor' => false,
        'licence' => null,
    ]);

    $this->valid_request = [
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

    $this->valid_request_2 = [
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

    $this->less_than_5_players_request = [
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
});
test('admin and committee members can see edit button from team show view', function (): void {
    $this->actingAs($this->admin)
        ->get(route('teams.show', 1))
        ->assertSee('Edit');

    $this->actingAs($this->committee_member)
        ->get(route('teams.show', 1))
        ->assertSee('Edit');
});
test('admin or committee member can edit a team', function (): void {
    $admin = User::where('is_admin', true)
        ->where('is_committee_member', false)
        ->first();
    $team = Team::first();
    $response = $this->actingAs($admin)
        ->from('teams.edit', $team->id)
        ->put(route('teams.update', $team->id), [
            'name' => 'T',
        ])
        ->assertInvalid()
        ->assertSessionHasErrors();

    $committee_member = User::firstWhere('is_admin', false)
        ->firstWhere('is_committee_member', true);

    $response = $this->actingAs($committee_member)
        ->from('teams.edit', $team->id)
        ->put(route('teams.update', $team->id), [
            'name' => 'T',
        ])
        ->assertInvalid()
        ->assertSessionHasErrors();
});
test('member cannot see delete team button', function (): void {
    $user = User::factory()->create([
        'is_admin' => false,
        'is_committee_member' => false,
    ]);

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertDontSee('Delete');
});
test('member cannot see edit team button', function (): void {
    $user = User::factory()->create([
        'is_admin' => false,
        'is_committee_member' => false,
    ]);

    $this->actingAs($user)
        ->get(route('teams.index'))
        ->assertDontSee('Edit');
});
test('member cant edit a team', function (): void {
    $user = User::factory()->create([
        'is_admin' => false,
        'is_committee_member' => false,
    ]);
    $team = Team::first();

    $this->actingAs($user)
        ->get(route('teams.edit', $team->id))
        ->assertStatus(403);
});
test('member cant see edit button from team show view', function (): void {
    $this->actingAs($this->member)
        ->get(route('teams.show', 1))
        ->assertDontSee('Edit');
});
test('team should contains minimum 5 players', function (): void {
    $team = Team::firstwhere('name', 'Z');

    $this->actingAs($this->admin)
        ->from(route('teams.edit', $team))
        ->put(route('teams.update', $team), $this->less_than_5_players_request)
        ->assertInvalid('players')
        ->assertRedirect(route('teams.edit', $team))
        ->assertSessionHasErrorsIn('players');
});
test('unlogged user cant edit a team', function (): void {
    $team = Team::first();

    $response = $this->get(route('teams.edit', $team->id));

    $response->assertRedirect('/login');
});
test('updates are correctly stored', function (): void {
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

    expect($total_players_final_count)->toEqual(--$total_players);
    expect($storeTeam->captain_id)->toEqual(5);
});
test('validation should fail in case of duplicate teams into same league', function (): void {
    // Create 2 different teams
    $this->actingAs($this->committee_member)
        ->from(route('teams.create'))
        ->post(route('teams.store'), $this->valid_request)
        ->assertRedirectToRoute('teams.index');

    $this->actingAs($this->committee_member)
        ->from('teams.create')
        ->post(route('teams.store'), $this->valid_request_2)
        ->assertRedirectToRoute('teams.index');

    // Create the duplicated team
    $updated_team = Team::find(1);
    $this->actingAs($this->committee_member)
        ->from(route('teams.edit', $updated_team))
        ->put(route('teams.update', $updated_team), $this->valid_request_2)
        ->assertInvalid('name')
        ->assertRedirect(route('teams.edit', $updated_team))
        ->assertSessionHasErrors('name');
});
