<?php

declare(strict_types=1);

namespace App\States\Payments;

use App\Contracts\SubscriptionState;
use App\Models\Subscription;

class RefundedState implements SubscriptionState
{
    public function cancel(Subscription $subscription): void
    {
        // État final : remboursée
        throw new \LogicException('Subscription is already refunded.');
    }

    public function confirm(Subscription $subscription): void
    {
        // État final : remboursée
        throw new \LogicException('Cannot confirm a refunded subscription.');
    }

    public function getStatus(): string
    {
        return 'refunded';
    }

    public function markAsPaid(Subscription $subscription): void
    {
        // État final : remboursée
        throw new \LogicException('Cannot mark as paid a refunded subscription.');
    }

    public function refund(Subscription $subscription): void
    {
        // Déjà remboursée
        throw new \LogicException('Subscription is already refunded.');
    }

    public function unconfirm(Subscription $subscription): void
    {
        // État final : remboursée
        throw new \LogicException('Cannot set a refunded subscription back to pending.');
    }
}
