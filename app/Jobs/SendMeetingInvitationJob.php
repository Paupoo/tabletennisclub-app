<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingInvitationNotification;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Notification;

/**
 * One member's convocation, queued so a general assembly never leaves in one burst.
 *
 * Same reasoning as {@see SendMemberInvitationJob}: Gmail tolerates the volume
 * and not the burst, and a club whose convocations land in spam has as many
 * families to explain it to. The limiter is a separate one, declared in
 * {@see AppServiceProvider} — a convocation carries a date the member has to
 * answer for, where an invitation nobody is waiting on can take three quarters
 * of an hour.
 *
 * Carries ids rather than models: a member archived between the fan-out and the
 * send is simply skipped, where a serialised model would fail the job.
 */
class SendMeetingInvitationJob implements ShouldQueue
{
    use Queueable, RetriesWhileRateLimited;

    public function __construct(public int $meetingId, public int $userId) {}

    public function handle(): void
    {
        $meeting = Meeting::with('agendaItems')->find($this->meetingId);
        $recipient = User::find($this->userId);

        if ($meeting === null || $recipient === null) {
            return;
        }

        Notification::send($recipient, new MeetingInvitationNotification($meeting));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('convocations')];
    }
}
