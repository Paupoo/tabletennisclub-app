<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarOrderItem;
use App\Domains\Bar\Models\BarProduct;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ce qui s'est vendu au bar sur une période, pour guider les achats.
 *
 * Le rapport ne sert pas la caisse mais les décisions du comité : quels produits
 * sortent, lesquels dorment et finiront périmés. D'où deux choix :
 *
 * - un offert compte comme une vente — il vide le frigo tout autant ; seule une
 *   ardoise encore ouverte est laissée de côté ;
 * - chaque produit du catalogue apparaît, même sans vente : c'est précisément la
 *   ligne à zéro que le comité doit voir.
 *
 * Chaque chiffre se compare à la période de même durée qui précède, pour qu'une
 * tendance se lise sans deuxième écran.
 */
class BarSalesReport
{
    /**
     * La journée d'exploitation commence à 6 h : la frontière de la feuille de caisse.
     */
    private const int BUSINESS_DAY_STARTS_AT = 6;

    /**
     * Les ventes du premier au dernier jour inclus, par catégorie puis par produit,
     * les plus vendus en tête.
     *
     * @return array<int, array{category: string, units: int, revenue: int, products: array<int, array{id: int, name: string, units: int, revenue: int, weekly: float, previous_units: int, change: int|null}>}>
     */
    public function between(CarbonInterface $firstDay, CarbonInterface $lastDay): array
    {
        $start = $firstDay->copy()->startOfDay()->setTime(self::BUSINESS_DAY_STARTS_AT, 0);
        $end = $lastDay->copy()->startOfDay()->addDay()->setTime(self::BUSINESS_DAY_STARTS_AT, 0);
        $days = (int) round($start->diffInDays($end));

        $current = $this->salesBetween($start, $end);
        $previous = $this->salesBetween($start->copy()->subDays($days), $start);

        $rows = BarProduct::query()->with('category')->get()->map(function (BarProduct $product) use ($current, $previous, $days): array {
            $units = $current[$product->id]['units'] ?? 0;
            $previousUnits = $previous[$product->id]['units'] ?? 0;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category->name,
                'units' => $units,
                'revenue' => $current[$product->id]['revenue'] ?? 0,
                'weekly' => round($units / $days * 7, 1),
                'previous_units' => $previousUnits,
                'change' => $previousUnits === 0 ? null : (int) round(($units - $previousUnits) / $previousUnits * 100),
            ];
        });

        return $rows
            ->groupBy('category')
            ->map(fn (Collection $products, string $category): array => [
                'category' => $category,
                'units' => $products->sum('units'),
                'revenue' => $products->sum('revenue'),
                'products' => $products
                    ->sortBy([['units', 'desc'], ['name', 'asc']])
                    ->map(fn (array $row): array => array_diff_key($row, ['category' => true]))
                    ->values()
                    ->all(),
            ])
            ->sortBy([['units', 'desc'], ['category', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Unités et chiffre d'affaires par produit, commandes réglées seulement.
     *
     * Les sommes arrivent en chaînes sous MySQL : elles sont remises en entiers ici,
     * une fois, plutôt qu'à chaque lecture.
     *
     * @return array<int, array{units: int, revenue: int}>
     */
    private function salesBetween(CarbonInterface $start, CarbonInterface $end): array
    {
        $sales = [];

        BarOrderItem::query()
            ->join('bar_orders', 'bar_orders.id', '=', 'bar_order_items.order_id')
            ->where('bar_orders.is_paid', 1)
            ->where('bar_orders.created_at', '>=', $start)
            ->where('bar_orders.created_at', '<', $end)
            ->groupBy('bar_order_items.product_id')
            ->toBase()
            ->get([
                'bar_order_items.product_id',
                DB::raw('SUM(bar_order_items.quantity) as units'),
                DB::raw('SUM(bar_order_items.total_price) as revenue'),
            ])
            ->each(function (object $row) use (&$sales): void {
                $sales[(int) $row->product_id] = ['units' => (int) $row->units, 'revenue' => (int) $row->revenue];
            });

        return $sales;
    }
}
