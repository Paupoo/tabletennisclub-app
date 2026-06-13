<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\User\RecalculateForceListAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;

class SubscriptionObserver
{
    public function saved(Subscription $subscription): void
    {
        // wasChanged() is only populated for updates; for new records, check is_competitive directly.
        $changed = $subscription->wasRecentlyCreated
            ? $subscription->is_competitive
            : $subscription->wasChanged('is_competitive');

        if ($changed) {
            RecalculateForceListAction::handle();
        }
    }
}
