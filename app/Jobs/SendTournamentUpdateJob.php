<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\TournamentUpdatedNotification;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use App\Providers\AppServiceProvider;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Notification;

/**
 * One player's "the date, the time or the room moved" notice.
 *
 * Same limiter as {@see SendTournamentCancellationJob} and for the same reason:
 * a player who has to be somewhere else on Saturday morning needs to know
 * today, so this shares `convocations` rather than queueing behind the
 * invitations. {@see AppServiceProvider} declares both.
 *
 * Carries ids and a list of changed fields rather than models: a member
 * archived between the fan-out and the send is simply skipped, where a
 * serialised model would fail the job.
 */
class SendTournamentUpdateJob implements ShouldQueue
{
    use Batchable, Queueable, RetriesWhileRateLimited;

    /**
     * @param  array<int, string>  $changes  Subset of: 'date', 'time', 'rooms'
     */
    public function __construct(
        public int $tournamentId,
        public int $userId,
        public array $changes,
    ) {}

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
        Notification::sendNow($player, new TournamentUpdatedNotification($tournament, $this->changes));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('convocations')];
    }
}
