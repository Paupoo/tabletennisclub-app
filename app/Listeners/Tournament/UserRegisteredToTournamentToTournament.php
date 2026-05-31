<?php

declare(strict_types=1);

namespace App\Listeners\Tournament;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\UserRegisteredToTournament as NotificationsUserRegisteredToTournament;
use App\Domains\Shared\Events\Tournament\UserRegisteredToTournament;
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
