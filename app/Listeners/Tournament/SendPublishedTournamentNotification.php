<?php

declare(strict_types=1);

namespace App\Listeners\Tournament;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Notifications\NewTournamentPublishedNotification;
use App\Domains\Shared\Events\Tournament\NewTournamentPublished;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPublishedTournamentNotification implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(NewTournamentPublished $event): void
    {
        // foreach (User::where('is_committee_member', true)->cursor() as $user) {
        //     $user->notify(new NewTournamentPublishedNotification($event->tournament, $user));
        // }
        foreach (User::cursor() as $user) {
            $user->notify(new NewTournamentPublishedNotification($event->tournament, $user));
        }
    }
}
