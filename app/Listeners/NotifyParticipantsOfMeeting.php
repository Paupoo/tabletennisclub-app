<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domains\Shared\Events\Meetings\MeetingCreated;

class NotifyParticipantsOfMeeting
{
    public function handle(MeetingCreated $event): void
    {
        // Notify participants of the new meeting
        // Example: Notification::send($participants, new MeetingCreatedNotification($event->meeting));
    }
}
