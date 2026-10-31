<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarInventory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;

/*
| The Bar section of the dashboard (decided on 2026-10-31): the counter and
| stock gestures a volunteer needs often, each behind the lock of its route —
| a tile that answers 403 is worse than no tile.
*/

/**
 * @return array<int, string>
 */
function barTileLinks(User $user): array
{
    $response = test()->actingAs($user)->get(route('dashboard'))->assertOk();

    return array_column($response->viewData('barTiles'), 'href');
}

it('gives a barman the counter: a new order and the tabs to cash in', function (): void {
    BarOrder::create(['total_price' => 5, 'is_paid' => 0]);

    $barman = User::factory()->withRole(Role::BARMAN)->create();

    $this->actingAs($barman)->get(route('dashboard'))
        ->assertViewHas('showBar', true)
        ->assertViewHas('barTiles', fn (array $tiles): bool => $tiles[0]['href'] === route('bar.index')
            && $tiles[0]['color'] === 'secondary'
            && $tiles[1]['href'] === route('bar.orders.index')
            && $tiles[1]['badge'] === 1);

    expect(barTileLinks($barman))->toBe([route('bar.index'), route('bar.orders.index')]);
});

it('gives a storekeeper the stock: shopping, the inventory and the products', function (): void {
    $storekeeper = User::factory()->withRole(Role::STORE_KEEPER)->create();

    expect(barTileLinks($storekeeper))->toBe([
        route('bar.restocking.index'),
        route('bar.inventories.current'),
        route('bar.products.index'),
    ]);
});

it('offers to resume the inventory a count left open', function (): void {
    $storekeeper = User::factory()->withRole(Role::STORE_KEEPER)->create();

    $this->actingAs($storekeeper)->get(route('dashboard'))
        ->assertSee('Nouvel inventaire')
        ->assertDontSee("Reprendre l'inventaire");

    BarInventory::query()->create(['status' => BarInventory::STATUS_IN_PROGRESS, 'opened_by' => $storekeeper->id, 'opened_at' => now()]);

    $this->actingAs($storekeeper)->get(route('dashboard'))
        ->assertSee("Reprendre l'inventaire");
});

it('keeps the section from the committee, who only read the bar figures', function (): void {
    $this->actingAs(User::factory()->withRole(Role::COMMITTEE)->create())->get(route('dashboard'))
        ->assertViewHas('showBar', false)
        ->assertViewHas('barTiles', []);
});

it('drops the section with the bar', function (): void {
    config(['features.bar' => false]);

    $this->actingAs(User::factory()->withRole(Role::BARMAN)->create())->get(route('dashboard'))
        ->assertViewHas('showBar', false);
});

it('shows the section open on a phone, right after the member space', function (): void {
    $this->actingAs(User::factory()->withRole(Role::BARMAN)->create())->get(route('dashboard'))
        ->assertSeeInOrder(['Mon espace', 'Le comptoir']);
});
