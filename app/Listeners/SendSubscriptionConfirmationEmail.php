<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domains\Shared\Events\Subscriptions\SubscriptionCreated;

class SendSubscriptionConfirmationEmail
{
    public function handle(SubscriptionCreated $event): void
    {
        // Send confirmation email to user
        // Example: Mail::send(new SubscriptionConfirmationMail($event->subscription));
    }
}
