<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Notifications\TeamCreatedNotification;
use App\Domains\Shared\Events\Interclub\TeamCreated;
use Illuminate\Support\Facades\Notification;

class SendTeamCreatedNotification
{
    public function handle(TeamCreated $event): void
    {
        $team = $event->team;

        $admins = User::where('is_admin', true)
            ->where('is_active', true)
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send(
                $admins,
                new TeamCreatedNotification($team)
            );
        }
    }
}
