<?php

namespace App\Actions\Subscriptions;


use App\Models\Subscription;

class ConfirmSubscriptionAction
{
   public function execute(Subscription $subscription): Subscription
    {
        // Délègue à l'état actuel
        $subscription->state()->confirm();

        return $subscription->fresh();
    }
}
