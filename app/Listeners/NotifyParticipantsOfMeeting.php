<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingInvitationNotification;
use App\Domains\Shared\Events\Meetings\MeetingCreated;
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

    private function getParticipants(Meeting $meeting): \Illuminate\Database\Eloquent\Collection
    {
        $type = $meeting->type->value;

        return match ($type) {
            'general_assembly' => $this->getAGParticipants(),
            'committee' => $this->getCommitteeMembers(),
            default => \App\Domains\ClubAdmin\Users\Models\User::query()->whereRaw('1=0')->get(),
        };
    }

    private function getAGParticipants(): \Illuminate\Database\Eloquent\Collection
    {
        return \App\Domains\ClubAdmin\Users\Models\User::where('is_active', true)->get();
    }

    private function getCommitteeMembers(): \Illuminate\Database\Eloquent\Collection
    {
        return \App\Domains\ClubAdmin\Users\Models\User::where('is_committee_member', true)
            ->where('is_active', true)
            ->get();
    }

    private function getAllMembers(): \Illuminate\Database\Eloquent\Collection
    {
        return \App\Domains\ClubAdmin\Users\Models\User::where('is_active', true)->get();
    }
}
