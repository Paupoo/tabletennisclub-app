<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Services\BarRestockingSettings;
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

it('applies every suggestion at once, to the products nobody has set yet', function (): void {
    $coca = BarProduct::create([
        'name' => 'Coca-Cola',
        'sale_price' => 150,
        'is_available' => 1,
        'category_id' => $this->jupiler->category_id,
        'low_stock_threshold' => 5,
        'max_stock' => 30,
    ]);
    restockingSettingsSale($this->jupiler, 14);
    restockingSettingsSale($coca, 2);

    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.products')
        ->assertSee(__('Apply suggestions (:count)', ['count' => 1]))
        ->call('applyAllSuggestions')
        ->assertDontSee(__('Apply suggestions (:count)', ['count' => 1]));

    expect($this->jupiler->fresh())
        ->low_stock_threshold->toBe(14)
        ->max_stock->toBe(42);
    // Un réglage choisi à la main n'est jamais écrasé en masse.
    expect($coca->fresh())
        ->low_stock_threshold->toBe(5)
        ->max_stock->toBe(30);
});

/**
 * Le titre du toast Mary émis par le dernier appel : Mary le pousse en JS dans
 * l'effet `xjs`, jamais dans le HTML.
 */
function barRestockingToastTitle(object $component): string
{
    foreach ($component->effects['xjs'] ?? [] as $effect) {
        if (preg_match('/^toast\((.*)\)$/s', (string) ($effect['expression'] ?? ''), $matches) === 1) {
            return json_decode($matches[1], true)['toast']['title'] ?? '';
        }
    }

    return '';
}

it('turns an automatic product manual when someone corrects it by hand, and says so', function (): void {
    $this->jupiler->update(['low_stock_threshold' => 12, 'max_stock' => 48, 'restocking_mode' => 'auto']);

    $component = Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.products')
        ->call('updateMaxStock', $this->jupiler->id, '60');

    expect(barRestockingToastTitle($component))
        ->toBe(__(':product is now manual: the automatic restocking leaves it alone.', ['product' => 'Jupiler 25 cl']));

    expect($this->jupiler->fresh())
        ->max_stock->toBe(60)
        ->restocking_mode->toBe('manual');
});

it('marks the automatic products in the table', function (): void {
    $this->jupiler->update(['max_stock' => 48, 'restocking_mode' => 'auto']);

    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.products')
        ->assertSeeHtml('data-restocking="auto"');
});

it('sets how a product is restocked from its drawer: mode, own coverage and cap', function (): void {
    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.products')
        ->call('openProduct', $this->jupiler->id)
        ->set('restockingMode', 'auto')
        ->set('restockingWeeks', '1')
        ->set('restockingCap', '72')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->jupiler->fresh())
        ->restocking_mode->toBe('auto')
        ->restocking_weeks->toBe(1)
        ->restocking_cap->toBe(72);

    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.products')
        ->call('openProduct', $this->jupiler->id)
        ->set('restockingMode', '')
        ->set('restockingWeeks', '')
        ->set('restockingCap', '')
        ->call('save');

    expect($this->jupiler->fresh())
        ->restocking_mode->toBeNull()
        ->restocking_weeks->toBeNull()
        ->restocking_cap->toBeNull();
});

it('switches the whole bar to automatic and sets its coverage', function (): void {
    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.products')
        ->call('openRestockingSettings')
        ->assertSet('restockingAutomatic', false)
        ->assertSet('coverageMinWeeks', '1')
        ->assertSet('coverageMaxWeeks', '3')
        ->set('restockingAutomatic', true)
        ->set('coverageMinWeeks', '2')
        ->set('coverageMaxWeeks', '4')
        ->call('saveRestockingSettings')
        ->assertHasNoErrors();

    $settings = app(BarRestockingSettings::class);
    expect($settings->isAutomatic())->toBeTrue()
        ->and($settings->minWeeks())->toBe(2)
        ->and($settings->maxWeeks())->toBe(4);
});

it('sets the min and max from the drawer, where a phone reaches them', function (): void {
    $this->jupiler->update(['low_stock_threshold' => 12, 'max_stock' => 48]);

    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.products')
        ->call('openProduct', $this->jupiler->id)
        ->assertSet('minStock', '12')
        ->assertSet('maxStock', '48')
        ->set('minStock', '10')
        ->set('maxStock', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->jupiler->fresh())
        ->low_stock_threshold->toBe(10)
        ->max_stock->toBeNull();
});

it('turns an automatic product manual when its drawer changes the min or max, and only then', function (): void {
    $this->jupiler->update(['low_stock_threshold' => 12, 'max_stock' => 48, 'restocking_mode' => 'auto']);

    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.products')
        ->call('openProduct', $this->jupiler->id)
        ->set('price', '2,50')
        ->call('save');

    expect($this->jupiler->fresh()->restocking_mode)->toBe('auto');

    $component = Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.products')
        ->call('openProduct', $this->jupiler->id)
        ->set('maxStock', '60')
        ->call('save');

    expect(barRestockingToastTitle($component))
        ->toBe(__(':product is now manual: the automatic restocking leaves it alone.', ['product' => 'Jupiler 25 cl']));

    expect($this->jupiler->fresh())
        ->max_stock->toBe(60)
        ->restocking_mode->toBe('manual');
});
