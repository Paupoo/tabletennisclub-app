<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarInventory;
use App\Domains\Bar\Models\BarInventoryLine;
use App\Domains\Bar\Models\BarOrderItem;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\Shared\Enums\BarInventoryCause;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Les pertes constatées par les inventaires, pour les ventes et pour les courses.
 *
 * Deux lectures d'une même perte :
 *
 * - l'écran des ventes la montre à la date où l'inventaire l'a constatée — c'est
 *   ce qu'on a appris sur la période ;
 * - les moyennes des courses ne gardent que les pertes parties chez quelqu'un
 *   ({@see BarInventoryCause::isDemand()}), étalées à parts égales sur les
 *   semaines avec ventes depuis le comptage précédent du produit. Posées d'un bloc
 *   sur la semaine de l'inventaire, elles feraient de la plus récente — la plus
 *   lourde — un pic, et le max s'emballerait juste après chaque inventaire.
 */
class BarLosses
{
    /**
     * La journée d'exploitation commence à 6 h : la frontière de la feuille de caisse.
     */
    public const int BUSINESS_DAY_STARTS_AT = 6;

    /** Une perte ne s'étale pas sur plus de semaines actives que les moyennes n'en regardent. */
    private const int MAX_SPREAD_WEEKS = 12;

    /**
     * La semaine d'exploitation d'un instant : une vente à 1 h appartient à la veille.
     */
    public static function weekOf(CarbonInterface|string $at): string
    {
        return Carbon::parse($at)->subHours(self::BUSINESS_DAY_STARTS_AT)->startOfWeek()->toDateString();
    }

    /**
     * Les pertes constatées entre deux instants, par produit et par cause.
     *
     * @return array<int, array<string, int>> unités perdues, par produit puis par valeur de cause
     */
    public function foundBetween(CarbonInterface $start, CarbonInterface $end): array
    {
        $losses = [];

        foreach ($this->lossLines()->filter(fn (BarInventoryLine $line): bool => $line->inventory->closed_at >= $start && $line->inventory->closed_at < $end) as $line) {
            $cause = $line->cause?->value ?? BarInventoryCause::Unknown->value;
            $losses[$line->product_id][$cause] = ($losses[$line->product_id][$cause] ?? 0) - $line->gap;
        }

        return $losses;
    }

    /**
     * Les pertes parties chez quelqu'un, étalées sur les semaines avec ventes.
     *
     * @return Collection<int, array{product_id: int, quantity: float, week: string}>
     */
    public function spreadDemand(): Collection
    {
        $lines = $this->lossLines()->filter(fn (BarInventoryLine $line): bool => $line->cause?->isDemand() ?? true);

        if ($lines->isEmpty()) {
            return collect();
        }

        $sales = $this->salesOf($lines->pluck('product_id')->unique()->all());

        return $lines->flatMap(function (BarInventoryLine $line) use ($sales): array {
            $since = $this->previousCount($line);
            $weeks = $sales
                ->filter(fn (array $sale): bool => $sale['product_id'] === $line->product_id
                    && ($since === null || $sale['sold_at'] > $since)
                    && $sale['sold_at'] <= $line->counted_at)
                ->pluck('week')->unique()->sortDesc()->take(self::MAX_SPREAD_WEEKS)->values();

            if ($weeks->isEmpty()) {
                $weeks = collect([self::weekOf($line->counted_at)]);
            }

            $share = (float) (-$line->gap / $weeks->count());

            return $weeks->map(fn (string $week): array => ['product_id' => $line->product_id, 'quantity' => $share, 'week' => $week])->all();
        })->values();
    }

    /**
     * Les manques des inventaires qui ont corrigé le stock, ce qui vient d'arriver au bar exclu.
     *
     * @return Collection<int, BarInventoryLine>
     */
    private function lossLines(): Collection
    {
        return BarInventoryLine::query()
            ->with('inventory')
            ->whereHas('inventory', fn ($query) => $query->whereIn('status', [BarInventory::STATUS_VALIDATED, BarInventory::STATUS_HISTORICAL]))
            ->whereColumn('counted', '<', 'expected')
            ->get()
            ->reject(fn (BarInventoryLine $line): bool => $line->cause === BarInventoryCause::AddedToBar);
    }

    /**
     * Quand le produit a été compté pour la dernière fois avant cette ligne, ou est entré au bar.
     */
    private function previousCount(BarInventoryLine $line): ?Carbon
    {
        $counted = BarInventoryLine::query()
            ->where('product_id', $line->product_id)
            ->where('counted_at', '<', $line->counted_at)
            ->whereHas('inventory', fn ($query) => $query->whereIn('status', [BarInventory::STATUS_VALIDATED, BarInventory::STATUS_HISTORICAL]))
            ->max('counted_at');

        $firstIn = BarStockMovement::query()
            ->where('product_id', $line->product_id)
            ->where('movement_type', BarStockMovement::TYPE_IN)
            ->min('created_at');

        $since = $counted ?? $firstIn;

        return $since === null ? null : Carbon::parse($since);
    }

    /**
     * Les ventes réglées de ces produits, avec leur semaine d'exploitation.
     *
     * @param  array<int, int>  $productIds
     * @return Collection<int, array{product_id: int, sold_at: Carbon, week: string}>
     */
    private function salesOf(array $productIds): Collection
    {
        return BarOrderItem::query()
            ->join('bar_orders', 'bar_orders.id', '=', 'bar_order_items.order_id')
            ->where('bar_orders.is_paid', 1)
            ->whereIn('bar_order_items.product_id', $productIds)
            ->get(['bar_order_items.product_id', 'bar_orders.created_at as sold_at'])
            ->map(fn (BarOrderItem $item): array => [
                'product_id' => (int) $item->product_id,
                'sold_at' => Carbon::parse($item->getAttribute('sold_at')),
                'week' => self::weekOf($item->getAttribute('sold_at')),
            ]);
    }
}
