<?php

declare(strict_types=1);

namespace App\Listeners\Tournament;

use App\Events\Tournament\UserUnregisteredFromTournament;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Notifications\Tournament\UserUnregisteredFromTournament as TournamentUserUnregisteredFromTournament;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserUnregisteredToTournamentToTournament implements ShouldQueue
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
    public function handle(UserUnregisteredFromTournament $event): void
    {
        $user = User::whereId($event->user->id)->first();

        $user->notify(new TournamentUserUnregisteredFromTournament($event->tournament, $event->user));
    }
}
