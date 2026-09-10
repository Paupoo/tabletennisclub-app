<?php

declare(strict_types=1);

namespace App\Domains\Shared\Traits;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\Permission;

/**
 * The two my-space destinations a member notification is allowed to point at.
 *
 * Notifications addressed to a member used to link to `admin.trainings.index`
 * and `admin.tournaments.index`, which are gated by `can:trainings.manage` and
 * `can:tournaments.manage`. An ordinary member clicking the bell got a 403 —
 * issue #39, reported by a member who did exactly that.
 *
 * A member notification therefore links into my-space, never into the back
 * office. Committee notifications are a different matter and keep their
 * back-office links: they are addressed to people who hold the permission.
 */
trait LinksToMemberSpace
{
    /**
     * The minutes of a meeting, seen from whichever side the reader stands on.
     *
     * The minutes page is the note taker's desk: every one of its actions writes,
     * so it aborts on anything short of `meetings.manage`. The committee holds
     * `meetings.view` alone, and the meeting page already renders the published
     * announcements, decisions and notes inline for them — so that is where their
     * button keeps pointing, rather than at a 403. {@see meetingUrl()}.
     */
    protected function meetingMinutesUrl(object $notifiable, Meeting $meeting): string
    {
        $canManageMeetings = $notifiable instanceof User
            && $notifiable->can(Permission::MeetingsManage->value);

        return $canManageMeetings
            ? route('admin.meetings.minutes', $meeting)
            : $this->meetingUrl($notifiable, $meeting);
    }

    /**
     * A meeting, seen from whichever side the reader stands on.
     *
     * A general assembly is convened to every active member, and the back-office
     * meeting page is gated by `can:meetings.view`, which only the committee and
     * the meetings délégation hold. Sending everyone there is the same 403 as the
     * trainings and tournaments indexes were. The committee keeps the full page —
     * agenda, attendance, minutes — and everyone else lands on the my-space
     * events page, which already carries the RSVP.
     */
    protected function meetingUrl(object $notifiable, Meeting $meeting): string
    {
        $canViewBackOffice = $notifiable instanceof User
            && $notifiable->can(Permission::MeetingsView->value);

        return $canViewBackOffice
            ? route('admin.meetings.show', $meeting)
            : $this->memberEventsUrl($notifiable);
    }

    /**
     * Where a member manages their event registrations — tournaments included.
     */
    protected function memberEventsUrl(object $notifiable): string
    {
        return route('admin.user.event-subscription', $this->mySpaceOwnerFor($notifiable));
    }

    /**
     * Where a member manages their affiliation and their training packs.
     */
    protected function memberTrainingsUrl(object $notifiable): string
    {
        return route('admin.user.registration-management', $this->mySpaceOwnerFor($notifiable));
    }

    /**
     * A managed member has no login of their own; the guardian who received the
     * mail is the one who can open the page. {@see User::mySpaceOwner()}.
     */
    private function mySpaceOwnerFor(object $notifiable): object
    {
        return $notifiable instanceof User ? $notifiable->mySpaceOwner() : $notifiable;
    }
}
