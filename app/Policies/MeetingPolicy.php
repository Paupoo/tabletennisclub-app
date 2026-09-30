<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Enums\Permission;

/**
 * Who may read a meeting's minutes, and tick the actions they carry.
 *
 * A committee meeting's minutes never leave the committee: they can name a
 * member in debt or a conflict. That holds even for a member invited to that
 * meeting in person — which is why this is stricter than
 * {@see Meeting::scopeVisibleTo()}, which lets such a member find the meeting
 * itself in their agenda.
 *
 * A general assembly's minutes belong to every active member, once the
 * committee has sent them to all: publishing alone does not open them, so the
 * committee gets to read them over first.
 */
class MeetingPolicy
{
    /** The member an action is assigned to, or whoever runs the meetings. */
    public function completeAction(User $user, Meeting $meeting, ?int $assigneeId): bool
    {
        return $user->can(Permission::MeetingsManage->value)
            || ($assigneeId !== null && $assigneeId === $user->id && $this->readMinutes($user, $meeting));
    }

    public function readMinutes(User $user, Meeting $meeting): bool
    {
        if (! $meeting->minutes?->is_published) {
            return false;
        }

        if ($user->can(Permission::MeetingsView->value)) {
            return true;
        }

        return $meeting->type === MeetingTypeEnum::GENERAL_ASSEMBLY
            && $meeting->minutes->sent_to_all_at !== null
            && $this->isOrActsForAnActiveMember($user);
    }

    /**
     * A general assembly is convened to the active members: the minutes reach
     * the same people. A guardian receives the mail for the child they manage,
     * and opens it logged in as themselves.
     */
    private function isOrActsForAnActiveMember(User $user): bool
    {
        $candidates = collect([$user->id])->merge($user->managedAccounts()->modelKeys());

        return User::active()->whereKey($candidates->all())->exists();
    }
}
