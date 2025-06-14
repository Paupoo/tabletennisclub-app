<?php

namespace App\Listeners\Tournament;

use App\Events\Tournament\NewTournamentPublished;
use App\Models\User;
use App\Notifications\Tournament\NewTournamentPublishedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

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
        foreach (User::where('is_committee_member', true)->cursor() as $user) {
            $user->notify(new NewTournamentPublishedNotification($event->tournament));
        }
    }
}
