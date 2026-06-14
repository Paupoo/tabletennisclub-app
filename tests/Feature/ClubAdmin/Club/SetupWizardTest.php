<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Models\AppSetting;
use Livewire\Livewire;

// ── Access control ───────────────────────────────────────────────────────────

it('allows access to /setup when not configured', function (): void {
    AppSetting::where('key', 'setup_completed')->delete();

    $this->get(route('setup'))->assertOk();
});

it('redirects /setup to home when setup is already complete', function (): void {
    // setup_completed is already '1' from TestCase::setUp()
    $this->get(route('setup'))->assertRedirect('/');
});

it('redirects admin routes to /setup when setup is not complete', function (): void {
    AppSetting::where('key', 'setup_completed')->delete();

    $this->get('/admin/dashboard')->assertRedirect(route('setup'));
});

// ── Step 2 — Admin account creation ─────────────────────────────────────────

it('creates an admin user and logs in on step 2', function (): void {
    AppSetting::where('key', 'setup_completed')->delete();

    Livewire::test('pages::setup.wizard')
        ->set('step', '2')
        ->set('firstName', 'Jean')
        ->set('lastName', 'Dupont')
        ->set('email', 'admin@test.be')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->call('completeStep2')
        ->assertSet('step', '3');

    $user = User::where('email', 'admin@test.be')->first();
    expect($user)->not->toBeNull();
    expect($user->is_admin)->toBeTrue();
});

it('fails step 2 when email is already taken', function (): void {
    AppSetting::where('key', 'setup_completed')->delete();
    User::factory()->create(['email' => 'existing@test.be']);

    Livewire::test('pages::setup.wizard')
        ->set('step', '2')
        ->set('firstName', 'Jean')
        ->set('lastName', 'Dupont')
        ->set('email', 'existing@test.be')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'password123')
        ->call('completeStep2')
        ->assertHasErrors(['email']);
});

it('fails step 2 when passwords do not match', function (): void {
    AppSetting::where('key', 'setup_completed')->delete();

    Livewire::test('pages::setup.wizard')
        ->set('step', '2')
        ->set('firstName', 'Jean')
        ->set('lastName', 'Dupont')
        ->set('email', 'admin@test.be')
        ->set('password', 'password123')
        ->set('passwordConfirmation', 'different')
        ->call('completeStep2')
        ->assertHasErrors(['password']);
});

// ── Step 3 — Club licence ────────────────────────────────────────────────────

it('creates a club record on step 3', function (): void {
    AppSetting::where('key', 'setup_completed')->delete();

    Livewire::test('pages::setup.wizard')
        ->set('step', '3')
        ->set('licence', 'TST001')
        ->set('clubName', 'Test Club')
        ->set('clubStreet', 'Rue de Test 1')
        ->set('clubCityCode', '1300')
        ->set('clubCityName', 'Wavre')
        ->set('clubEmailContact', 'contact@testclub.com')
        ->call('completeStep3')
        ->assertSet('step', '4')
        ->assertSet('maxReachable', 4);

    $club = Club::where('licence', 'TST001')->first();
    expect($club)->not->toBeNull();
    expect($club->licence)->toBe('TST001');
});

it('fails step 3 when licence is already taken', function (): void {
    AppSetting::where('key', 'setup_completed')->delete();
    Club::factory()->create(['licence' => 'TAKEN1']);

    Livewire::test('pages::setup.wizard')
        ->set('step', '3')
        ->set('licence', 'TAKEN1')
        ->call('completeStep3')
        ->assertHasErrors(['licence']);
});

// ── Step 4 — Season ──────────────────────────────────────────────────────────

it('creates an active season on step 4', function (): void {
    AppSetting::where('key', 'setup_completed')->delete();

    $existingClub = Club::factory()->create();

    Livewire::test('pages::setup.wizard')
        ->set('step', '4')
        ->set('clubId', $existingClub->id)
        ->set('seasonName', '2025-2026')
        ->set('seasonStartAt', '2025-08-01')
        ->set('seasonEndAt', '2026-07-31')
        ->call('completeStep4')
        ->assertSet('step', '5')
        ->assertSet('maxReachable', 5);

    $season = Season::where('name', '2025-2026')->first();
    expect($season)->not->toBeNull();
    expect($season->is_active)->toBeTrue();
});

it('fails step 4 when end date is before start date', function (): void {
    AppSetting::where('key', 'setup_completed')->delete();

    Livewire::test('pages::setup.wizard')
        ->set('step', '4')
        ->set('seasonName', '2025-2026')
        ->set('seasonStartAt', '2025-08-01')
        ->set('seasonEndAt', '2025-07-01')
        ->call('completeStep4')
        ->assertHasErrors(['seasonEndAt']);
});

// ── Step 7 — Setup completion ────────────────────────────────────────────────

it('marks setup as complete on step 7', function (): void {
    AppSetting::where('key', 'setup_completed')->delete();

    $admin = User::factory()->isAdmin()->create();

    Livewire::actingAs($admin)
        ->test('pages::setup.wizard')
        ->set('step', '7')
        ->set('maxReachable', 7)
        ->call('completeSetup');

    expect(AppSetting::get('setup_completed'))->toBe('1');
});
