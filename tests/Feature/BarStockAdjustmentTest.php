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
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

function stockAdjustmentProduct(): BarProduct
{
    $category = BarCategory::create(['name' => 'Bières']);

    return BarProduct::create([
        'name' => 'Jupiler',
        'sale_price' => 150,
        'is_available' => true,
        'category_id' => $category->id,
    ]);
}

it('lets a barman correct one product without granting stock management', function (): void {
    Notification::fake();

    $barman = User::factory()->withRole(Role::BARMAN)->create();
    $product = stockAdjustmentProduct();
    $stockService = app(StockService::class);
    $stockService->addIncomingStock($product->id, 4);
    $stockService->consumeFIFO($product->id, 4);
    $this->withSession(['bar_tab_name' => 'Service']);

    Livewire::actingAs($barman)
        ->test('pages::bar.counter')
        ->assertSee(__('Correct stock for :product', ['product' => $product->name]))
        ->call('openStockAdjustment', $product->id)
        ->set('stockAdjustmentCount', '3')
        ->set('stockAdjustmentCause', BarInventoryCause::StockNotRecorded->value)
        ->call('applyStockAdjustment')
        ->assertHasNoErrors();

    $inventory = BarInventory::query()->sole();
    $line = BarInventoryLine::query()->sole();
    $movement = BarStockMovement::query()->where('inventory_id', $inventory->id)->sole();

    expect($barman->can(Permission::BarStockAdjust->value))->toBeTrue()
        ->and($barman->can(Permission::BarStockManage->value))->toBeFalse()
        ->and($product->fresh()->stock)->toBe(3)
        ->and($inventory->status)->toBe(BarInventory::STATUS_VALIDATED)
        ->and($inventory->opened_by)->toBe($barman->id)
        ->and($inventory->closed_by)->toBe($barman->id)
        ->and($line->expected)->toBe(0)
        ->and($line->counted)->toBe(3)
        ->and($line->cause)->toBe(BarInventoryCause::StockNotRecorded)
        ->and($movement->movement_type)->toBe(BarStockMovement::TYPE_IN)
        ->and($movement->created_by)->toBe($barman->id);
});

it('requires a cause when a counted quantity differs from recorded stock', function (): void {
    Notification::fake();

    $barman = User::factory()->withRole(Role::BARMAN)->create();
    $product = stockAdjustmentProduct();
    app(StockService::class)->addIncomingStock($product->id, 4);

    Livewire::actingAs($barman)
        ->test('pages::bar.counter')
        ->call('openStockAdjustment', $product->id)
        ->set('stockAdjustmentCount', '2')
        ->call('applyStockAdjustment')
        ->assertHasErrors('stockAdjustmentCause');

    expect($product->fresh()->stock)->toBe(4)
        ->and(BarInventory::query()->count())->toBe(0);
});

it('does not let a storekeeper use the barman correction action', function (): void {
    $storeKeeper = User::factory()->withRole(Role::STORE_KEEPER)->create();
    $product = stockAdjustmentProduct();
    $this->withSession(['bar_tab_name' => 'Service']);

    expect($storeKeeper->can(Permission::BarStockManage->value))->toBeTrue()
        ->and($storeKeeper->can(Permission::BarStockAdjust->value))->toBeFalse();

    Livewire::actingAs($storeKeeper)
        ->test('pages::bar.counter')
        ->assertDontSee(__('Correct stock for :product', ['product' => $product->name]))
        ->call('openStockAdjustment', $product->id)
        ->assertForbidden();
});
