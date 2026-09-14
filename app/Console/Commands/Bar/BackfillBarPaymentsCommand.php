<?php

declare(strict_types=1);

namespace App\Console\Commands\Bar;

use App\Actions\Bar\RecordBarOrderPayment;
use App\Domains\Bar\Models\BarOrder;
use Illuminate\Console\Command;

/**
 * Fait remonter l'historique des commandes réglées par QR dans la liste à
 * rapprocher du trésorier.
 *
 * Ces virements sont arrivés sur le compte du club sans que rien ne les relie à
 * la commande qui les a produits. La reprise les y ramène en attente, pour que
 * le rapprochement des mois passés soit rattrapable.
 *
 * Rejouable sans dommage : une commande qui a déjà son paiement est laissée
 * telle quelle. Un déploiement interrompu se relance, et un lancement de trop
 * ne donne pas deux lignes à rapprocher pour un seul virement.
 */
class BackfillBarPaymentsCommand extends Command
{
    protected $description = 'Bring bar orders already settled by QR into the treasurer\'s reconciliation list.';

    protected $signature = 'bar:backfill-payments {--dry-run : Count what would be created, write nothing}';

    public function handle(RecordBarOrderPayment $recordPayment): int
    {
        $orders = BarOrder::query()
            ->where('is_paid', true)
            ->where('payment_method', RecordBarOrderPayment::BANKED_METHOD)
            ->whereDoesntHave('payment')
            ->orderBy('paid_at')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Nothing to backfill — every QR order already has its payment.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("{$orders->count()} QR orders would be brought in.");

            return self::SUCCESS;
        }

        $created = 0;

        foreach ($orders as $order) {
            $payment = $recordPayment($order);

            if ($payment === null) {
                continue;
            }

            /*
             * Daté du soir où le bar a encaissé, pas du jour de la reprise :
             * c'est par la date que le trésorier retrouve la transaction
             * correspondante sur le relevé. Sans ça, tout l'historique arrive
             * daté du même jour et la date cesse d'aider.
             */
            $payment->forceFill([
                'created_at' => $order->paid_at,
                'updated_at' => $order->paid_at,
            ])->save();

            $created++;
        }

        $this->info("{$created} bar payments brought into the reconciliation list.");

        return self::SUCCESS;
    }
}
