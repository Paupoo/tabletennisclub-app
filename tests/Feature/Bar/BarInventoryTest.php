<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarInventory;
use App\Domains\Bar\Models\BarInventoryLine;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\Bar\Services\StockService;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\BarInventoryCause;
use App\Domains\Shared\Enums\Role;
use App\Support\Help\HelpAudience;
use App\Support\Help\HelpLibrary;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Bar — l'inventaire
|--------------------------------------------------------------------------
|
| Le stock ne se corrige plus d'un champ : on ouvre un inventaire, on compte ce
| qui est sur l'étagère, on dit ce qui s'est passé quand le nombre ne tombe pas
| juste, et on valide une fois. Rien ne touche au stock avant la validation.
|
*/

beforeEach(function (): void {
    $this->storeKeeper = User::factory()->withRole(Role::STORE_KEEPER)->create(['first_name' => 'Marc', 'last_name' => 'Dubois']);
    $beers = BarCategory::create(['name' => 'Bières']);
    $this->jupiler = inventoryProduct('Jupiler', $beers, stock: 12);
});

function inventoryProduct(string $name, BarCategory $category, int $stock, int $price = 150): BarProduct
{
    $product = BarProduct::create([
        'name' => $name,
        'sale_price' => $price,
        'is_available' => 1,
        'category_id' => $category->id,
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

function inventoryStockOf(BarProduct $product): int
{
    return BarProduct::query()->withStock()->findOrFail($product->id)->stock;
}

it('lowers the stock by the gap once the inventory is validated', function (): void {
    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $this->jupiler->id, '8')
        ->call('saveCause', $this->jupiler->id, BarInventoryCause::Broken->value)
        ->call('validateInventory')
        ->assertHasNoErrors();

    expect(inventoryStockOf($this->jupiler))->toBe(8);
});

it('keeps the sales made after the count, because the gap is read when the number is typed', function (): void {
    $component = Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $this->jupiler->id, '8')
        ->call('saveCause', $this->jupiler->id, BarInventoryCause::Unknown->value);

    // Trois Jupiler vendues au comptoir pendant qu'on finit de compter le reste.
    app(StockService::class)->consumeFIFO($this->jupiler->id, 3, 'Sale');

    $component->call('validateInventory')->assertHasNoErrors();

    expect(inventoryStockOf($this->jupiler))->toBe(5);
});

it('refuses to validate while a gap has no cause, and touches nothing', function (): void {
    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $this->jupiler->id, '8')
        ->call('validateInventory')
        ->assertHasErrors('causes');

    expect(inventoryStockOf($this->jupiler))->toBe(12)
        ->and(BarInventory::inProgress())->not->toBeNull();
});

it('marks the first count of a product that never had stock as added to the bar, without asking why', function (): void {
    $lemonCoke = inventoryProduct('Coca-Cola citron', BarCategory::create(['name' => 'Softs']), stock: 0);

    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $lemonCoke->id, '24')
        ->call('validateInventory')
        ->assertHasNoErrors();

    expect(inventoryStockOf($lemonCoke))->toBe(24)
        ->and(BarInventoryLine::query()->where('product_id', $lemonCoke->id)->value('cause'))->toBe(BarInventoryCause::AddedToBar);
});

it('only accepts a cause that fits the direction of the gap', function (): void {
    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $this->jupiler->id, '8')
        ->call('saveCause', $this->jupiler->id, BarInventoryCause::ReceivedOutsideShopping->value)
        ->assertHasErrors('causes')
        ->call('saveCount', $this->jupiler->id, '15')
        ->call('saveCause', $this->jupiler->id, BarInventoryCause::Broken->value)
        ->assertHasErrors('causes')
        ->call('saveCause', $this->jupiler->id, BarInventoryCause::ReceivedOutsideShopping->value)
        ->assertHasNoErrors()
        ->call('validateInventory')
        ->assertHasNoErrors();

    expect(inventoryStockOf($this->jupiler))->toBe(15);
});

it('forgets the cause when a new count turns the gap the other way', function (): void {
    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $this->jupiler->id, '8')
        ->call('saveCause', $this->jupiler->id, BarInventoryCause::Broken->value)
        ->call('saveCount', $this->jupiler->id, '15')
        ->call('validateInventory')
        ->assertHasErrors('causes');

    expect(inventoryStockOf($this->jupiler))->toBe(12);
});

it('reads an emptied count as not counted, never as zero', function (): void {
    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $this->jupiler->id, '8')
        ->call('saveCount', $this->jupiler->id, '')
        ->call('validateInventory')
        ->assertHasNoErrors();

    expect(inventoryStockOf($this->jupiler))->toBe(12);
});

it('refuses a negative count', function (): void {
    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $this->jupiler->id, '-3')
        ->assertHasErrors('counts')
        ->call('validateInventory');

    expect(inventoryStockOf($this->jupiler))->toBe(12);
});

it('writes no stock movement for a count that is already right', function (): void {
    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $this->jupiler->id, '12')
        ->call('validateInventory')
        ->assertHasNoErrors();

    expect(BarStockMovement::query()->where('product_id', $this->jupiler->id)->count())->toBe(1);
});

it('keeps a single inventory at a time, where everyone who manages the stock counts', function (): void {
    $other = User::factory()->withRole(Role::STORE_KEEPER)->create();

    Livewire::actingAs($this->storeKeeper)->test('pages::bar.inventory')->call('open');

    Livewire::actingAs($other)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $this->jupiler->id, '8')
        ->call('saveCause', $this->jupiler->id, BarInventoryCause::Expired->value)
        ->call('validateInventory')
        ->assertHasNoErrors();

    expect(BarInventory::query()->count())->toBe(1)
        ->and(inventoryStockOf($this->jupiler))->toBe(8);
});

it('lets anyone cancel the inventory in progress, and the stock stays as it was', function (): void {
    $other = User::factory()->withRole(Role::STORE_KEEPER)->create();

    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $this->jupiler->id, '8');

    Livewire::actingAs($other)
        ->test('pages::bar.inventory')
        ->call('cancel')
        ->assertRedirect(route('bar.inventories.index'));

    Livewire::actingAs($other)
        ->test('pages::bar.inventories')
        ->assertSee(__('Cancelled by :name', ['name' => $other->full_name]));

    expect(inventoryStockOf($this->jupiler))->toBe(12)
        ->and(BarInventory::inProgress())->toBeNull();
});

it('freezes a validated inventory, its selling price included', function (): void {
    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $this->jupiler->id, '8')
        ->call('saveCause', $this->jupiler->id, BarInventoryCause::Broken->value)
        ->call('saveNote', $this->jupiler->id, 'Carton tombé au vestiaire')
        ->call('validateInventory');

    $inventory = BarInventory::query()->sole();
    $this->jupiler->update(['sale_price' => 300]);

    // Plus rien n'est en cours : un nouveau comptage n'atteint pas l'inventaire validé.
    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('saveCount', $this->jupiler->id, '2');

    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory-show', ['inventory' => $inventory])
        ->assertSee('Jupiler')
        ->assertSee(BarInventoryCause::Broken->label())
        ->assertSee('Carton tombé au vestiaire')
        ->assertSee(euros(-600));

    expect(inventoryStockOf($this->jupiler))->toBe(8);
});

it('never sells more than what is left when the bar sold the counted units meanwhile', function (): void {
    $component = Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $this->jupiler->id, '2')
        ->call('saveCause', $this->jupiler->id, BarInventoryCause::Unknown->value);

    // Le comptoir encaisse plus que ce qui restait au comptage : le compte était faux,
    // mais la validation ne doit ni casser ni descendre sous zéro.
    app(StockService::class)->consumeFIFO($this->jupiler->id, 11, 'Sale');

    $component->call('validateInventory')->assertHasNoErrors();

    expect(inventoryStockOf($this->jupiler))->toBe(0);
});

it('lets the store keeper count, the committee read, and keeps the barman out', function (): void {
    $committee = User::factory()->isCommitteeMember()->create();
    $barman = User::factory()->withRole(Role::BARMAN)->create();

    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $this->jupiler->id, '12')
        ->call('validateInventory');
    $inventory = BarInventory::query()->sole();

    $this->actingAs($this->storeKeeper)->get(route('bar.inventories.current'))->assertOk();
    $this->actingAs($this->storeKeeper)->get(route('bar.inventories.index'))->assertOk();

    $this->actingAs($committee)->get(route('bar.inventories.index'))->assertOk()->assertDontSee(__('New inventory'));
    $this->actingAs($committee)->get(route('bar.inventories.show', $inventory))->assertOk();
    $this->actingAs($committee)->get(route('bar.inventories.current'))->assertForbidden();

    $this->actingAs($barman)->get(route('bar.inventories.index'))->assertForbidden();
    $this->actingAs($barman)->get(route('bar.inventories.current'))->assertForbidden();
});

it('shows a cancelled inventory in the history but opens no detail for it', function (): void {
    Livewire::actingAs($this->storeKeeper)->test('pages::bar.inventory')->call('open')->call('cancel');

    $this->actingAs($this->storeKeeper)
        ->get(route('bar.inventories.show', BarInventory::query()->sole()))
        ->assertNotFound();
});

it('refuses every counting gesture to whoever does not manage the stock', function (): void {
    $committee = User::factory()->isCommitteeMember()->create();

    Livewire::actingAs($committee)->test('pages::bar.inventory')->assertForbidden();
});

it('shows the inventory help to who counts and to who reads, never to the barman', function (): void {
    $slugsFor = fn (User $user): array => collect(HelpLibrary::visibleTo(HelpAudience::for($user)))->pluck('slug')->all();

    expect($slugsFor($this->storeKeeper))->toContain('faire-l-inventaire-du-bar')
        ->and($slugsFor(User::factory()->isCommitteeMember()->create()))->toContain('faire-l-inventaire-du-bar')
        ->and($slugsFor(User::factory()->withRole(Role::BARMAN)->create()))->not->toContain('faire-l-inventaire-du-bar');
});

it('counts every product with a gap in the filter, the ones added to the bar included', function (): void {
    $softs = BarCategory::create(['name' => 'Softs']);
    $lemonCoke = inventoryProduct('Coca-Cola citron', $softs, stock: 0);
    $orangina = inventoryProduct('Orangina', $softs, stock: 0);

    Livewire::actingAs($this->storeKeeper)
        ->test('pages::bar.inventory')
        ->call('open')
        ->call('saveCount', $this->jupiler->id, '8')
        ->call('saveCount', $lemonCoke->id, '24')
        ->call('saveCount', $orangina->id, '12')
        ->assertSee(__('With a gap · :count', ['count' => 3]));
});
