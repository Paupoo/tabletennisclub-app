<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
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
     * screens behind a délégation. This page is where a player lands from
     * "you are selected", so the roster has to grant it — and the id is in the
     * URL, so something has to refuse it. Three groups may look: whoever plays
     * for either side, whoever captains either side, and whoever already reads
     * every fixture elsewhere — the committee included, whose team files link
     * here.
     *
     * Roster membership *or* a row on the fixture: a member who answered and
     * then left the team still has an answer on this match, and reading their
     * own page back should not 403.
     */
    public function viewMatchPage(User $user, Interclub $interclub): bool
    {
        if ($user->canAny([
            Permission::InterclubsView->value,
            Permission::InterclubsManage->value,
            Permission::SelectionsManage->value,
            Permission::ResultsManage->value,
        ])) {
            return true;
        }

        if ($interclub->isCaptainedBy($user)) {
            return true;
        }

        $teamIds = array_filter([$interclub->visited_team_id, $interclub->visiting_team_id]);

        $playsForEitherSide = $teamIds !== [] && $user->teams()
            ->whereIn('teams.id', $teamIds)
            ->exists();

        if ($playsForEitherSide) {
            return true;
        }

        // Having played it is the strongest claim of all, and the only one a
        // member keeps on a season imported from the federation: those team
        // rows arrive empty, because the federation publishes its own teams and
        // never our roster. The match sheet, which it does publish, names them.
        return $interclub->users()->where('users.id', $user->id)->exists()
            || InterclubIndividualMatch::where('interclub_id', $interclub->id)
                ->where('user_id', $user->id)
                ->exists();
    }
}
