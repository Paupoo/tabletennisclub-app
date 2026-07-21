<?php

declare(strict_types=1);

namespace App\Support\Help;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;
use App\Http\Controllers\ClubAdmin\DashboardController;

/**
 * Resolves which help tasks a member is shown first.
 *
 * Roles are additive, never exclusive: an admin is a member *and* a secretary
 * *and* a treasurer at once, exactly as {@see DashboardController}
 * treats them. A task is therefore written once and tagged with every role it
 * concerns — it is never duplicated per persona.
 */
final class HelpAudience
{
    /**
     * @return string[]
     */
    public static function for(User $user): array
    {
        $tags = ['member'];

        if ($user->is_competitor) {
            $tags[] = 'competitor';
        }

        if ($user->can(Permission::CoachAreaAccess->value)) {
            $tags[] = 'coach';
        }

        if ($user->can(Permission::SelectionsManage->value)) {
            $tags[] = 'selector';
        }

        if ($user->can(Permission::UsersView->value)) {
            $tags[] = 'committee';
        }

        if ($user->canManageClubAdmin()) {
            $tags[] = 'secretary';
        }

        if ($user->can(Permission::FinesIssue->value)) {
            $tags[] = 'treasurer';
        }

        if (Team::where('captain_id', $user->id)->exists()) {
            $tags[] = 'captain';
        }

        if ($user->hasRole(Role::ADMINISTRATOR->value)) {
            $tags[] = 'admin';
        }

        return $tags;
    }
}
