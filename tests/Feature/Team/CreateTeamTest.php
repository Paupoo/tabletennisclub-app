<?php

declare(strict_types=1);

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;

beforeEach(function (): void {
    Season::factory()->create();

    $this->user = User::factory()
        ->isNotCompetitor()
        ->create();

    $this->committee_member = User::factory()
        ->isCommitteeMember()
        ->isNotCompetitor()
        ->create();

    $this->admin = User::factory()
        ->isAdmin()
        ->isNotCompetitor()
        ->create();

    User::factory()->create();
    User::factory()->create();

    $this->less_than_5_players_request = [
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

    $this->valid_request = [
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
});
test('admin or committee member can create a team', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.interclubs.teams'));

    $response->assertStatus(200);

    $response = $this->actingAs($this->committee_member)
        ->get(route('admin.interclubs.teams'))
        ->assertStatus(200);

    $response = $this->actingAs($this->committee_member)
        ->from(route('admin.interclubs.teams'))
        ->post(route('teams.store'), $this->valid_request)
        ->assertRedirectToRoute('teams.index');
});
test('creation of a team creates one new entry in the database', function (): void {
    $totalTeams = Team::count();

    Team::factory()->create();

    $this->assertDatabaseCount('teams', ++$totalTeams);
});
test('member cant create a team', function (): void {
    $this->actingAs($this->user)
        ->get(route('teams.create'))
        ->assertStatus(403);

    $this->actingAs($this->user)
        ->post(route('teams.store'))
        ->assertStatus(403);
});
test('members dont see create teams button', function (): void {
    $this->actingAs($this->user)
        ->get(route('admin.interclubs.teams'))
        ->assertDontSee('Create new team')
        ->assertDontSee('Team Builder');
});
test('team should contains minimum 5 players', function (): void {
    $this->actingAs($this->admin)
        ->from('teams.create')
        ->post(route('teams.store'), $this->less_than_5_players_request)
        ->assertInvalid(['players'])
        ->assertSessionHasErrors(['players']);
});
test('unlogged user cant create a team', function (): void {
    $this->get(route('teams.create'))
        ->assertRedirect('/login');
});
test('validation should fail in case of duplicate teams into same league', function (): void {
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
});
test('validation should fail in case of invalid parameters', function (): void {
    $invalidUserId = (int) User::orderByDesc('id')->first()->id + 10;
    $invalid_request = [
        'captain_id' => $invalidUserId,
        'category' => 'somethingWrong',
        'division' => null,
        'level' => 'somethingWrong',
        'name' => 'AA',
        'players' => [
            0 => $invalidUserId,
            1 => $invalidUserId + 1,
            2 => $invalidUserId + 2,
            3 => '1',       // this one is correct.
        ],
        'season_id' => 99,
    ];

    $this->actingAs($this->admin)
        ->from(route('teams.create'))
        ->post(route('teams.store'), $invalid_request)
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
});
