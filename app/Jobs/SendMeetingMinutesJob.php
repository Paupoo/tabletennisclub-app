<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingMinutesNotification;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Notification;

/**
 * One reader's copy of published minutes, queued so a general assembly's
 * minutes never leave in one burst.
 *
 * It shares the `invitations` limiter with the other broadcasts: Gmail counts
 * the club's burst, not the kind of mail, and nobody is waiting on minutes the
 * way they wait on a convocation's date. Carries ids, so a member archived in
 * between is skipped rather than failing the job.
 */
class SendMeetingMinutesJob implements ShouldQueue
{
    use Queueable, RetriesWhileRateLimited;

    public function __construct(public int $meetingId, public int $userId) {}

    public function handle(): void
    {
        $meeting = Meeting::find($this->meetingId);
        $recipient = User::find($this->userId);

        if ($meeting === null || $recipient === null || ! $meeting->minutes?->is_published) {
            return;
        }

        Notification::sendNow($recipient, new MeetingMinutesNotification($meeting));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('invitations')];
    }
}
