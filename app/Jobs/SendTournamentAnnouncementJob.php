<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\NewTournamentPublishedNotification;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * One member's "a new tournament is open" announcement.
 *
 * Shares the `invitations` limiter declared in {@see AppServiceProvider} rather
 * than taking one of its own. The semantics match exactly — bulk, member-facing,
 * nobody waiting on it — and a shared key means the two non-urgent mailings queue
 * behind each other instead of adding up. Gmail counts the burst, not the job
 * type, so one bound over both is the safer arithmetic.
 *
 * Carries ids rather than models: a member archived between the fan-out and the
 * send is simply skipped, where a serialised model would fail the job.
 */
class SendTournamentAnnouncementJob implements ShouldQueue
{
    use Queueable, RetriesWhileRateLimited;

    public function __construct(public int $tournamentId, public int $userId) {}

    public function handle(): void
    {
        $tournament = Tournament::find($this->tournamentId);
        $member = User::find($this->userId);

        if ($tournament === null || $member === null) {
            return;
        }

        $member->notify(new NewTournamentPublishedNotification($tournament, $member));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('invitations')];
    }
}
