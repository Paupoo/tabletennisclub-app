<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingInvitationNotification;
use App\Domains\Shared\Events\Meetings\MeetingCreated;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class NotifyParticipantsOfMeeting
{
    public function handle(MeetingCreated $event): void
    {
        $meeting = $event->meeting;

        $participants = $this->getParticipants($meeting);

        if ($participants->isNotEmpty()) {
            Notification::send(
                $participants,
                new MeetingInvitationNotification($meeting)
            );
        }
    }

    private function getAGParticipants(): Collection
    {
        return User::active()->get();
    }

    private function getCommitteeMembers(): Collection
    {
        return User::where('is_committee_member', true)->get();
    }

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
