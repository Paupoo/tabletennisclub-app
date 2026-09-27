<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\Bar\Services\RestockingList;

/*
|--------------------------------------------------------------------------
| Bar — la liste de courses
|--------------------------------------------------------------------------
|
| Un produit entre dans « À acheter » quand son stock est au min ou en dessous,
| dans « Si tu as la place » quand il est entre le min et le max. Sans max, il
| n'entre jamais. La quantité remonte au max, arrondie au conditionnement
| supérieur : le max est une cible, pas un plafond.
|
*/

function restockingListProduct(string $name, BarCategory $category, int $stock, ?int $min, ?int $max, int $packSize = 1, ?string $packLabel = null): BarProduct
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

it('splits what must be bought from what fits if there is room, in whole packs', function (): void {
    $beers = BarCategory::create(['name' => 'Bières']);
    $softs = BarCategory::create(['name' => 'Softs']);

    restockingListProduct('Jupiler', $beers, stock: 5, min: 12, max: 48, packSize: 24, packLabel: 'casier');
    restockingListProduct('Coca-Cola', $softs, stock: 14, min: 6, max: 30, packSize: 6, packLabel: 'pack');
    restockingListProduct('Ice Tea', $softs, stock: 3, min: null, max: 10);   // min par défaut du bar : 3
    restockingListProduct('Fanta', $softs, stock: 20, min: 5, max: 20);       // plein
    restockingListProduct('Chips', $softs, stock: 0, min: 2, max: null);      // hors réassort

    $list = app(RestockingList::class)->current();

    expect($list['to_buy'])->toHaveCount(2)
        ->and($list['to_buy'][0])->toMatchArray([
            'name' => 'Jupiler', 'category' => 'Bières', 'stock' => 5, 'max' => 48,
            'pack_size' => 24, 'pack_label' => 'casier',
            'packs' => 2,   // 43 manquent : un casier n'y suffit pas
            'units' => 48,
        ])
        ->and($list['to_buy'][1])->toMatchArray(['name' => 'Ice Tea', 'packs' => 7, 'units' => 7]);

    expect($list['if_room'])->toHaveCount(1)
        ->and($list['if_room'][0])->toMatchArray(['name' => 'Coca-Cola', 'packs' => 3, 'units' => 18]);
});
