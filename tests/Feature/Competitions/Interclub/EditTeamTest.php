<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Livewire\Livewire;

beforeEach(function (): void {
    $season = Season::factory()->create();

    $league = League::create([
        'division' => '1A',
        'level' => 'NATIONAL',
        'category' => 'VETERANS',
        'season_id' => $season->id,
    ]);

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

    User::factory()->count(4)->create();

    $this->team = Team::create([
        'name' => 'Z',
        'season_id' => $season->id,
        'league_id' => $league->id,
        'captain_id' => 1,
    ]);

    $this->team->users()->attach([1, 2, 3, 4, 5, 6, 7]);
});

test('admin can access edit page', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.interclubs.teams.edit', $this->team))
        ->assertStatus(200);
});

test('committee member can access edit page', function (): void {
    $this->actingAs($this->committee_member)
        ->get(route('admin.interclubs.teams.edit', $this->team))
        ->assertStatus(200);
});

test('member cant access edit page', function (): void {
    $this->actingAs($this->member)
        ->get(route('admin.interclubs.teams.edit', $this->team))
        ->assertStatus(403);
});

test('unlogged user is redirected to login', function (): void {
    $this->get(route('admin.interclubs.teams.edit', $this->team))
        ->assertRedirect('/login');
});

test('admin can see edit button from team show view', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.interclubs.teams.show', $this->team->id))
        ->assertSee('Modifier');
});

test('committee member can see edit button from team show view', function (): void {
    $this->actingAs($this->committee_member)
        ->get(route('admin.interclubs.teams.show', $this->team->id))
        ->assertSee('Modifier');
});

test('member cant see edit button from team show view', function (): void {
    $this->actingAs($this->member)
        ->get(route('admin.interclubs.teams.show', $this->team->id))
        ->assertDontSee('Edit');
});

test('team name must be single letter', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->set('name', 'AA')
        ->call('save')
        ->assertHasErrors('name');
});

test('team name is required', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors('name');
});

test('team must have at least one member', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->set('memberIds', [])
        ->call('save')
        ->assertHasErrors('memberIds');
});

test('admin can toggle team member', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->call('toggleMember', 1)
        ->assertSet('memberIds', [2, 3, 4, 5, 6, 7]);
});

test('admin can set captain', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->call('setCaptain', 5)
        ->assertSet('captainId', 5);
});

test('admin can remove captain', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.edit', ['team' => $this->team])
        ->call('removeCaptain')
        ->assertSet('captainId', null);
});
