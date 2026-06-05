<?php

declare(strict_types=1);

namespace App\Listeners\Tournament;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\UserUnregisteredFromTournament as TournamentUserUnregisteredFromTournament;
use App\Domains\Shared\Events\Tournament\UserUnregisteredFromTournament;
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
