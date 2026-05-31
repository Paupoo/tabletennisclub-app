<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Events\Tournament\NewTournamentPublished;
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
