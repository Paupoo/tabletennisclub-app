<?php

namespace App\Listeners;

use App\Events\NewTournamentPublished;
use App\Models\User;
use App\Notifications\NewTournamentPublishedNotification;
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
