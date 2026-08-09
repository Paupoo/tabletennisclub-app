<?php

declare(strict_types=1);

namespace App\Domains\Shared\States\Payments;

use App\Contracts\SubscriptionState;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;

class ValidatedState implements SubscriptionState
{
    public function availableTransitions(): array
    {
        return [
            'cancel' => __('Cancel'),
            'markPaid' => __('Mark as Paid'),
            'unconfirm' => __('Unconfirm'),
        ];
    }

    public function cancel(Subscription $subscription): void
    {
        // Transition autorisée : confirmed → cancelled
        $subscription->setState(new CancelledState);
    }

    public function canGeneratePayment(Subscription $subscription): bool
    {
        return true;
    }

    public function confirm(Subscription $subscription): void
    {
        // Déjà confirmée
        throw new \LogicException('Subscription is already confirmed.');
    }

    public function getStatus(): string
    {
        return 'confirmed';
    }

    public function markAsPaid(Subscription $subscription): void
    {
        // Transition autorisée : confirmed → paid
        $subscription->setState(new PaidState);
    }

    public function refund(Subscription $subscription): void
    {
        // Transition autorisée : confirmed → refunded, uniquement si un paiement (partiel) a été encaissé
        if ($subscription->totalPaid() <= 0) {
            throw new \LogicException('Cannot refund a subscription that has not been paid.');
        }

        $subscription->setState(new RefundedState);
    }

    public function unconfirm(Subscription $subscription): void
    {
        // Déjà confirmée
        $subscription->setState(new PendingState);
    }
}
