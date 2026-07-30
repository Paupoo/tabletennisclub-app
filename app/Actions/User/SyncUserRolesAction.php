<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;

/**
 * Applies the roles a form submitted, without touching what it did not manage.
 *
 * The base roles (administrator, committee) come from their own checkboxes and
 * are always recomputed. Délégations are only rewritten when the caller actually
 * offers them: the self-service profile screen edits a member's own details and
 * knows nothing about duties, so it passes null and leaves them untouched — were
 * it to pass an empty array, saving your own profile would strip your duties.
 */
class SyncUserRolesAction
{
    public static function handle(
        User $user,
        bool $isAdmin,
        bool $isCommitteeMember,
        ?array $delegations = null,
    ): void {
        $roles = [];

        if ($isAdmin) {
            $roles[] = Role::ADMINISTRATOR->value;
        }

        if ($isCommitteeMember) {
            $roles[] = Role::COMMITTEE->value;
        }

        $roles = [...$roles, ...($delegations === null
            ? self::currentDelegations($user)
            : self::sanitise($delegations))];

        $user->syncRoles($roles);

        // A statutory title only belongs to a committee member. Enforced here
        // rather than in a saving() observer, because the roles this reads are
        // only written once the row exists.
        if (! $isCommitteeMember && $user->committee_role !== null) {
            $user->forceFill(['committee_role' => null])->save();
        }
    }

    /**
     * @return array<int, string>
     */
    private static function currentDelegations(User $user): array
    {
        return $user->roles
            ->pluck('name')
            ->filter(static fn (string $name): bool => Role::tryFrom($name)?->isDelegation() ?? false)
            ->values()
            ->all();
    }

    /**
     * Keeps only names that are real délégations, so a tampered form payload
     * cannot grant the administrator role through the duties field.
     *
     * @param  array<int, mixed>  $delegations
     * @return array<int, string>
     */
    private static function sanitise(array $delegations): array
    {
        return collect($delegations)
            ->map(static fn (mixed $name): ?Role => is_string($name) ? Role::tryFrom($name) : null)
            ->filter(static fn (?Role $role): bool => $role?->isDelegation() ?? false)
            ->map(static fn (Role $role): string => $role->value)
            ->unique()
            ->values()
            ->all();
    }
}
