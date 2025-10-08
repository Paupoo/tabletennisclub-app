<?php

namespace App\States\Payments;

use App\Contracts\SubscriptionState;
use App\Models\Subscription;
use Exception;

class PendingState implements SubscriptionState
{
    public function unconfirm(Subscription $subscription): void
    {
        // L'instance est déjà pending
        throw new \LogicException(__('The subscription is already pending'));
    }
    
    public function confirm(Subscription $subscription): void
    {
        // Transition autorisée : pending → confirmed
        $subscription->setState(new ConfirmedState());
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

    public function cancel(Subscription $subscription): void
    {
        // Transition autorisée : pending → cancelled
        $subscription->setState(new CancelledState());
    }

    public function getStatus(): string
    {
        return 'pending';
    }
}
