<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const USERS_COMPONENT = 'pages::club-admin.users.index';

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
});

// ── HasBulkActions — tested via real users component ─────────────────────────

describe('HasBulkActions', function (): void {
    it('selecting all on a page sets selected to current page IDs', function (): void {
        User::factory()->count(3)->create();

        $component = Livewire::actingAs($this->admin)
            ->test(USERS_COMPONENT);

        $pageIds = $component->get('users')->pluck('id')->map(fn ($id): string => (string) $id)->toArray();

        $component->set('selectAll', true)
            ->assertSet('selected', $pageIds)
            ->assertSet('selectingAllResults', false);
    });

    it('deselecting selectAll clears the selection', function (): void {
        User::factory()->count(2)->create();

        Livewire::actingAs($this->admin)
            ->test(USERS_COMPONENT)
            ->set('selectAll', true)
            ->set('selectAll', false)
            ->assertSet('selected', [])
            ->assertSet('selectingAllResults', false);
    });

    it('selectAllResults sets selectingAllResults to true', function (): void {
        User::factory()->count(3)->create();

        Livewire::actingAs($this->admin)
            ->test(USERS_COMPONENT)
            ->set('selectAll', true)
            ->call('selectAllResults')
            ->assertSet('selectingAllResults', true);
    });

    it('clearSelection resets selected, selectAll, and selectingAllResults', function (): void {
        User::factory()->count(2)->create();

        Livewire::actingAs($this->admin)
            ->test(USERS_COMPONENT)
            ->set('selectAll', true)
            ->call('selectAllResults')
            ->call('clearSelection')
            ->assertSet('selected', [])
            ->assertSet('selectAll', false)
            ->assertSet('selectingAllResults', false);
    });

    it('toggleSelectionMode enables selection mode on first call', function (): void {
        Livewire::actingAs($this->admin)
            ->test(USERS_COMPONENT)
            ->assertSet('selectionModeActive', false)
            ->call('toggleSelectionMode')
            ->assertSet('selectionModeActive', true);
    });

    it('toggleSelectionMode clears selection when disabling', function (): void {
        User::factory()->count(2)->create();

        Livewire::actingAs($this->admin)
            ->test(USERS_COMPONENT)
            ->call('toggleSelectionMode')
            ->set('selectAll', true)
            ->call('toggleSelectionMode')
            ->assertSet('selectionModeActive', false)
            ->assertSet('selected', []);
    });
});

// ── HasFilterDrawer — tested via real users component ────────────────────────

describe('HasFilterDrawer', function (): void {
    it('removeFilter resets a string property', function (): void {
        Livewire::actingAs($this->admin)
            ->test(USERS_COMPONENT)
            ->set('selectedLicenceType', 'competitive')
            ->call('removeFilter', 'selectedLicenceType')
            ->assertSet('selectedLicenceType', 'both');
    });

    it('removeFilter removes a single value from the categories array', function (): void {
        Livewire::actingAs($this->admin)
            ->test(USERS_COMPONENT)
            ->set('categories', ['MEN', 'WOMEN'])
            ->call('removeFilter', 'categories_MEN')
            ->assertSet('categories', ['WOMEN']);
    });

    it('clearFilters resets all filter properties to defaults', function (): void {
        Livewire::actingAs($this->admin)
            ->test(USERS_COMPONENT)
            ->set('selectedLicenceType', 'competitive')
            ->set('categories', ['MEN'])
            ->call('clearFilters')
            ->assertSet('selectedLicenceType', 'both')
            ->assertSet('categories', []);
    });

    it('filterChips is empty when no filters are active', function (): void {
        $component = Livewire::actingAs($this->admin)
            ->test(USERS_COMPONENT);

        expect($component->get('filterChips'))->toBeEmpty();
    });

    it('filterChips includes a chip for active licence type filter', function (): void {
        $component = Livewire::actingAs($this->admin)
            ->test(USERS_COMPONENT)
            ->set('selectedLicenceType', 'competitive');

        expect($component->get('filterChips'))->toHaveCount(1)
            ->and($component->get('filterChips')[0]['key'])->toBe('selectedLicenceType');
    });

    it('filterChips includes one chip per selected gender', function (): void {
        $component = Livewire::actingAs($this->admin)
            ->test(USERS_COMPONENT)
            ->set('categories', ['MEN', 'WOMEN']);

        expect($component->get('filterChips'))->toHaveCount(2);
    });
});

// ── Bulk actions — tested via real users component ────────────────────────────

describe('Bulk actions', function (): void {
    it('confirmBulkArchive opens the confirm modal', function (): void {
        Livewire::actingAs($this->admin)
            ->test(USERS_COMPONENT)
            ->call('confirmBulkArchive')
            ->assertSet('confirmArchiveModal', true);
    });

    it('bulkArchive soft-deletes the selected users', function (): void {
        $user = User::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(USERS_COMPONENT)
            ->set('selected', [(string) $user->id])
            ->call('bulkArchive');

        expect(User::find($user->id))->toBeNull();
        expect(User::withTrashed()->find($user->id)->deleted_at)->not->toBeNull();
    });

    it('bulkArchive does not delete the authenticated admin', function (): void {
        Livewire::actingAs($this->admin)
            ->test(USERS_COMPONENT)
            ->set('selected', [(string) $this->admin->id])
            ->call('bulkArchive');

        expect($this->admin->fresh())->not->toBeNull();
    });

    it('bulkArchive skips users with an unresolved subscription for the active season', function (): void {
        $season = makeActiveSeason();

        $blocked = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $blocked->id,
            'season_id' => $season->id,
            'status' => 'confirmed',
        ]);

        $archivable = User::factory()->create();

        Livewire::actingAs($this->admin)
            ->test(USERS_COMPONENT)
            ->set('selected', [(string) $blocked->id, (string) $archivable->id])
            ->call('bulkArchive');

        expect(User::find($blocked->id))->not->toBeNull()
            ->and(User::find($archivable->id))->toBeNull();
    });
});
