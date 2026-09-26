<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Bar — les réglages du réassort
|--------------------------------------------------------------------------
|
| Le min est le seuil d'alerte qui existait déjà : un seul chiffre décide à la
| fois de l'alerte au comptoir et de l'entrée dans la liste de courses. Le max
| est la cible des courses ; sans max, un produit n'entre jamais dans la liste
| (saisonnier, retiré, acheté à l'occasion).
|
*/

beforeEach(function (): void {
    $this->storeKeeper = User::factory()->withRole(Role::STORE_KEEPER)->create();
    $this->jupiler = BarProduct::create([
        'name' => 'Jupiler 25 cl',
        'sale_price' => 200,
        'is_available' => 1,
        'category_id' => BarCategory::create(['name' => 'Bières'])->id,
    ]);
});

it('sets the restocking target of a product, and an emptied field takes it out of restocking', function (): void {
    expect($this->jupiler->max_stock)->toBeNull();

    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.products')
        ->call('updateMaxStock', $this->jupiler->id, '48');

    expect($this->jupiler->fresh()->max_stock)->toBe(48);

    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.products')
        ->call('updateMaxStock', $this->jupiler->id, '');

    expect($this->jupiler->fresh()->max_stock)->toBeNull();
});

it('shows the restocking target next to the stock on the products screen', function (): void {
    $this->jupiler->update(['max_stock' => 48]);

    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.products')
        ->assertSeeHtml('aria-label="' . e(__('Restocking target for :product', ['product' => 'Jupiler 25 cl'])) . '"')
        ->assertSeeHtml('value="48"');
});

it('records how a product is bought, one unit when nobody says otherwise', function (): void {
    expect($this->jupiler->fresh()->pack_size)->toBe(1);

    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.products')
        ->call('openProduct', $this->jupiler->id)
        ->set('packSize', '24')
        ->set('packLabel', 'casier')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->jupiler->fresh())
        ->pack_size->toBe(24)
        ->pack_label->toBe('casier');
});

it('refuses a pack of no unit', function (): void {
    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.products')
        ->call('openProduct', $this->jupiler->id)
        ->set('packSize', '0')
        ->call('save')
        ->assertHasErrors('packSize');

    expect($this->jupiler->fresh()->pack_size)->toBe(1);
});

/**
 * Une commande réglée vendredi dernier : de quoi donner une suggestion au produit.
 */
function restockingSettingsSale(BarProduct $product, int $quantity): void
{
    $order = BarOrder::create(['total_price' => 0, 'is_paid' => 1, 'payment_method' => 'Cash']);
    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => $quantity,
        'unit_price' => 200,
        'total_price' => 200 * $quantity,
    ]);
}

it('offers what sales suggest, and applies it to one product on request', function (): void {
    restockingSettingsSale($this->jupiler, 14);

    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.products')
        ->assertSee(__('Suggested: :min – :max', ['min' => 14, 'max' => 42]))
        ->call('applySuggestion', $this->jupiler->id);

    expect($this->jupiler->fresh())
        ->low_stock_threshold->toBe(14)
        ->max_stock->toBe(42);
});
