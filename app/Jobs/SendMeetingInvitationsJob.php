<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingInvitationNotification;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SendMeetingInvitationsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $meetingId) {}

    public function handle(): void
    {
        $meeting = Meeting::with('agendaItems')->findOrFail($this->meetingId);

        $recipients = $meeting->type === MeetingTypeEnum::GENERAL_ASSEMBLY
            ? User::where('is_active', true)->get()
            : User::where(fn (Builder $q) => $q->where('is_admin', true)->orWhere('is_committee_member', true))->get();

        if ($recipients->isEmpty()) {
            return;
        }

        // Bulk pivot upsert — single query for all users
        DB::table('meeting_user')->upsert(
            $recipients->map(fn (User $u) => [
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

        Notification::send($recipients, new MeetingInvitationNotification($meeting));
    }
}
