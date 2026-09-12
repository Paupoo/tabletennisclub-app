<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\User\SendGuardianInvitationAction;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * One parent's invitation, queued beside the members' own.
 *
 * Shares the `invitations` limiter rather than declaring one of its own: the
 * point of that limiter is the club's outgoing mail as seen by Gmail, and a
 * burst is a burst whether it is addressed to members or to their parents.
 *
 * Carries an id rather than a model, for the same reason as
 * {@see SendMemberInvitationJob}: a guardian sheet deleted between the click and
 * the send is skipped instead of failing the job.
 */
class SendGuardianInvitationJob implements ShouldQueue
{
    use Batchable, Queueable, RetriesWhileRateLimited;

    public function __construct(public int $guardianId) {}

    public function handle(): void
    {
        $guardian = Guardian::find($this->guardianId);

        if ($guardian === null) {
            return;
        }

        SendGuardianInvitationAction::handle($guardian);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('invitations')];
    }
}
