<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\Events\Tournament\NewTournamentPublished;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Event;

class TournamentObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Statuses a tournament sits in before it has ever been open to members.
     *
     * The wizard walks draft → locked → published; it never goes from draft
     * straight to published, which is the only transition this observer used to
     * watch for. The announcement therefore never went out (issue #81).
     *
     * `setup` is deliberately absent: a tournament in setup has already been
     * open once and closed again, so reopening it is a reopening, not news.
     */
    private const array NEVER_OPENED = [
        TournamentStatusEnum::DRAFT,
        TournamentStatusEnum::LOCKED,
    ];

    /**
     * Handle the Tournament "updated" event.
     */
    public function updated(Tournament $tournament): void
    {
        $opensForTheFirstTime = in_array($tournament->getOriginal('status'), self::NEVER_OPENED, true)
            && $tournament->status === TournamentStatusEnum::PUBLISHED;

        if ($opensForTheFirstTime) {
            Event::dispatch(new NewTournamentPublished($tournament));
        }
    }
}
