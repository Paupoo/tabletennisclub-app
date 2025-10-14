<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;

class SyncTrainingPack
{
    
    public function __invoke(array $trainingPacksIds, Subscription $subscription): void
    {
        $subscription->trainingPacks()->sync($trainingPacksIds);

        new CalculatePriceAction()($subscription);
    }
}
