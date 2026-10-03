<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
| A selection never outlives the list it was made on.
|
| Searching, filtering, switching tab or leaving the default view changes
| which rows the list holds: the selection is dropped, so a bulk action never
| reaches rows the screen no longer shows. Sorting and paging show the same
| rows in another order or another slice: the selection stays.
*/

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
});

/**
 * The members list on every member, three of them selected.
 */
function membersListWithASelection(User $admin): Testable
{
    User::factory()->count(3)->create(['last_name' => 'Zzselection']);

    return Livewire::actingAs($admin)
        ->withQueryParams(['allMembers' => true])
        ->test('pages::club-admin.users.index')
        ->set('selectAll', true)
        ->assertNotSet('selected', []);
}

/**
 * The payments list on its "pending" tab, three payments selected.
 */
function paymentsListWithASelection(User $admin): Testable
{
    $admin->assignRole(Role::TREASURY->value);
    $subscription = Subscription::factory()->create(['user_id' => User::factory()->create()->id]);

    foreach (range(1, 3) as $n) {
        $subscription->payments()->create([
            'reference' => sprintf('RESET/%05d', $n),
            'amount_due' => 10,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);
    }

    return Livewire::actingAs($admin)
        ->test('pages::club-admin.treasury.payments')
        ->set('statusFilter', 'pending')
        ->set('selectAll', true)
        ->assertNotSet('selected', []);
}

describe('members', function (): void {
    it('drops the selection when the search changes', function (): void {
        membersListWithASelection($this->admin)
            ->set('search', 'Zzselection')
            ->assertSet('selected', [])
            ->assertSet('selectAll', false)
            ->assertSet('selectingAllResults', false);
    });

    it('drops the selection when a filter changes', function (): void {
        membersListWithASelection($this->admin)
            ->set('hasKey', true)
            ->assertSet('selected', []);
    });

    it('drops the selection when the default view comes back', function (): void {
        membersListWithASelection($this->admin)
            ->set('allMembers', false)
            ->assertSet('selected', []);
    });

    it('drops the selection when a filter chip is removed', function (): void {
        Livewire::actingAs($this->admin)
            ->withQueryParams(['allMembers' => true, 'hasKey' => true])
            ->test('pages::club-admin.users.index')
            ->set('selected', [(string) $this->admin->id])
            ->call('removeFilter', 'hasKey')
            ->assertSet('selected', []);
    });

    it('drops the selection when the filters are cleared', function (): void {
        Livewire::actingAs($this->admin)
            ->withQueryParams(['allMembers' => true, 'hasKey' => true])
            ->test('pages::club-admin.users.index')
            ->set('selected', [(string) $this->admin->id])
            ->call('clearFilters')
            ->assertSet('selected', []);
    });

    it('keeps the selection when the list is sorted', function (): void {
        $component = membersListWithASelection($this->admin);
        $selected = $component->get('selected');

        $component->set('sortBy', ['column' => 'first_name', 'direction' => 'desc'])
            ->assertSet('selected', $selected);
    });

    it('keeps the selection when the page changes', function (): void {
        $component = membersListWithASelection($this->admin);
        $selected = $component->get('selected');

        $component->call('setPage', 2)
            ->assertSet('selected', $selected);
    });

    it('keeps the selection while a modal field is typed in', function (): void {
        $component = membersListWithASelection($this->admin);
        $selected = $component->get('selected');

        $component->set('departureReason', 'Moved away')
            ->assertSet('selected', $selected);
    });
});

describe('treasury payments', function (): void {
    it('drops the selection when the search changes', function (): void {
        paymentsListWithASelection($this->admin)
            ->set('search', 'RESET')
            ->assertSet('selected', []);
    });

    it('drops the selection when the tab changes', function (): void {
        paymentsListWithASelection($this->admin)
            ->set('statusFilter', 'paid')
            ->assertSet('selected', []);
    });

    it('drops the selection when a filter changes', function (): void {
        paymentsListWithASelection($this->admin)
            ->set('eventType', Subscription::class)
            ->assertSet('selected', []);
    });

    it('keeps the selection when the list is sorted', function (): void {
        $component = paymentsListWithASelection($this->admin);
        $selected = $component->get('selected');

        $component->set('sortBy', ['column' => 'reference', 'direction' => 'asc'])
            ->assertSet('selected', $selected);
    });

    it('keeps the selection when the page changes', function (): void {
        $component = paymentsListWithASelection($this->admin);
        $selected = $component->get('selected');

        $component->call('setPage', 2)
            ->assertSet('selected', $selected);
    });
});
