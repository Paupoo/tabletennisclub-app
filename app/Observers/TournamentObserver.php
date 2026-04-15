<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\TournamentStatusEnum;
use App\Events\Tournament\NewTournamentPublished;
use App\Models\ClubEvents\Tournament\Tournament;
use Event;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class TournamentObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the Tournament "updated" event.
     */
    public function updated(Tournament $tournament): void
    {
        if ($tournament->getOriginal('status') === TournamentStatusEnum::DRAFT && $tournament->status === TournamentStatusEnum::PUBLISHED) {
            Event::dispatch(new NewTournamentPublished($tournament));
        }
    }
}
