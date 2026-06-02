<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarStockMovement;
use RuntimeException;

class StockService
{
    /**
     * Create a new incoming stock movement.
     * For FIFO, remaining_quantity starts equal to quantity.
     */
    public function addIncomingStock(
        int $productId,
        int $quantity,
        ?string $reason = null,
        ?int $createdBy = null,
        ?int $modifiedBy = null
    ): BarStockMovement {
        if ($quantity <= 0) {
            throw new RuntimeException('La quantité entrante doit être supérieure à 0.');
        }

        return BarStockMovement::create([
            'product_id' => $productId,
            'quantity' => $quantity,
            'remaining_quantity' => $quantity,
            'movement_type' => BarStockMovement::TYPE_IN,
            'reason' => $reason,
            'created_by' => $createdBy,
            'modified_by' => $modifiedBy,
        ]);
    }

    /**
     * Consume stock using FIFO:
     * - oldest IN movements first
     * - decrement remaining_quantity on IN lots
     * - create one OUT movement per consumed lot, linked to source IN lot
     */
    public function consumeFIFO(
        int $productId,
        int $quantity,
        ?string $reason = null,
        ?int $createdBy = null,
        ?int $modifiedBy = null,
        ?int $orderId = null,
        ?int $orderItemId = null
    ): void {
        if ($quantity <= 0) {
            throw new RuntimeException('La quantité à sortir doit être supérieure à 0.');
        }

        $qtyToConsume = $quantity;

        $inMovements = BarStockMovement::query()
            ->where('product_id', $productId)
            ->where('movement_type', BarStockMovement::TYPE_IN)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        foreach ($inMovements as $inMovement) {
            if ($qtyToConsume <= 0) {
                break;
            }

            $available = (int) $inMovement->remaining_quantity;
            if ($available <= 0) {
                continue;
            }

            $consumed = min($available, $qtyToConsume);

            // Reduce remaining quantity on the source IN lot
            $inMovement->remaining_quantity = $available - $consumed;
            $inMovement->save();

            // Create an OUT movement linked to the consumed source IN lot
            BarStockMovement::create([
                'product_id' => $productId,
                'quantity' => $consumed,
                'remaining_quantity' => 0,
                'movement_type' => BarStockMovement::TYPE_OUT,
                'reason' => $reason,
                'created_by' => $createdBy,
                'modified_by' => $modifiedBy,
                'source_movement_id' => $inMovement->id,
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
            ]);

            $qtyToConsume -= $consumed;
        }

        if ($qtyToConsume > 0) {
            throw new RuntimeException('Stock insuffisant pour appliquer la sortie FIFO.');
        }
    }

    /**
     * Restore stock previously consumed for an order item.
     * This reverses the FIFO consumption by re-crediting the original IN lots.
     */
    public function restoreFromOrderItem(int $orderItemId, ?int $modifiedBy = null): void
    {
        $outMovements = BarStockMovement::query()
            ->where('order_item_id', $orderItemId)
            ->where('movement_type', BarStockMovement::TYPE_OUT)
            ->lockForUpdate()
            ->get();

        foreach ($outMovements as $outMovement) {
            if ($outMovement->source_movement_id) {
                $sourceIn = BarStockMovement::query()
                    ->lockForUpdate()
                    ->find($outMovement->source_movement_id);

                if ($sourceIn) {
                    $sourceIn->remaining_quantity = (int) $sourceIn->remaining_quantity + (int) $outMovement->quantity;
                    $sourceIn->modified_by = $modifiedBy;
                    $sourceIn->save();
                }
            }

            $outMovement->delete();
        }
    }
}
