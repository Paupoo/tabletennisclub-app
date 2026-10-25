<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Fans a meeting's convocations out into one job per recipient.
 *
 * The pivot still moves in a single upsert — every invited member is recorded as
 * invited the moment the committee clicks, whatever the mail queue does next.
 * Only the sending is spread, by {@see SendMeetingInvitationJob}, so a general
 * assembly to the whole club does not leave as fifty near identical messages in
 * three seconds.
 */
class SendMeetingInvitationsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $meetingId) {}

    public function handle(): void
    {
        $meeting = Meeting::with('agendaItems')->findOrFail($this->meetingId);

        $recipients = $meeting->audience()->get();

        // Bulk pivot upsert — single query for all users
        DB::table('meeting_user')->upsert(
            $recipients->map(fn (User $u): array => [
                'meeting_id' => $meeting->id,
                'user_id' => $u->id,
                'status' => MeetingUserStatusEnum::INVITED->value,
                'invitation_sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray(),
            ['meeting_id', 'user_id'],
            ['status', 'invitation_sent_at', 'updated_at']
        );

        // Guests asked before the convocations left get theirs now. The upsert
        // above never touches them: they are outside the audience.
        $guestIds = $meeting->users()
            ->wherePivot('is_guest', true)
            ->wherePivotNull('invitation_sent_at')
            ->pluck('users.id');
        DB::table('meeting_user')
            ->where('meeting_id', $meeting->id)
            ->whereIn('user_id', $guestIds)
            ->update(['invitation_sent_at' => now(), 'updated_at' => now()]);

        foreach ([...$recipients->modelKeys(), ...$guestIds] as $id) {
            SendMeetingInvitationJob::dispatch($meeting->id, $id);
        }
    }
}
