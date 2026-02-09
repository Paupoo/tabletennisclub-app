<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Models\ClubAdmin\Subscription\Subscription;
use Exception;

use function App\Actions\Subscriptions\__;

class SyncTrainingPackAction
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
