<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use Exception;

class SyncTrainingPack
{

    public function __invoke(array $trainingPacksIds, Subscription $subscription): void
    {
        if ($subscription->status !== 'pending') {
            throw new Exception(__('The subscription can not be modified'));
        }
        $subscription->trainingPacks()->sync($trainingPacksIds);

        new CalculatePriceAction()($subscription);
    }
}
