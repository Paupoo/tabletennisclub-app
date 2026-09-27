<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarRestockingAdjustment;
use Illuminate\Support\Facades\DB;

/**
 * Le réassort automatique : régler le min et le max des produits sur leurs ventes.
 *
 * Décidé le 2026-09-27. Un produit est automatique quand il le dit
 * (`restocking_mode = auto`), ou quand il suit le bar et que le bar l'est — et
 * dans ce second cas seulement s'il est déjà au réassort : un produit sans max a
 * été sorti des courses par quelqu'un, l'automatique ne l'y remet pas.
 *
 * Trois retenues :
 *
 * - rien ne bouge en dessous de 15 % : 72 ne devient pas 73 puis 71 ;
 * - un produit qui ne se vend plus n'est pas touché — le digest le signale, le
 *   comité décide ;
 * - aucun passage en manuel ici : c'est une correction humaine qui l'y met.
 */
class RestockingAutomation
{
    /** L'écart relatif en deçà duquel on ne réécrit pas une valeur. */
    public const float MINIMUM_CHANGE = 0.15;

    public function __construct(
        private readonly BarRestockingSettings $settings,
        private readonly RestockingSuggestions $suggestions,
    ) {}

    /**
     * Le produit est-il réglé par l'automatique ?
     */
    public function isAutomatic(BarProduct $product): bool
    {
        return match ($product->restocking_mode) {
            'auto' => true,
            'manual' => false,
            default => $this->settings->isAutomatic() && $product->max_stock !== null,
        };
    }

    /**
     * Recalculer, et garder la trace de ce qui a bougé.
     *
     * @return list<BarRestockingAdjustment>
     */
    public function recalculate(): array
    {
        $suggestions = $this->suggestions->all();
        $adjustments = [];

        foreach (BarProduct::query()->orderBy('id')->get() as $product) {
            $suggestion = $suggestions[$product->id] ?? null;

            if ($suggestion === null || ! $this->isAutomatic($product)) {
                continue;
            }

            if (! $this->moves($product->low_stock_threshold, $suggestion['min']) && ! $this->moves($product->max_stock, $suggestion['max'])) {
                continue;
            }

            $adjustments[] = DB::transaction(function () use ($product, $suggestion): BarRestockingAdjustment {
                $adjustment = BarRestockingAdjustment::query()->create([
                    'product_id' => $product->id,
                    'old_min' => $product->low_stock_threshold,
                    'new_min' => $suggestion['min'],
                    'old_max' => $product->max_stock,
                    'new_max' => $suggestion['max'],
                ]);

                $product->update([
                    'low_stock_threshold' => $suggestion['min'],
                    'max_stock' => $suggestion['max'],
                    'restocking_adjusted_at' => now(),
                ]);

                return $adjustment;
            });
        }

        return $adjustments;
    }

    private function moves(?int $current, int $suggested): bool
    {
        if ($current === null || $current === 0) {
            return $current !== $suggested;
        }

        return abs($suggested - $current) / $current >= self::MINIMUM_CHANGE;
    }
}
