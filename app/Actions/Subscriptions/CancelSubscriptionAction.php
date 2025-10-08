<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;

class CancelSubscriptionAction
{
    public function execute(Subscription $subscription): Subscription
    {
        $subscription->state()->cancel();

        return $subscription->fresh();
    }
}
