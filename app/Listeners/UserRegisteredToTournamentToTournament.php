<?php

namespace App\Listeners;

use App\Events\UserRegisteredToTournament;
use App\Models\Tournament;
use App\Models\User;
use App\Notifications\UserRegisteredToTournament as NotificationsUserRegisteredToTournament;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UserRegisteredToTournamentToTournament
{
    /**
     * Create the event listener.
     */
    public function __construct(public Tournament $tournament, public  User $user)
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
