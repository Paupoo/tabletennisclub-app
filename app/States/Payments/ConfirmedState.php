<?php

declare(strict_types=1);

namespace App\States\Payments;

use const App\States\Tournament\Payments\confirmed;

use App\Contracts\SubscriptionState;
use App\Models\Subscription;

class ConfirmedState implements SubscriptionState
{
    public function cancel(Subscription $subscription): void
    {
        // Transition autorisée : confirmed → cancelled
        $subscription->setState(new CancelledState);
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
        // On ne peut pas rembourser ce qui n'est pas encore payé
        throw new \LogicException('Cannot refund a subscription that has not been paid.');
    }

    public function unconfirm(Subscription $subscription): void
    {
        // Déjà confirmée
        $subscription->setState(new PendingState);
    }

    public function availableTransitions(): array
    {
        return [
            'cancel' => __('Cancel'),
            'markPaid' => __('Mark as Paid'),
            'unconfirm' => __('Unconfirm'),
        ];
    }

    public function canGeneratePayment(Subscription $subscription): bool
    {
        return true;
    }
}
