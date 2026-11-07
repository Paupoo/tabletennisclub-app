<?php

declare(strict_types=1);

namespace App\Actions\Bar;

use App\Domains\Bar\Models\BarOrder;
use Illuminate\Support\Facades\DB;

/**
 * Régler une commande du bar : cash, QR ou verre du club.
 *
 * L'écran d'encaissement et le comptoir règlent par le même chemin, pour qu'une
 * commande ne puisse pas être payée deux fois parce que deux écrans se croisent :
 * le verrou de ligne et la relecture de `is_paid` tiennent ensemble.
 */
class SettleBarOrder
{
    public function __construct(private readonly RecordBarOrderPayment $recordPayment) {}

    /**
     * @return bool false si la commande était déjà réglée
     */
    public function __invoke(BarOrder $order, string $method, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($order, $method, $reason): bool {
            $order = BarOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->is_paid) {
                return false;
            }

            $order->update([
                'is_paid' => 1,
                'paid_at' => now(),
                'payment_method' => $method,
                'reason' => $method === 'offered' ? $reason : null,
                // L'ardoise est réglée : son nom se libère pour la prochaine tournée, sans
                // que l'historique le perde — `name` reste, seule la clé d'unicité part.
                'open_name_key' => null,
            ]);

            // Le QR, sa réservation ou la libération d'une réservation concurrente
            // font partie de la même transaction que le règlement.
            ($this->recordPayment)($order);

            return true;
        });
    }
}
