<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->season = Season::factory()->create();

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
});

test('admin or committee member can access team list page', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.interclubs.teams'))
        ->assertStatus(200);

    $this->actingAs($this->committee_member)
        ->get(route('admin.interclubs.teams'))
        ->assertStatus(200);
});

test('unlogged user is redirected to login', function (): void {
    $this->get(route('admin.interclubs.teams'))
        ->assertRedirect('/login');
});

test('member cannot access team list page', function (): void {
    $this->actingAs($this->user)
        ->get(route('admin.interclubs.teams'))
        ->assertForbidden();
});

test('admin can validate team creation fields via Livewire', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.teams.index')
        ->set('newTeamName', '')
        ->set('newCategory', '')
        ->set('newLevel', '')
        ->set('newDivision', '')
        ->call('createTeam')
        ->assertHasErrors(['newTeamName', 'newCategory', 'newLevel', 'newDivision']);
});

test('member cant call createTeam via Livewire', function (): void {
    Livewire::actingAs($this->user)
        ->test('pages::club-events.interclubs.teams.index')
        ->set('newTeamName', 'A')
        ->set('newCategory', 'MEN')
        ->set('newLevel', 'NATIONAL')
        ->set('newDivision', '1A')
        ->call('createTeam')
        ->assertStatus(403);
});
