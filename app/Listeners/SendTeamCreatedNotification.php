<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domains\Shared\Events\Interclub\TeamCreated;

class SendTeamCreatedNotification
{
    public function handle(TeamCreated $event): void
    {
        // Notify admins that a new team was created
        // Example: Notification::send($admins, new TeamWasCreatedNotification($event->team));
    }
}
