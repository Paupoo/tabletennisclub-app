<?php

declare(strict_types=1);

namespace App\Domains\Shared\Events\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;

class SubscriptionCreated
{
    public function __construct(public readonly Subscription $subscription) {}
}
