<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domains\Shared\Events\Subscriptions\SubscriptionCreated;
use App\Domains\Subscriptions\Notifications\SubscriptionCreatedNotification;
use Illuminate\Support\Facades\Notification;

class SendSubscriptionConfirmationEmail
{
    public function handle(SubscriptionCreated $event): void
    {
        $subscription = $event->subscription;

        Notification::send(
            $subscription->user,
            new SubscriptionCreatedNotification($subscription)
        );
    }
}
