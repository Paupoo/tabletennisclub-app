<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\MembershipStatus;
use App\Domains\Subscriptions\Notifications\RenewalReminderNotification;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use App\Providers\AppServiceProvider;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Notification;

/**
 * One member's renewal reminder, queued behind the club's other mailings.
 *
 * Shares the `invitations` limiter declared in {@see AppServiceProvider}: an
 * end-of-summer round to last season's members is the same burst, as Gmail
 * sees it, as a round of invitations.
 *
 * At fifteen a minute the last of a long round leaves well after the click, so
 * the member is read again here: one who registered or declared their
 * departure in the meantime is no longer to follow up, and a reminder would
 * only tell them the club had not noticed. Carries an id for the same reason
 * as {@see SendMemberInvitationJob}: a member archived in between is skipped.
 */
class SendRenewalReminderJob implements ShouldQueue
{
    use Batchable, Queueable, RetriesWhileRateLimited;

    public function __construct(public int $userId) {}

    public function handle(): void
    {
        $season = Season::current();
        $member = User::query()->withMembershipFacts()->find($this->userId);

        if ($season === null || $member === null || $member->membershipStatus() !== MembershipStatus::ToFollowUp) {
            return;
        }

        /*
         * sendNow(): the limiter paces this job, so the mail has to leave inside
         * it — see SendTournamentInvitationJob.
         */
        Notification::sendNow($member, new RenewalReminderNotification($season));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('invitations')];
    }
}
