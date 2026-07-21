<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;

/**
 * Translates the three booleans still carried by the user form into Spatie roles.
 *
 * Only the base roles it owns are recomputed; every other délégation the member
 * holds is preserved, so granting someone the cash-register duty is not undone
 * the next time an admin saves their profile.
 *
 * This is a stepping stone: once the form offers délégations directly, the form
 * assigns roles itself and this action disappears with the boolean columns.
 */
class SyncBaseRolesAction
{
    private const array OWNED = [
        Role::ADMINISTRATOR,
        Role::COMMITTEE,
        Role::COACH,
    ];

    public static function handle(User $user, bool $isAdmin, bool $isCommitteeMember, bool $isCoach): void
    {
        $owned = array_map(static fn (Role $role): string => $role->value, self::OWNED);

        $preserved = $user->roles
            ->pluck('name')
            ->reject(static fn (string $name): bool => in_array($name, $owned, true))
            ->all();

        $granted = [];

        if ($isAdmin) {
            $granted[] = Role::ADMINISTRATOR->value;
        }

        if ($isCommitteeMember) {
            $granted[] = Role::COMMITTEE->value;
        }

        if ($isCoach) {
            $granted[] = Role::COACH->value;
        }

        $user->syncRoles([...$preserved, ...$granted]);

        // A statutory title only belongs to a committee member. Enforced here rather
        // than in a saving() observer, because the roles this reads are only written
        // once the row exists.
        if (! $isCommitteeMember && $user->committee_role !== null) {
            $user->forceFill(['committee_role' => null])->save();
        }
    }
}
