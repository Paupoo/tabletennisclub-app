<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Services\RestockingSuggestions;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Bar — ce que les ventes suggèrent comme min et max
|--------------------------------------------------------------------------
|
| Min ≈ une semaine de ventes, max ≈ trois, sur les douze dernières semaines où
| le bar a vendu quelque chose : l'été et les vacances ne doivent pas faire
| chuter les moyennes. Un offert compte comme une vente — il vide le frigo tout
| autant ; une ardoise encore ouverte ne compte pas.
|
*/

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-26 12:00'));

    $category = BarCategory::create(['name' => 'Bières']);
    $this->jupiler = restockingSuggestionsProduct('Jupiler', $category);
    $this->coca = restockingSuggestionsProduct('Coca-Cola', $category);
});

function restockingSuggestionsProduct(string $name, BarCategory $category): BarProduct
{
    return BarProduct::create([
        'name' => $name,
        'sale_price' => 200,
        'is_available' => 1,
        'category_id' => $category->id,
    ]);
}

/**
 * @param  array<int, array{0: BarProduct, 1: int}>  $lines
 */
function restockingSuggestionsSale(string $at, array $lines, bool $paid = true, ?string $method = 'Cash'): void
{
    $order = new BarOrder(['total_price' => 0, 'is_paid' => $paid ? 1 : 0, 'payment_method' => $paid ? $method : null]);
    $order->created_at = Carbon::parse($at);
    $order->save();

    foreach ($lines as [$product, $quantity]) {
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => 200,
            'total_price' => 200 * $quantity,
        ]);
    }
}

it('suggests one week of sales as min and three as max, over the weeks the bar sold', function (): void {
    // Trois semaines actives, séparées par des semaines creuses qui ne comptent pas.
    restockingSuggestionsSale('2026-09-18 21:00', [[$this->jupiler, 10], [$this->coca, 5]]);
    restockingSuggestionsSale('2026-08-28 21:00', [[$this->jupiler, 20]]);
    restockingSuggestionsSale('2026-07-03 21:00', [[$this->jupiler, 12]]);

    $suggestions = app(RestockingSuggestions::class)->all();

    // Jupiler : 42 en 3 semaines = 14 par semaine.
    expect($suggestions[$this->jupiler->id])->toBe(['min' => 14, 'max' => 42]);
    // Coca : 5 en 3 semaines = 1,67 par semaine, arrondi au-dessus.
    expect($suggestions[$this->coca->id])->toBe(['min' => 2, 'max' => 5]);
});

it('counts what was offered and leaves out a tab still open', function (): void {
    restockingSuggestionsSale('2026-09-18 21:00', [[$this->jupiler, 6]], method: 'Offered');
    restockingSuggestionsSale('2026-09-18 22:00', [[$this->jupiler, 100]], paid: false);

    expect(app(RestockingSuggestions::class)->all()[$this->jupiler->id])->toBe(['min' => 6, 'max' => 18]);
});

it('looks at the last twelve weeks the bar sold, however far back they go', function (): void {
    // Douze semaines actives d'une Coca chacune, un vendredi sur deux…
    foreach (range(0, 11) as $fortnight) {
        restockingSuggestionsSale(Carbon::parse('2026-09-18 21:00')->subWeeks(2 * $fortnight)->toDateTimeString(), [[$this->coca, 1]]);
    }
    // … et une treizième, plus ancienne, qui ne doit plus peser.
    restockingSuggestionsSale('2025-12-05 21:00', [[$this->jupiler, 100]]);

    $suggestions = app(RestockingSuggestions::class)->all();

    expect($suggestions[$this->coca->id])->toBe(['min' => 1, 'max' => 3])
        ->and($suggestions)->not->toHaveKey($this->jupiler->id);
});

it('suggests nothing for a product that never sold', function (): void {
    restockingSuggestionsSale('2026-09-18 21:00', [[$this->coca, 4]]);

    expect(app(RestockingSuggestions::class)->all())->not->toHaveKey($this->jupiler->id);
});
