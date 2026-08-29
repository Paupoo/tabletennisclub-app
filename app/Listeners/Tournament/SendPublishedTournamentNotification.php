<?php

declare(strict_types=1);

namespace App\Listeners\Tournament;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Events\Tournament\NewTournamentPublished;
use App\Jobs\SendTournamentAnnouncementJob;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Announces a newly opened tournament to the club.
 *
 * Two things were wrong here and had to be fixed together (issue #81). The
 * event never fired, so this had never run; and the loop was over
 * `User::cursor()` — every row in the table, archived members and people who
 * never affiliated included — with no rate limit. Repairing the trigger alone
 * would have turned a silent bug into an unfiltered mass mailing on the first
 * tournament opened.
 *
 * The audience is now the active members, the same set every other club-wide
 * mailing uses, and the sending is fanned out over a throttled job. The
 * per-member opt-out still applies, in the notification's own `via()`.
 */
class SendPublishedTournamentNotification implements ShouldQueue
{
    public function handle(NewTournamentPublished $event): void
    {
        User::active()
            ->get()
            ->each(fn (User $member) => SendTournamentAnnouncementJob::dispatch(
                $event->tournament->id,
                $member->id,
            ));
    }
}
