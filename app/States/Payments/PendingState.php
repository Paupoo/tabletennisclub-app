<?php

declare(strict_types=1);

namespace App\States\Payments;

use App\Contracts\SubscriptionState;
use App\Models\Subscription;

class PendingState implements SubscriptionState
{
    public function cancel(Subscription $subscription): void
    {
        // Transition autorisée : pending → cancelled
        $subscription->setState(new CancelledState);
    }

    public function confirm(Subscription $subscription): void
    {
        // Transition autorisée : pending → confirmed
        $subscription->setState(new ConfirmedState);
    }

    public function getStatus(): string
    {
        return 'pending';
    }

    public function markAsPaid(Subscription $subscription): void
    {
        // On ne peut pas payer directement depuis pending
        throw new \LogicException(__('Cannot mark as paid from pending status. Confirm first.'));
    }

    public function refund(Subscription $subscription): void
    {
        // On ne peut pas rembourser ce qui n'est pas payé
        throw new \LogicException('Cannot refund a pending subscription.');
    }

    public function unconfirm(Subscription $subscription): void
    {
        // L'instance est déjà pending
        throw new \LogicException(__('The subscription is already pending'));
    }

    public function availableTransitions(): array
    {
        return [
            'confirm' => __('Confirm'),
            'cancel' => __('Cancel'),
        ];
    }

    public function canGeneratePayment(Subscription $subscription): bool
    {
        return false;
    }
}
