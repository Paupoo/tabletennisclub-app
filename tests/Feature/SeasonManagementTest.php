<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeAdmin(): User
{
    return User::factory()->create(['is_admin' => true, 'is_active' => true]);
}

function makeCommitteeMember(): User
{
    return User::factory()->create(['is_committee_member' => true, 'is_active' => true]);
}

function makeRegularUser(): User
{
    return User::factory()->create(['is_admin' => false, 'is_committee_member' => false, 'is_active' => true]);
}

// ── Access control ────────────────────────────────────────────────────────────

describe('season management access', function () {
    it('allows admin to access the seasons page', function () {
        $this->actingAs(makeAdmin())
            ->get(route('admin.seasons.index'))
            ->assertOk();
    });

    it('allows committee member to access the seasons page', function () {
        $this->actingAs(makeCommitteeMember())
            ->get(route('admin.seasons.index'))
            ->assertOk();
    });

    it('denies regular users access to the seasons page', function () {
        $this->actingAs(makeRegularUser())
            ->get(route('admin.seasons.index'))
            ->assertForbidden();
    });
});

// ── Livewire component ────────────────────────────────────────────────────────

describe('season management component', function () {
    it('lists seasons ordered by start date ascending', function () {
        $past = Season::factory()->create([
            'name' => '2023-2024',
            'start_at' => now()->subYears(2),
            'end_at' => now()->subYear(),
            'is_active' => false,
        ]);
        $active = Season::factory()->create([
            'name' => '2025-2026',
            'start_at' => now()->subMonth(),
            'end_at' => now()->addMonths(8),
            'is_active' => true,
        ]);
        $future = Season::factory()->create([
            'name' => '2026-2027',
            'start_at' => now()->addMonths(9),
            'end_at' => now()->addMonths(21),
            'is_active' => false,
        ]);

        Livewire::actingAs(makeAdmin())
            ->test('pages::club-admin.seasons.index')
            ->assertSee($past->name)
            ->assertSee($active->name)
            ->assertSee($future->name);
    });

    it('hides older past seasons by default and shows only the most recent one', function () {
        $older = Season::factory()->create([
            'start_at' => now()->subYears(3),
            'end_at' => now()->subYears(2),
            'is_active' => false,
        ]);
        $recent = Season::factory()->create([
            'start_at' => now()->subYears(2)->addDay(),
            'end_at' => now()->subYear(),
            'is_active' => false,
        ]);
        Season::factory()->create([
            'start_at' => now()->subMonth(),
            'end_at' => now()->addMonths(8),
            'is_active' => true,
        ]);

        $component = Livewire::actingAs(makeAdmin())
            ->test('pages::club-admin.seasons.index');

        $component
            ->assertSet('showAllPastSeasons', false)
            ->assertViewHas('hiddenPastCount', 1)
            ->assertDontSee($older->name)
            ->assertSee($recent->name);
    });

    it('reveals all past seasons when showAllPastSeasons is toggled', function () {
        $older = Season::factory()->create([
            'start_at' => now()->subYears(3),
            'end_at' => now()->subYears(2),
            'is_active' => false,
        ]);
        $recent = Season::factory()->create([
            'start_at' => now()->subYears(2)->addDay(),
            'end_at' => now()->subYear(),
            'is_active' => false,
        ]);
        Season::factory()->create([
            'start_at' => now()->subMonth(),
            'end_at' => now()->addMonths(8),
            'is_active' => true,
        ]);

        Livewire::actingAs(makeAdmin())
            ->test('pages::club-admin.seasons.index')
            ->set('showAllPastSeasons', true)
            ->assertViewHas('hiddenPastCount', 0)
            ->assertSee($older->name)
            ->assertSee($recent->name);
    });

    it('activates a season and deactivates the previous one', function () {
        $current = Season::factory()->create(['is_active' => true]);
        $next = Season::factory()->create(['is_active' => false]);

        Livewire::actingAs(makeAdmin())
            ->test('pages::club-admin.seasons.index')
            ->call('openActivate', $next->id)
            ->assertSet('activateModal', true)
            ->assertSet('activateName', $next->name)
            ->call('confirmActivate');

        expect($current->fresh()->is_active)->toBeFalse()
            ->and($next->fresh()->is_active)->toBeTrue();
    });

    it('can create a new season', function () {
        Livewire::actingAs(makeAdmin())
            ->test('pages::club-admin.seasons.index')
            ->set('createName', '2099-2100')
            ->set('createStartAt', '2099-09-01')
            ->set('createEndAt', '2100-06-30')
            ->call('createSeason');

        expect(Season::where('name', '2099-2100')->exists())->toBeTrue();
    });

    it('rejects duplicate season name on create', function () {
        Season::factory()->create(['name' => '2099-2100']);

        Livewire::actingAs(makeAdmin())
            ->test('pages::club-admin.seasons.index')
            ->set('createName', '2099-2100')
            ->set('createStartAt', '2099-09-01')
            ->set('createEndAt', '2100-06-30')
            ->call('createSeason')
            ->assertHasErrors('createName');
    });

    it('can edit a season name and dates', function () {
        $season = Season::factory()->create(['name' => 'OldName']);

        Livewire::actingAs(makeAdmin())
            ->test('pages::club-admin.seasons.index')
            ->call('openEdit', $season->id)
            ->set('editName', 'NewName')
            ->set('editStartAt', '2030-09-01')
            ->set('editEndAt', '2031-06-30')
            ->call('updateSeason');

        expect($season->fresh()->name)->toBe('NewName');
    });

    it('forbids regular users from creating a season', function () {
        Livewire::actingAs(makeRegularUser())
            ->test('pages::club-admin.seasons.index')
            ->call('createSeason')
            ->assertForbidden();
    });

    it('forbids regular users from activating a season', function () {
        $season = Season::factory()->create(['is_active' => false]);

        Livewire::actingAs(makeRegularUser())
            ->test('pages::club-admin.seasons.index')
            ->call('openActivate', $season->id)
            ->assertForbidden();
    });
});

// ── season:provision command ──────────────────────────────────────────────────

describe('season:provision command', function () {
    it('creates the next two seasons when none exist beyond the latest', function () {
        $latest = Season::factory()->create([
            'name' => '2030-2031',
            'start_at' => '2030-09-01',
            'end_at' => '2031-06-30',
            'is_active' => true,
        ]);

        $this->artisan('season:provision')->assertSuccessful();

        expect(Season::where('name', '2031-2032')->exists())->toBeTrue()
            ->and(Season::where('name', '2032-2033')->exists())->toBeTrue();
    });

    it('bootstraps current season and two ahead when database is empty', function () {
        $this->artisan('season:provision')->assertSuccessful();

        $now = now();
        $currentYear = $now->month >= 9 ? (int) $now->year : (int) $now->year - 1;

        expect(Season::where('name', $currentYear.'-'.($currentYear + 1))->exists())->toBeTrue()
            ->and(Season::where('name', ($currentYear + 1).'-'.($currentYear + 2))->exists())->toBeTrue()
            ->and(Season::where('name', ($currentYear + 2).'-'.($currentYear + 3))->exists())->toBeTrue()
            ->and(Season::count())->toBe(3);
    });

    it('is idempotent — does not create duplicates', function () {
        // Active season + the two expected upcoming ones already provisioned
        Season::factory()->create(['name' => '2030-2031', 'start_at' => '2030-09-01', 'end_at' => '2031-06-30', 'is_active' => true]);
        Season::factory()->create(['name' => '2031-2032', 'start_at' => '2031-09-01', 'end_at' => '2032-06-30']);
        Season::factory()->create(['name' => '2032-2033', 'start_at' => '2032-09-01', 'end_at' => '2033-06-30']);

        $countBefore = Season::count();
        $this->artisan('season:provision')->assertSuccessful();

        expect(Season::count())->toBe($countBefore);
    });
});
