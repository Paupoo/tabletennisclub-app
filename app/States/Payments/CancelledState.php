<?php

declare(strict_types=1);

namespace App\States\Payments;

use App\Contracts\SubscriptionState;
use App\Models\Subscription;

class CancelledState implements SubscriptionState
{
    public function cancel(Subscription $subscription): void
    {
        // Déjà annulée
        throw new \LogicException('Subscription is already cancelled.');
    }

    public function confirm(Subscription $subscription): void
    {
        // État final : annulée
        throw new \LogicException('Cannot confirm a cancelled subscription.');
    }

    public function getStatus(): string
    {
        return 'cancelled';
    }

    public function markAsPaid(Subscription $subscription): void
    {
        // État final : annulée
        throw new \LogicException('Cannot mark as paid a cancelled subscription.');
    }

    public function refund(Subscription $subscription): void
    {
        // État final : annulée
        throw new \LogicException('Cannot refund a cancelled subscription.');
    }

    public function unconfirm(Subscription $subscription): void
    {
        // État final : annulée
        throw new \LogicException('Cannot set a cancelled subscription back to pending.');
    }
}
