<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;

class SynchTrainingPack
{
    
    public function __invoke(array $trainingPacksIds, Subscription $subscription): void
    {
        $subscription->trainingPacks()->sync($trainingPacksIds);
    }
}
