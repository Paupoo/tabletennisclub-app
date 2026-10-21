<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\Bar\Services\StockService;

function stockServiceProduct(): BarProduct
{
    $category = BarCategory::create(['name' => 'Bières']);

    return BarProduct::create([
        'name' => 'Jupiler',
        'sale_price' => 150,
        'is_available' => true,
        'category_id' => $category->id,
    ]);
}

it('rolls back every lot update when there is not enough stock', function (): void {
    $product = stockServiceProduct();
    $stockService = app(StockService::class);
    $incoming = $stockService->addIncomingStock($product->id, 2);

    expect(fn () => $stockService->consumeFIFO($product->id, 3))
        ->toThrow(RuntimeException::class, 'Stock insuffisant pour appliquer la sortie FIFO.');

    expect($incoming->fresh()->remaining_quantity)->toBe(2)
        ->and(BarStockMovement::query()->where('product_id', $product->id)->count())->toBe(1);
});

it('consumes lots in FIFO order and restores their original quantities', function (): void {
    $product = stockServiceProduct();
    $createdAt = now()->subMinute();
    $firstLot = BarStockMovement::create([
        'product_id' => $product->id,
        'quantity' => 10,
        'remaining_quantity' => 10,
        'movement_type' => BarStockMovement::TYPE_IN,
        'created_at' => $createdAt,
    ]);
    $secondLot = BarStockMovement::create([
        'product_id' => $product->id,
        'quantity' => 7,
        'remaining_quantity' => 7,
        'movement_type' => BarStockMovement::TYPE_IN,
        'created_at' => $createdAt,
    ]);
    $order = BarOrder::create(['total_price' => 1800, 'is_paid' => false]);
    $orderItem = $order->items()->create([
        'product_id' => $product->id,
        'quantity' => 12,
        'unit_price' => 150,
        'total_price' => 1800,
    ]);

    $stockService = app(StockService::class);
    $stockService->consumeFIFO($product->id, 12, orderId: $order->id, orderItemId: $orderItem->id);

    $outMovements = BarStockMovement::query()
        ->where('order_item_id', $orderItem->id)
        ->orderBy('id')
        ->get();

    expect($outMovements->map(fn (BarStockMovement $movement): array => [
        (int) $movement->source_movement_id,
        (int) $movement->quantity,
    ])->all())->toBe([
        [$firstLot->id, 10],
        [$secondLot->id, 2],
    ]);

    $stockService->restoreFromOrderItem($orderItem->id);

    expect($firstLot->fresh()->remaining_quantity)->toBe(10)
        ->and($secondLot->fresh()->remaining_quantity)->toBe(7)
        ->and(BarStockMovement::query()->where('order_item_id', $orderItem->id)->count())->toBe(0);
});
