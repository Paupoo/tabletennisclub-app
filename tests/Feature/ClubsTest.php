<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
    $this->committee = User::factory()->isCommitteeMember()->create();
    $this->user = User::factory()->create();
});

// ── Access control ──────────────────────────────────────────────────────────

it('redirects guests to login', function (): void {
    $this->get(route('admin.interclubs.clubs'))->assertRedirect(route('login'));
});

it('forbids regular users', function (): void {
    $this->actingAs($this->user)->get(route('admin.interclubs.clubs'))->assertForbidden();
});

it('allows admins to access the page', function (): void {
    $this->actingAs($this->admin)->get(route('admin.interclubs.clubs'))->assertOk();
});

it('allows committee members to access the page', function (): void {
    $this->actingAs($this->committee)->get(route('admin.interclubs.clubs'))->assertOk();
});

// ── Create ──────────────────────────────────────────────────────────────────

it('creates a club with auto-generated licence', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.clubs')
        ->call('openCreateModal')
        ->set('formName', 'TT Wavre')
        ->set('formStreet', 'Rue de la Gare 10')
        ->set('formCityCode', '1300')
        ->set('formCityName', 'Wavre')
        ->call('save');

    $club = Club::where('name', 'TT Wavre')->first();
    expect($club)->not->toBeNull();
    expect($club->licence)->toStartWith('OPP-');
    expect($club->street)->toBe('Rue de la Gare 10');
    expect($club->city_code)->toBe('1300');
    expect($club->city_name)->toBe('Wavre');
});

it('creates a club with a provided licence', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.clubs')
        ->call('openCreateModal')
        ->set('formName', 'TT Namur')
        ->set('formLicence', 'NAM-001')
        ->call('save');

    expect(Club::where('licence', 'NAM-001')->exists())->toBeTrue();
});

it('validates club name is required', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.clubs')
        ->call('openCreateModal')
        ->set('formName', '')
        ->call('save')
        ->assertHasErrors(['formName']);
});

it('validates licence is unique', function (): void {
    Club::factory()->create(['licence' => 'DUP-001']);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.clubs')
        ->call('openCreateModal')
        ->set('formName', 'TT Dupont')
        ->set('formLicence', 'DUP-001')
        ->call('save')
        ->assertHasErrors(['formLicence']);
});

// ── Edit ────────────────────────────────────────────────────────────────────

it('updates a club', function (): void {
    $club = Club::factory()->create(['name' => 'TT Old Name', 'licence' => 'OPP-OLD001']);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.clubs')
        ->call('openEditModal', $club->id)
        ->set('formName', 'TT New Name')
        ->set('formCityName', 'Namur')
        ->call('save');

    expect($club->fresh()->name)->toBe('TT New Name');
    expect($club->fresh()->city_name)->toBe('Namur');
});

it('allows keeping the same licence when editing', function (): void {
    $club = Club::factory()->create(['licence' => 'KEEP-001']);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.clubs')
        ->call('openEditModal', $club->id)
        ->set('formName', 'Updated Name')
        ->call('save')
        ->assertHasNoErrors();
});

// ── Delete ───────────────────────────────────────────────────────────────────

it('deletes a club with no teams', function (): void {
    $club = Club::factory()->create(['licence' => 'OPP-DEL001']);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.clubs')
        ->call('confirmDelete', $club->id)
        ->call('delete');

    expect(Club::find($club->id))->toBeNull();
});

it('blocks deletion of a club that has teams', function (): void {
    $season = Season::factory()->create(['is_active' => true]);
    $club = Club::factory()->create(['licence' => 'OPP-HASTEAM']);
    Team::factory()->create(['club_id' => $club->id, 'season_id' => $season->id]);

    Livewire::actingAs($this->admin)
        ->test('pages::club-events.interclubs.clubs')
        ->call('confirmDelete', $club->id)
        ->call('delete');

    expect(Club::find($club->id))->not->toBeNull();
});
