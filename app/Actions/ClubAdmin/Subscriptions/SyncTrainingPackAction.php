<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Models\ClubAdmin\Subscription\Subscription;
use Exception;

class SyncTrainingPackAction
{
    /**
     * @throws Exception
     */
    public function __invoke(array $trainingPacksIds, Subscription $subscription): void
    {
        // Only pending and confirmed subscriptions can be modified
        if (!in_array($subscription->status, ['pending', 'confirmed'])) {
            throw new \DomainException(
                __('The subscription cannot be modified in this state')
            );
        }

        $subscription->trainingPacks()->sync($trainingPacksIds);

        // Recalculate the price of the subscription
        new CalculatePriceAction($subscription);
    }
}
