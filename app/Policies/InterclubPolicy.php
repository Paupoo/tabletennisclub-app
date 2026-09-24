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

    /**
     * Opening one fixture's own page.
     *
     * Wider than {@see view()}, which gates the club-wide fixture management
     * screens behind a délégation. Since 2026-09-24 every member reads the page
     * of any fixture the club plays: the club calendar links every match tile
     * here, and a link must never lead to a 403 (DS-D). What stays the team's —
     * the answer, the captain's word, their contact details — the page itself
     * holds back from a visitor.
     *
     * The id is still in the URL, so a fixture the club does not play in is
     * refused.
     */
    public function viewMatchPage(User $user, Interclub $interclub): bool
    {
        $interclub->loadMissing(['visitedTeam.club', 'visitingTeam.club']);

        return $interclub->visitedTeam?->club?->is_own_club || $interclub->visitingTeam?->club?->is_own_club;
    }
}
