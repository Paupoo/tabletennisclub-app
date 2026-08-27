<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;

/**
 * Every method used to read `is_admin || is_committee_member`. The right is now
 * named — so moving it between délégations is a seeder change rather than a grep
 * — while the relational parts stay here, because a permission cannot express
 * "anyone but yourself" or "your own account".
 */
class UserPolicy
{
    /**
     * Erasing someone's personal data is irreversible, and doing it to your own
     * account would lock you out of undoing it.
     */
    public function anonymize(User $user, User $model): bool
    {
        return $user->can(Permission::UsersAnonymize->value) && $user->isNot($model);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::UsersCreate->value);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(Permission::UsersDelete->value) && $user->isNot($model);
    }

    public function deleteForceList(User $user): bool
    {
        return $user->can(Permission::UsersUpdate->value);
    }

    /**
     * Nobody, ever: members are archived (soft-deleted), never erased, so the
     * payment and interclub history stays coherent. Deliberately not delegated —
     * no permission unlocks this.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }

    public function index(User $user): bool
    {
        return $user->can(Permission::UsersView->value);
    }

    /**
     * Writing the rights layer of a member's file: the délégations, the committee
     * seat and the statutory title that comes with it.
     *
     * Never on your own file, administrators included — the same doctrine as
     * delete() and anonymize(). Someone who can widen their own rights is bounded
     * by nothing, so an administrator wanting a right removed asks another one.
     * With a single administrator in the club that is a deliberate cost, weighed
     * against an escalation path that no matrix change could close.
     *
     * Deliberately narrower than the administrator checkbox, which stays on
     * promoteAdmin(): handing over the whole application is not a délégation.
     */
    public function manageAccess(User $user, User $model): bool
    {
        return $user->can(Permission::AccessManage->value) && $user->isNot($model);
    }

    /**
     * A member manages their own affiliation; managing someone else's is a duty.
     */
    public function manageSubscription(User $user, User $model): bool
    {
        return $user->can(Permission::SubscriptionsManage->value) || $user->is($model);
    }

    /**
     * Only an administrator makes another one. Kept as a role test rather than a
     * permission: this is the one right whose holders must not be widenable by
     * editing the matrix, since it hands over the whole application.
     */
    public function promoteAdmin(User $user, User $model): bool
    {
        return $user->hasRole(Role::ADMINISTRATOR->value) && $user->isNot($model);
    }

    /**
     * The committee seat and its statutory title are rights, not data: they are
     * handed over by whoever hands over the délégations, not by whoever keeps the
     * member's address up to date.
     */
    public function promoteCommitteeMember(User $user, User $model): bool
    {
        return $this->manageAccess($user, $model);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->can(Permission::UsersDelete->value);
    }

    public function selfDelete(User $user, User $model): bool
    {
        return $user->is($model);
    }

    public function sendEmail(User $user): bool
    {
        return $user->can(Permission::UsersInvite->value);
    }

    public function setOrUpdateForceList(User $user): bool
    {
        return $user->can(Permission::UsersUpdate->value);
    }

    public function update(User $user): bool
    {
        return $user->can(Permission::UsersUpdate->value);
    }

    public function updatePassword(User $user, User $model): bool
    {
        return $user->can(Permission::UsersUpdate->value) || $user->is($model);
    }

    /**
     * A member owns their own portrait; replacing or removing somebody else's is
     * editing their file.
     *
     * Same shape as updatePassword(), and named separately because the screens
     * that offer it are not the same ones: the self-service profile and the
     * onboarding wizard both hand a member their own photo, while the member
     * form hands an editor everyone's.
     */
    public function updatePhoto(User $user, User $model): bool
    {
        return $user->can(Permission::UsersUpdate->value) || $user->is($model);
    }

    /**
     * Members are visible to one another — the club runs a member directory.
     * What each of them exposes is governed by their own contact_visibility.
     */
    public function view(User $user, User $model): bool
    {
        return true;
    }
}
