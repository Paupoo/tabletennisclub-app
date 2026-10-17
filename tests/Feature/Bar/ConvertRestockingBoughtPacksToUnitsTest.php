<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarRestocking;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Bar — ce qui a été acheté passe des conditionnements aux unités
|--------------------------------------------------------------------------
|
| Les tournées closes avant le changement ont noté des casiers : elles se
| relisent en unités, au conditionnement figé sur leur ligne, sans rien perdre.
|
*/

it('rereads the packs bought on a closed trip as units', function (): void {
    $migration = require base_path('database/migrations/2026_10_17_011401_convert_bar_restocking_lines_bought_packs_to_units.php');
    $migration->down();

    $category = BarCategory::create(['name' => 'Bières']);
    $jupiler = BarProduct::create(['name' => 'Jupiler', 'sale_price' => 200, 'is_available' => 1, 'category_id' => $category->id, 'pack_size' => 24]);
    $coca = BarProduct::create(['name' => 'Coca-Cola', 'sale_price' => 200, 'is_available' => 1, 'category_id' => $category->id, 'pack_size' => 6]);
    $trip = BarRestocking::create([
        'status' => BarRestocking::STATUS_CLOSED,
        'shopper_id' => User::factory()->create()->id,
        'started_at' => now()->subHour(),
        'closed_at' => now(),
    ]);
    $line = fn (BarProduct $product, ?int $boughtPacks): int => DB::table('bar_restocking_lines')->insertGetId([
        'restocking_id' => $trip->id,
        'product_id' => $product->id,
        'section' => 'to_buy',
        'stock_at_start' => 3,
        'pack_size' => $product->pack_size,
        'proposed_packs' => 2,
        'bought_packs' => $boughtPacks,
    ]);
    $bought = $line($jupiler, 2);
    $neverClosed = $line($coca, null);

    $migration->up();

    expect(DB::table('bar_restocking_lines')->find($bought)->bought_units)->toBe(48)
        ->and(DB::table('bar_restocking_lines')->find($neverClosed)->bought_units)->toBeNull();
});
