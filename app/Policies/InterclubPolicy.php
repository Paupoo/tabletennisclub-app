<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Shared\Enums\Permission;

/**
 * update() and delete() used to `return true` — any authenticated member could
 * rewrite or destroy any fixture. It was labelled an "intentional open policy",
 * and it never bit because nothing invoked the policy; it is invoked now.
 *
 * Managing the calendar is a duty. Composing a lineup is one too, but a captain
 * holds it only for their own teams — hence the relational half here, which no
 * permission can express.
 */
class InterclubPolicy
{
    public function create(User $user): bool
    {
        return $user->can(Permission::InterclubsManage->value);
    }

    public function delete(User $user, Interclub $interclub): bool
    {
        return $user->can(Permission::InterclubsManage->value);
    }

    public function forceDelete(User $user, Interclub $interclub): bool
    {
        return false;
    }

    public function restore(User $user, Interclub $interclub): bool
    {
        return false;
    }

    /**
     * Composing the lineup of a given fixture: a club-wide selector may do it
     * anywhere, a captain only where they captain.
     */
    public function selectLineup(User $user, Interclub $interclub): bool
    {
        // A club-wide selector composes anywhere; a captain only where they
        // captain — and a captain holds no délégation, so the relation is the
        // only thing that grants it.
        return $user->can(Permission::SelectionsManage->value)
            || $interclub->isCaptainedBy($user);
    }

    public function update(User $user, Interclub $interclub): bool
    {
        return $user->can(Permission::InterclubsManage->value);
    }

    public function view(User $user, Interclub $interclub): bool
    {
        return $user->can(Permission::InterclubsView->value);
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permission::InterclubsView->value);
    }
}
