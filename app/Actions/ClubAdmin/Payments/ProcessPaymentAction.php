<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;

class ProcessPaymentAction
{
    public function execute(
        Subscription $subscription,
        string $transactionId,
        float $amount,
        string $status = 'paid'
    ): Subscription {
        // Trouve le payment en attente
        $payment = $subscription->payments()
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (! $payment) {
            throw new \DomainException('No pending payment found');
        }

        $payment->update([
            'transaction_id' => $transactionId,
            'status' => $status,
        ]);

        // `amount_paid` est le miroir des lignes de crédit et n'est écrit que
        // par AllocateTransactionAction. L'identifiant reçu ici vient d'un
        // prestataire de paiement, pas d'un relevé bancaire : c'est un
        // encaissement sans transaction, et il se note comme tel.
        if ($status === 'paid') {
            (new AllocateTransactionAction)->credit($payment, $amount, 'psp');

            $subscription->markAsPaid();
        }

        return $subscription->fresh();
    }
}
