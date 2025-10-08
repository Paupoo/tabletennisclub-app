<?php

namespace App\States\Payments;

use App\Contracts\SubscriptionState;
use App\Models\Subscription;

use const App\States\Tournament\Payments\confirmed;

class ConfirmedState implements SubscriptionState
{
    public function confirm(Subscription $subscription): void
    {
        // Déjà confirmée
        throw new \LogicException('Subscription is already confirmed.');
    }

    public function unconfirm(Subscription $subscription): void
    {
        // Déjà confirmée
        $subscription->setState(new PendingState());
    }

    public function markAsPaid(Subscription $subscription): void
    {
        // Transition autorisée : confirmed → paid
        $subscription->setState(new PaidState());
    }

    public function refund(Subscription $subscription): void
    {
        // On ne peut pas rembourser ce qui n'est pas encore payé
        throw new \LogicException('Cannot refund a subscription that has not been paid.');
    }

    public function cancel(Subscription $subscription): void
    {
        // Transition autorisée : confirmed → cancelled
        $subscription->setState(new CancelledState());
    }

    public function getStatus(): string
    {
        return 'confirmed';
    }
}
