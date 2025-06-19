<?php

declare(strict_types=1);

namespace App\Listeners\Tournament;

use App\Events\Tournament\UserRegisteredToTournament;
use App\Models\Tournament;
use App\Models\User;
use App\Notifications\Tournament\UserRegisteredToTournament as NotificationsUserRegisteredToTournament;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserRegisteredToTournamentToTournament implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct(public Tournament $tournament, public User $user)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserRegisteredToTournament $event): void
    {
        $user = User::whereId($event->user->id)->first();

        $user->notify(new NotificationsUserRegisteredToTournament($event->tournament, $event->user));
    }
}
