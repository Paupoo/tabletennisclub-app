<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\User\SendInvitationAction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use App\Providers\AppServiceProvider;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * One member's invitation, queued so the club never sends fifty at once.
 *
 * The club sends through Gmail, where the daily allowance is generous and the
 * burst is not: fifty identical messages in three seconds is what gets a sender
 * classified as a spammer, and a club whose invitations land in spam has fifty
 * families to explain it to. Spread over the limiter declared in
 * {@see AppServiceProvider}, the same fifty take three quarters
 * of an hour and nobody notices.
 *
 * Carries an id rather than a model: a member archived between the click and the
 * send is simply skipped, where a serialised model would fail the job.
 */
class SendMemberInvitationJob implements ShouldQueue
{
    use Batchable, Queueable, RetriesWhileRateLimited;

    public function __construct(public int $userId) {}

    public function handle(): void
    {
        $member = User::find($this->userId);

        if ($member === null) {
            return;
        }

        SendInvitationAction::handle($member);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('invitations')];
    }
}
