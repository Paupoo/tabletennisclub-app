<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;

class UpdateSubscriptionOptionsAction
{
    public function execute(Subscription $subscription, array $options): Subscription
    {
        // Délègue à l'état actuel
        $subscription->state()->updateOptions($options);

        // Rafraîchit le modèle
        return $subscription->fresh();
    }
}
