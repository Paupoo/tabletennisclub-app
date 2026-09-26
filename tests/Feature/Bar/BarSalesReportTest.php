<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Services\BarSalesReport;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Bar — ce qui se vend, pour le comité
|--------------------------------------------------------------------------
|
| Le but n'est pas la caisse mais les achats : quels produits sortent, lesquels
| dorment et risquent de périmer. Un offert compte donc comme une vente, une
| ardoise ouverte non. Les jours sont des journées d'exploitation : une vente à
| 1 h du matin appartient à la soirée de la veille.
|
*/

beforeEach(function (): void {
    $this->beers = BarCategory::create(['name' => 'Bières']);
    $this->snacks = BarCategory::create(['name' => 'Snacks']);
    $this->jupiler = barSalesReportProduct('Jupiler', $this->beers);
    $this->chimay = barSalesReportProduct('Chimay bleue', $this->beers);
    $this->chips = barSalesReportProduct('Chips paprika', $this->snacks);
});

function barSalesReportProduct(string $name, BarCategory $category): BarProduct
{
    return BarProduct::create(['name' => $name, 'sale_price' => 200, 'is_available' => 1, 'category_id' => $category->id]);
}

/**
 * @param  array<int, array{0: BarProduct, 1: int, 2?: int}>  $lines  produit, quantité, prix unitaire en cents
 */
function barSalesReportSale(string $at, array $lines, bool $paid = true, string $method = 'Cash'): void
{
    $order = new BarOrder(['total_price' => 0, 'is_paid' => $paid ? 1 : 0, 'payment_method' => $paid ? $method : null]);
    $order->created_at = Carbon::parse($at);
    $order->save();

    foreach ($lines as $line) {
        [$product, $quantity] = $line;
        $unitPrice = $line[2] ?? 200;
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $quantity,
        ]);
    }
}

it('ranks what sold over a period, compared with the period just before', function (): void {
    // Septembre 2026 : 30 jours, et août juste avant pour comparer.
    barSalesReportSale('2026-09-04 21:00', [[$this->jupiler, 20], [$this->chips, 3, 150]]);
    barSalesReportSale('2026-09-18 22:00', [[$this->jupiler, 10]], method: 'Offered');
    barSalesReportSale('2026-09-18 23:00', [[$this->jupiler, 99]], paid: false);
    barSalesReportSale('2026-08-21 21:00', [[$this->jupiler, 24], [$this->chips, 1, 150]]);
    // 1 h du matin le 1er octobre : c'est encore la soirée du 30 septembre.
    barSalesReportSale('2026-10-01 01:00', [[$this->chips, 1, 150]]);

    $report = app(BarSalesReport::class)->between(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

    expect($report)->toHaveCount(2)
        ->and($report[0]['category'])->toBe('Bières')
        ->and($report[0]['units'])->toBe(30)
        ->and($report[0]['revenue'])->toBe(6000)
        ->and($report[1]['category'])->toBe('Snacks')
        ->and($report[1]['units'])->toBe(4);

    [$jupiler, $chimay] = $report[0]['products'];

    expect($jupiler)->toMatchArray([
        'name' => 'Jupiler',
        'units' => 30,
        'revenue' => 6000,
        'weekly' => 7.0,          // 30 unités en 30 jours
        'previous_units' => 24,   // les 30 jours d'avant : 2 → 31 août
        'change' => 25,           // (30 − 24) / 24
    ]);
    // Rien vendu, ni maintenant ni avant : en bas, sans évolution à calculer.
    expect($chimay)->toMatchArray(['name' => 'Chimay bleue', 'units' => 0, 'previous_units' => 0, 'change' => null]);

    expect($report[1]['products'][0])->toMatchArray([
        'name' => 'Chips paprika',
        'units' => 4,
        'revenue' => 600,
        'previous_units' => 1,
        'change' => 300,
    ]);
});
