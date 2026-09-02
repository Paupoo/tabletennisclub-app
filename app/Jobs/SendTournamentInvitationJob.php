<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Notifications\TournamentInvitationNotification;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use App\Providers\AppServiceProvider;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Notification;

/**
 * One member's invitation to a tournament, queued so the club never sends the
 * whole mailing list at once.
 *
 * The wizard's step 4 was the last mass mailing in the application still looping
 * over `notify()` in the request: inviting the club sent a hundred and forty
 * three near identical messages in as long as the worker took to drain them,
 * which is the burst Gmail is built to catch. Every other club-wide mailing had
 * already been fanned out over a limiter — this one was simply missed.
 *
 * Shares the `invitations` limiter declared in {@see AppServiceProvider} with
 * {@see SendMemberInvitationJob} and {@see SendTournamentAnnouncementJob},
 * rather than taking one of its own: the semantics match exactly, and a shared
 * key means the non-urgent mailings queue behind each other instead of adding
 * up. Gmail counts the burst, not the job type.
 *
 * Carries ids and scalars rather than models: a member archived between the
 * fan-out and the send is simply skipped, where a serialised model would fail
 * the job.
 */
class SendTournamentInvitationJob implements ShouldQueue
{
    use Batchable, Queueable, RetriesWhileRateLimited;

    public function __construct(
        public int $tournamentId,
        public int $userId,
        public string $customMessage = '',
        public bool $includeArticleLink = false,
        public ?int $newsPostId = null,
    ) {}

    public function handle(): void
    {
        $tournament = Tournament::find($this->tournamentId);
        $member = User::find($this->userId);

        if ($tournament === null || $member === null) {
            return;
        }

        /*
         * sendNow() and not notify(): the notification is itself ShouldQueue, so
         * notify() would only push a second, unthrottled job and return — the
         * limiter would pace the dispatching rather than the sending. Sending
         * inside the throttled job is what makes the fifteen a minute a fact
         * about the mail leaving, which is the number Gmail is counting.
         */
        Notification::sendNow($member, new TournamentInvitationNotification(
            tournament: $tournament,
            customMessage: $this->customMessage,
            includeArticleLink: $this->includeArticleLink,
            newsPostId: $this->newsPostId,
        ));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('invitations')];
    }
}
