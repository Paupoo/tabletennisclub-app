<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create([
        'season_id' => $this->season->id,
        'category' => 'MEN',
        'division' => 'P2A',
    ]);

    $this->ourClub = Club::factory()->ownClub()->create();
    $this->ourTeam = Team::factory()->create([
        'name' => 'A',
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $this->ourClub->id,
    ]);

    $this->admin = User::factory()->isAdmin()->create();
    $this->committee = User::factory()->isCommitteeMember()->withRole(Role::INTERCLUBS)->create();
    $this->user = User::factory()->create();
});

// ── Access control ─────────────────────────────────────────────────────────────

it('redirects guests to login', function (): void {
    $this->get(route('admin.interclubs.division-setup'))
        ->assertRedirect(route('login'));
});

it('forbids regular users', function (): void {
    $this->actingAs($this->user)
        ->get(route('admin.interclubs.division-setup'))
        ->assertForbidden();
});

it('allows admins to access the page', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.interclubs.division-setup'))
        ->assertOk();
});

it('allows committee members to access the page', function (): void {
    $this->actingAs($this->committee)
        ->get(route('admin.interclubs.division-setup'))
        ->assertOk();
});

// ── addParticipant ─────────────────────────────────────────────────────────────

it('creates a club and a team when adding a participant', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.division-setup')
        ->set('seasonId', $this->season->id)
        ->call('selectLeague', $this->league->id)
        ->set('formClubName', 'TT Wavre')
        ->set('formClubStreet', 'Rue de la Gare 10')
        ->set('formTeamLetter', 'A')
        ->call('addParticipant');

    expect(Club::where('name', 'TT Wavre')->exists())->toBeTrue();
    expect(
        Team::where('league_id', $this->league->id)
            ->where('season_id', $this->season->id)
            ->where('name', 'A')
            ->whereHas('club', fn ($q) => $q->where('name', 'TT Wavre'))
            ->exists()
    )->toBeTrue();
});

it('reuses an existing club when the name already exists', function (): void {
    $existing = Club::factory()->create(['name' => 'TT Namur', 'licence' => 'OPP-TTNAMU']);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.division-setup')
        ->set('seasonId', $this->season->id)
        ->call('selectLeague', $this->league->id)
        ->set('formClubName', 'TT Namur')
        ->set('formTeamLetter', 'B')
        ->call('addParticipant');

    expect(Club::where('name', 'TT Namur')->count())->toBe(1);
    expect(
        Team::where('club_id', $existing->id)
            ->where('league_id', $this->league->id)
            ->where('name', 'B')
            ->exists()
    )->toBeTrue();
});

it('blocks duplicate team creation in the same division', function (): void {
    $oppClub = Club::factory()->create(['name' => 'TT Rixensart', 'licence' => 'OPP-TTRIXE']);
    Team::factory()->create([
        'name' => 'A',
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $oppClub->id,
    ]);

    $before = Team::count();

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.division-setup')
        ->set('seasonId', $this->season->id)
        ->call('selectLeague', $this->league->id)
        ->set('formClubName', 'TT Rixensart')
        ->set('formTeamLetter', 'A')
        ->call('addParticipant');

    expect(Team::count())->toBe($before);
});

it('validates that club name is required', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.division-setup')
        ->set('seasonId', $this->season->id)
        ->call('selectLeague', $this->league->id)
        ->set('formClubName', '')
        ->set('formTeamLetter', 'A')
        ->call('addParticipant')
        ->assertHasErrors(['formClubName']);
});

it('validates that team letter is a single alpha character', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.division-setup')
        ->set('seasonId', $this->season->id)
        ->call('selectLeague', $this->league->id)
        ->set('formClubName', 'TT Genappe')
        ->set('formTeamLetter', 'AB')
        ->call('addParticipant')
        ->assertHasErrors(['formTeamLetter']);
});

// ── deleteParticipant ──────────────────────────────────────────────────────────

it('deletes a participant that has no matches', function (): void {
    $oppClub = Club::factory()->create(['licence' => 'OPP-TEST01']);
    $oppTeam = Team::factory()->create([
        'name' => 'B',
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $oppClub->id,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.division-setup')
        ->set('seasonId', $this->season->id)
        ->call('selectLeague', $this->league->id)
        ->call('confirmDelete', $oppTeam->id)
        ->call('deleteParticipant');

    expect(Team::find($oppTeam->id))->toBeNull();
});

it('blocks deletion of a team that has matches', function (): void {
    $oppClub = Club::factory()->create(['licence' => 'OPP-TEST02']);
    $oppTeam = Team::factory()->create([
        'name' => 'C',
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $oppClub->id,
    ]);

    Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->ourTeam->id,
        'visiting_team_id' => $oppTeam->id,
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.division-setup')
        ->set('seasonId', $this->season->id)
        ->call('selectLeague', $this->league->id)
        ->call('confirmDelete', $oppTeam->id)
        ->call('deleteParticipant');

    expect(Team::find($oppTeam->id))->not->toBeNull();
});

it('lists already encoded opponent clubs in the picker', function (): void {
    Club::factory()->create(['name' => 'TT Jodoigne', 'licence' => 'OPP-TTJODO', 'street' => 'Rue Neuve 4']);
    Club::factory()->create(['name' => 'TT Perwez', 'licence' => 'OPP-TTPERW']);

    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.division-setup')
        ->call('openAddModal');

    $names = array_column($component->get('clubOptions'), 'name');

    expect($names)->toContain('TT Jodoigne', 'TT Perwez')
        ->and($names)->not->toContain($this->ourClub->name);

    $component->call('search', 'Jodo');

    expect(array_column($component->get('clubOptions'), 'name'))->toBe(['TT Jodoigne']);
});

it('reuses the club selected in the picker without creating a new one', function (): void {
    $existing = Club::factory()->create(['name' => 'TT Chastre', 'licence' => 'OPP-TTCHAS']);
    $before = Club::count();

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.division-setup')
        ->set('seasonId', $this->season->id)
        ->call('selectLeague', $this->league->id)
        ->call('openAddModal')
        ->set('formClubId', $existing->id)
        ->set('formTeamLetter', 'C')
        ->call('addParticipant')
        ->assertHasNoErrors();

    expect(Club::count())->toBe($before);
    expect(
        Team::where('club_id', $existing->id)
            ->where('league_id', $this->league->id)
            ->where('name', 'C')
            ->exists()
    )->toBeTrue();
});

it('keeps the selected club visible after a narrowing search', function (): void {
    $existing = Club::factory()->create(['name' => 'TT Beauvechain', 'licence' => 'OPP-TTBEAU']);
    Club::factory()->create(['name' => 'TT Tubize', 'licence' => 'OPP-TTTUBI']);

    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.division-setup')
        ->call('openAddModal')
        ->set('formClubId', $existing->id)
        ->call('search', 'Tubize');

    expect(array_column($component->get('clubOptions'), 'name'))
        ->toContain('TT Beauvechain', 'TT Tubize');
});

it('switches to manual encoding and clears the picked club', function (): void {
    $existing = Club::factory()->create(['name' => 'TT Nivelles', 'licence' => 'OPP-TTNIVE']);

    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.division-setup')
        ->call('openAddModal')
        ->set('formClubId', $existing->id)
        ->call('toggleNewClub');

    expect($component->get('formNewClub'))->toBeTrue()
        ->and($component->get('formClubId'))->toBeNull();
});

it('accepts the picker being cleared', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.division-setup')
        ->call('openAddModal')
        ->set('formClubId', '')
        ->assertSet('formClubId', null);
});
