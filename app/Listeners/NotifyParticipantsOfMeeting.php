<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Events\Meetings\MeetingCreated;
use App\Jobs\SendMeetingInvitationJob;
use App\Jobs\SendMeetingInvitationsJob;
use Illuminate\Database\Eloquent\Collection;

/**
 * The other door onto a general assembly's convocations, and it had the same
 * burst problem as {@see SendMeetingInvitationsJob}: creating the
 * meeting sent every active member their notification in one call. It now fans
 * out onto the same throttled job.
 */
class NotifyParticipantsOfMeeting
{
    public function handle(MeetingCreated $event): void
    {
        $meeting = $event->meeting;

        $participants = $this->getParticipants($meeting);

        foreach ($participants as $participant) {
            SendMeetingInvitationJob::dispatch($meeting->id, $participant->id);
        }
    }

    /** @return Collection<int, User> */
    private function getAGParticipants(): Collection
    {
        return User::active()->get();
    }

    /** @return Collection<int, User> */
    private function getCommitteeMembers(): Collection
    {
        return User::role(Role::COMMITTEE->value)->get();
    }

    /** @return Collection<int, User> */
    private function getParticipants(Meeting $meeting): Collection
    {
        $type = $meeting->type->value;

        return match ($type) {
            'general_assembly' => $this->getAGParticipants(),
            'committee' => $this->getCommitteeMembers(),
            default => User::query()->whereRaw('1=0')->get(),
        };
    }
}
