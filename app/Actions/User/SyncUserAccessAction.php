<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Data\User\AccessData;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;

/**
 * The single writer of a member's rights layer: the administrator flag, the
 * committee seat, the statutory title that comes with it, and the délégations.
 *
 * Callers that do not manage rights pass null and nothing here runs — an
 * explicit "do not touch" rather than default values that would quietly revoke
 * what they never offered. Saving your own profile used to reach this code with
 * the flags it had just re-read from the model; it now says nothing at all.
 *
 * Defensive on purpose. The screens gate themselves through the policy, but a
 * Livewire property stays writable from the client even when the markup never
 * renders it, so the rules that matter are enforced again here, where the write
 * actually happens: the layer belongs to whoever may manage the target's access,
 * the administrator flag to promoteAdmin alone, and a délégation the matrix
 * reserves to administrators moves in neither direction for anybody else.
 */
class SyncUserAccessAction
{
    public static function handle(User $user, ?AccessData $access, User $actor): void
    {
        // Two refusals, one silence: a caller with nothing to say about rights,
        // and a caller with no standing to say it. Neither is an error worth
        // interrupting a save that is otherwise legitimate.
        if (! $access instanceof AccessData || ! $actor->can('manageAccess', $user)) {
            return;
        }

        $before = self::roleNames($user);

        $isAdmin = $actor->can('promoteAdmin', $user)
            ? $access->isAdmin
            : $user->hasRole(Role::ADMINISTRATOR->value);

        $roles = self::sanitise($access->delegations, $user, $actor);

        if ($isAdmin) {
            $roles[] = Role::ADMINISTRATOR->value;
        }

        if ($access->isCommitteeMember) {
            $roles[] = Role::COMMITTEE->value;
        }

        $user->syncRoles($roles);
        $user->load('roles');

        // A statutory title only belongs to a committee member. Enforced here
        // rather than in a saving() observer, because the roles this reads are
        // only written once the row exists.
        $title = $access->isCommitteeMember ? $access->committeeRole : null;

        if ($user->committee_role !== $title) {
            $user->forceFill(['committee_role' => $title])->save();
        }

        $after = self::roleNames($user);

        // Roles live in a pivot table, which logFillable never sees: without this
        // the one change worth auditing above all others left no trace at all.
        //
        // Through withChanges() rather than withProperties(): the diff lives in
        // its own `attribute_changes` column, which is the one the audit screen
        // renders. Logged as properties instead, the entry appears in the list
        // with an empty Details column — which is how this first shipped.
        // Raw role names rather than labels: a translated label frozen into the
        // log would drift the day the wording changes.
        if ($before !== $after) {
            activity()
                ->performedOn($user)
                ->causedBy($actor)
                ->event('roles_changed')
                ->withChanges([
                    'attributes' => ['roles' => implode(', ', $after)],
                    'old' => ['roles' => implode(', ', $before)],
                ])
                ->log('roles_changed');
        }
    }

    /**
     * @return array<int, string>
     */
    private static function roleNames(User $user): array
    {
        return $user->getRoleNames()->sort()->values()->all();
    }

    /**
     * Keeps only names that are real délégations the actor may actually hand out,
     * so a tampered payload cannot grant the administrator role through the
     * duties field, nor the délégation that hands out délégations.
     *
     * @param  array<int, mixed>  $delegations
     * @return array<int, string>
     */
    private static function sanitise(array $delegations, User $user, User $actor): array
    {
        $actorIsAdministrator = $actor->hasRole(Role::ADMINISTRATOR->value);

        $submitted = collect($delegations)
            ->map(static fn (mixed $name): ?Role => is_string($name) ? Role::tryFrom($name) : null)
            ->filter(static fn (?Role $role): bool => $role?->isDelegation() ?? false)
            ->reject(static fn (Role $role): bool => $role->isReservedToAdministrators() && ! $actorIsAdministrator)
            ->map(static fn (Role $role): string => $role->value);

        // A reserved délégation is rendered locked to everyone but an
        // administrator, so their save must not move it in either direction:
        // what the member holds today survives it.
        if (! $actorIsAdministrator) {
            $submitted = $submitted->merge(
                collect(Role::cases())
                    ->filter(static fn (Role $role): bool => $role->isReservedToAdministrators() && $user->hasRole($role->value))
                    ->map(static fn (Role $role): string => $role->value)
            );
        }

        return $submitted->unique()->values()->all();
    }
}
