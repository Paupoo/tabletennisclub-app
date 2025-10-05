<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;

class CancelSubscriptionAction
{
    public function execute(Subscription $subscription, ?string $reason = null): Subscription
    {
        // Tu peux stocker la raison dans un champ dédié ou dans un log
        
        // Délègue à l'état actuel
        $subscription->state()->cancel();

        return $subscription->fresh();
    }
}
