<?php

declare(strict_types=1);

namespace App\States\Payments;

use const App\States\Tournament\Payments\paid;

use App\Contracts\SubscriptionState;
use App\Models\Subscription;

class PaidState implements SubscriptionState
{
    public function cancel(Subscription $subscription): void
    {
        // On ne peut pas annuler une subscription déjà payée
        // Il faut la rembourser
        throw new \LogicException('Cannot cancel a paid subscription. Refund it instead.');
    }

    public function confirm(Subscription $subscription): void
    {
        // Déjà confirmée et payée
        throw new \LogicException('Subscription is already paid.');
    }

    public function getStatus(): string
    {
        return 'paid';
    }

    public function markAsPaid(Subscription $subscription): void
    {
        // Déjà payée
        throw new \LogicException('Subscription is already paid.');
    }

    public function refund(Subscription $subscription): void
    {
        // Transition autorisée : paid → refunded
        $subscription->setState(new RefundedState);
    }

    public function unconfirm(Subscription $subscription): void
    {
        // Déjà confirmée et payée
        throw new \LogicException('Subscription is already paid.');
    }
}
