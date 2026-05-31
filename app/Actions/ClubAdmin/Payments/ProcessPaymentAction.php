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

        // Met à jour le payment avec les infos du PSP
        $payment->update([
            'transaction_id' => $transactionId,
            'amount_paid' => $amount,
            'status' => $status,
        ]);

        // Si le paiement est réussi, met à jour la subscription
        if ($status === 'paid') {
            $subscription->markAsPaid();
        }

        return $subscription->fresh();
    }
}
