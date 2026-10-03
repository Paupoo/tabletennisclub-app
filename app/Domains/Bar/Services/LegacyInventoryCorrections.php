<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarInventory;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\Shared\Enums\BarInventoryCause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Les corrections faites avant l'inventaire, reprises en inventaires « Historique ».
 *
 * L'ancien champ Stock écrivait un mouvement `Inventory count` par écart, avec un
 * auteur et une heure, mais rien sur ce qui s'était passé. On regroupe par auteur
 * et par journée — une soirée d'inventaire faite d'un seul geste — et chaque ligne
 * dit « Je ne sais pas ». La valeur est estimée au prix d'aujourd'hui, faute de
 * prix figé à l'époque. Rien n'est envoyé, et le stock ne bouge pas : les
 * mouvements existent déjà, on les relie seulement à leur inventaire.
 *
 * Rejouable sans doublon : un mouvement déjà relié n'est plus repris.
 */
class LegacyInventoryCorrections
{
    public const string LEGACY_REASON = 'Inventory count';

    /**
     * @return int le nombre d'inventaires créés
     */
    public function import(): int
    {
        $groups = BarStockMovement::query()
            ->where('reason', self::LEGACY_REASON)
            ->whereNull('inventory_id')
            ->whereNull('order_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (BarStockMovement $movement): string => ($movement->created_by ?? 'nobody') . '|' . $movement->created_at?->toDateString());

        $prices = BarProduct::query()->pluck('sale_price', 'id');

        foreach ($groups as $movements) {
            DB::transaction(fn () => $this->importGroup($movements, $prices));
        }

        return $groups->count();
    }

    /**
     * @param  Collection<int, BarStockMovement>  $movements
     * @param  Collection<int, int>  $prices
     */
    private function importGroup(Collection $movements, Collection $prices): void
    {
        $first = $movements->first();

        $inventory = BarInventory::query()->create([
            'status' => BarInventory::STATUS_HISTORICAL,
            'opened_by' => $first->created_by,
            'closed_by' => $first->created_by,
            'opened_at' => $first->created_at,
            'closed_at' => $movements->last()->created_at,
        ]);

        foreach ($movements->groupBy('product_id') as $productId => $ofProduct) {
            $gap = (int) $ofProduct->sum(fn (BarStockMovement $movement): int => $movement->signed_quantity);

            if ($gap === 0) {
                continue;
            }

            $expected = $this->stockBefore((int) $productId, $ofProduct->first());

            $inventory->lines()->create([
                'product_id' => $productId,
                'expected' => $expected,
                'counted' => max(0, $expected + $gap),
                'counted_by' => $first->created_by,
                'counted_at' => $ofProduct->first()->created_at,
                'cause' => BarInventoryCause::Unknown,
                'unit_price' => (int) ($prices[$productId] ?? 0),
            ]);
        }

        BarStockMovement::query()->whereKey($movements->pluck('id')->all())->update(['inventory_id' => $inventory->id]);
    }

    /**
     * Le stock du produit juste avant la première correction de la journée.
     */
    private function stockBefore(int $productId, BarStockMovement $first): int
    {
        $before = BarStockMovement::query()
            ->where('product_id', $productId)
            ->where(fn ($query) => $query->where('created_at', '<', $first->created_at)
                ->orWhere(fn ($query) => $query->where('created_at', $first->created_at)->where('id', '<', $first->id)))
            ->selectRaw('SUM(CASE WHEN movement_type = ? THEN quantity ELSE -quantity END) as stock', [BarStockMovement::TYPE_IN])
            ->value('stock');

        return (int) $before;
    }
}
