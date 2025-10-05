<?php

namespace App\Actions\Payments;

use App\Models\Subscription;

class RefundSubscriptionAction
{
    public function execute(Subscription $subscription, ?string $reason = null): Subscription
    {
        // Vérifie que la subscription peut être remboursée
        if (!$subscription->state()->canTransitionTo('refunded')) {
            throw new \DomainException('Cannot refund this subscription');
        }

        // Délègue à l'état actuel
        $subscription->state()->refund();

        // Tu peux envoyer une notification, logger la raison, etc.

        return $subscription->fresh();
    }
}
