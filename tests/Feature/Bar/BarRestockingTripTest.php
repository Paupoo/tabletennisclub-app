<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarRestocking;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Bar — la tournée de courses
|--------------------------------------------------------------------------
|
| « Je fais les courses » fige la liste au nom de celui qui part : les autres
| voient qu'il y est, et deux personnes n'achètent pas les mêmes casiers. Une
| seule tournée à la fois ; n'importe qui peut la reprendre ou l'abandonner, sans
| délai — ce sont des adultes, l'écran leur conseille seulement d'appeler
| d'abord celui qui y était.
|
*/

beforeEach(function (): void {
    $this->shopper = User::factory()->withRole(Role::STORE_KEEPER)->create(['first_name' => 'Aurélien', 'last_name' => 'Paulus']);
    $beers = BarCategory::create(['name' => 'Bières']);
    $softs = BarCategory::create(['name' => 'Softs']);
    $this->jupiler = restockingTripProduct('Jupiler', $beers, stock: 5, min: 12, max: 48, packSize: 24, packLabel: 'casier');
    $this->coca = restockingTripProduct('Coca-Cola', $softs, stock: 14, min: 6, max: 30, packSize: 6, packLabel: 'pack');
});

function restockingTripProduct(string $name, BarCategory $category, int $stock, int $min, int $max, int $packSize = 1, ?string $packLabel = null): BarProduct
{
    $product = BarProduct::create([
        'name' => $name,
        'sale_price' => 200,
        'is_available' => 1,
        'category_id' => $category->id,
        'low_stock_threshold' => $min,
        'max_stock' => $max,
        'pack_size' => $packSize,
        'pack_label' => $packLabel,
    ]);

    if ($stock > 0) {
        BarStockMovement::create([
            'product_id' => $product->id,
            'quantity' => $stock,
            'remaining_quantity' => $stock,
            'movement_type' => BarStockMovement::TYPE_IN,
        ]);
    }

    return $product;
}

it('shows the shopping list to a store keeper, in packs', function (): void {
    $this->actingAs($this->shopper)
        ->get(route('bar.restocking.index'))
        ->assertOk()
        ->assertSeeInOrder([__('To buy'), 'Jupiler', '2 × casier', __('If you have room'), 'Coca-Cola', '3 × pack']);
});

it('puts the shopping in the bar menu of a store keeper', function (): void {
    $this->actingAs($this->shopper)
        ->get(route('bar.products.index'))
        ->assertSee('href="' . route('bar.restocking.index') . '"', false);
});

it('stays closed to a barman', function (): void {
    $this->actingAs(User::factory()->withRole(Role::BARMAN)->create())
        ->get(route('bar.restocking.index'))
        ->assertForbidden();
});

it('freezes the list in the name of whoever goes shopping', function (): void {
    Livewire::actingAs($this->shopper)
        ->test('pages::bar.restocking')
        ->call('start')
        ->assertHasNoErrors();

    $trip = BarRestocking::inProgress();

    expect($trip)->not->toBeNull()
        ->and($trip->shopper_id)->toBe($this->shopper->id)
        ->and($trip->lines)->toHaveCount(2);

    $jupiler = $trip->lines->firstWhere('product_id', $this->jupiler->id);
    expect($jupiler)
        ->section->toBe('to_buy')
        ->proposed_packs->toBe(2)
        ->pack_size->toBe(24)
        ->stock_at_start->toBe(5)
        ->in_cart->toBeFalse();
});

it('refuses a trip when nothing must be bought', function (): void {
    $this->jupiler->update(['max_stock' => null]);

    Livewire::actingAs($this->shopper)
        ->test('pages::bar.restocking')
        ->call('start');

    expect(BarRestocking::inProgress())->toBeNull();
});

it('keeps a single trip at a time', function (): void {
    Livewire::actingAs($this->shopper)->test('pages::bar.restocking')->call('start');
    Livewire::actingAs(User::factory()->withRole(Role::STORE_KEEPER)->create())->test('pages::bar.restocking')->call('start');

    expect(BarRestocking::query()->count())->toBe(1)
        ->and(BarRestocking::inProgress()->shopper_id)->toBe($this->shopper->id);
});

it('remembers what is already in the cart, and only for the one shopping', function (): void {
    Livewire::actingAs($this->shopper)->test('pages::bar.restocking')->call('start');
    $line = BarRestocking::inProgress()->lines->firstWhere('product_id', $this->jupiler->id);

    Livewire::actingAs($this->shopper)
        ->test('pages::bar.restocking')
        ->call('toggleInCart', $line->id, true);

    expect($line->fresh()->in_cart)->toBeTrue();

    // Un autre ne coche pas dans le caddie de quelqu'un : il reprend d'abord la tournée.
    Livewire::actingAs(User::factory()->withRole(Role::STORE_KEEPER)->create())
        ->test('pages::bar.restocking')
        ->call('toggleInCart', $line->id, false);

    expect($line->fresh()->in_cart)->toBeTrue();
});

it('tells the others who is shopping, and lets them take the trip over', function (): void {
    Livewire::actingAs($this->shopper)->test('pages::bar.restocking')->call('start');
    $trip = BarRestocking::inProgress();
    $trip->lines->first()->update(['in_cart' => true]);
    $other = User::factory()->withRole(Role::STORE_KEEPER)->create();

    Livewire::actingAs($other)
        ->test('pages::bar.restocking')
        ->assertSee(__(':name is doing the shopping', ['name' => 'Aurélien Paulus']))
        ->assertDontSee('<x-', false)
        ->call('takeOver');

    expect($trip->fresh()->shopper_id)->toBe($other->id)
        ->and($trip->lines()->where('in_cart', true)->count())->toBe(1);
});

it('lets anyone abandon the trip, and the list is free again', function (): void {
    Livewire::actingAs($this->shopper)->test('pages::bar.restocking')->call('start');
    $trip = BarRestocking::inProgress();
    $other = User::factory()->withRole(Role::STORE_KEEPER)->create();

    Livewire::actingAs($other)->test('pages::bar.restocking')->call('abandon');

    expect($trip->fresh())
        ->status->toBe(BarRestocking::STATUS_ABANDONED)
        ->abandoned_by->toBe($other->id)
        ->and(BarRestocking::inProgress())->toBeNull();

    Livewire::actingAs($other)->test('pages::bar.restocking')->call('start');

    expect(BarRestocking::inProgress()->shopper_id)->toBe($other->id);
});

it('enters what was really bought into the stock, linked to the trip, and closes it', function (): void {
    $chimay = restockingTripProduct('Chimay bleue', $this->jupiler->category, stock: 0, min: 0, max: 0, packSize: 24, packLabel: 'carton');
    $chimay->update(['max_stock' => null]);   // hors réassort : pris en promo

    Livewire::actingAs($this->shopper)->test('pages::bar.restocking')->call('start');
    $trip = BarRestocking::inProgress();
    $jupilerLine = $trip->lines->firstWhere('product_id', $this->jupiler->id);
    $cocaLine = $trip->lines->firstWhere('product_id', $this->coca->id);

    Livewire::actingAs($this->shopper)
        ->test('pages::bar.restocking')
        ->call('toggleInCart', $jupilerLine->id, true)
        ->call('openClosing')
        // Coché = la quantité proposée ; non coché = « pas trouvé ».
        ->assertSet("bought.{$jupilerLine->id}", 2)
        ->assertSet("bought.{$cocaLine->id}", 0)
        ->set("bought.{$cocaLine->id}", 1)
        ->set('extraProductId', $chimay->id)
        ->call('addExtra')
        ->assertSet("extras.{$chimay->id}", 1)
        ->assertDontSee('<x-', false)
        ->call('close')
        ->assertHasNoErrors();

    expect($this->jupiler->fresh()->stock)->toBe(5 + 48)
        ->and($this->coca->fresh()->stock)->toBe(14 + 6)
        ->and($chimay->fresh()->stock)->toBe(24);

    expect($trip->fresh())
        ->status->toBe(BarRestocking::STATUS_CLOSED)
        ->closed_at->not->toBeNull();
    expect(BarStockMovement::query()->where('restocking_id', $trip->id)->sum('quantity'))->toBe(48 + 6 + 24);
    expect($jupilerLine->fresh()->bought_packs)->toBe(2)
        ->and($trip->lines()->where('section', 'extra')->where('product_id', $chimay->id)->value('bought_packs'))->toBe(1);
});

it('lets only the one shopping close the trip', function (): void {
    Livewire::actingAs($this->shopper)->test('pages::bar.restocking')->call('start');

    Livewire::actingAs(User::factory()->withRole(Role::STORE_KEEPER)->create())
        ->test('pages::bar.restocking')
        ->call('openClosing')
        ->call('close');

    expect(BarRestocking::inProgress())->not->toBeNull()
        ->and($this->jupiler->fresh()->stock)->toBe(5);
});
