<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\TournamentCancelledNotification;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Notification;

/**
 * One player's "the tournament is off" notice.
 *
 * Takes the `convocations` limiter and not `invitations`, which is the whole
 * reason the club has two. A cancellation carries a date the player had blocked
 * out: a full draw of sixty four reaches everybody in a couple of minutes
 * rather than sitting three quarters of an hour behind an invitation mailing
 * nobody is waiting on.
 *
 * Carries ids rather than models: a member archived between the fan-out and the
 * send is simply skipped, where a serialised model would fail the job.
 */
class SendTournamentCancellationJob implements ShouldQueue
{
    use Batchable, Queueable, RetriesWhileRateLimited;

    public function __construct(public int $tournamentId, public int $userId) {}

    public function handle(): void
    {
        $tournament = Tournament::find($this->tournamentId);
        $player = User::find($this->userId);

        if ($tournament === null || $player === null) {
            return;
        }

        // sendNow() so the mail leaves inside the throttled job: notify() would
        // hand a ShouldQueue notification to a second, unthrottled job, and the
        // limiter would pace the dispatching rather than the sending.
        Notification::sendNow($player, new TournamentCancelledNotification($tournament));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('convocations')];
    }
}
