<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use DomainException;
use Illuminate\Database\Eloquent\Builder;

class TeamObserver
{
    /**
     * Empty the roster through Eloquent before the team goes.
     *
     * The team_user foreign keys cascade on delete, so the database would
     * otherwise remove the rows itself, silently: no model event, no audit
     * entry, and no way to tell afterwards who was in the team that was just
     * deleted. Two of the three delete paths detached by hand; the third did
     * not, and nothing stopped a fourth from forgetting too.
     *
     * detach() is idempotent, so the hand-written calls that remain elsewhere
     * simply find nothing left to remove.
     */
    public function deleting(Team $team): void
    {
        $team->users()->detach();
    }

    /**
     * One letter per category, per season — for our own teams only.
     *
     * The club fields A to E in men and A to C in veterans: a letter identifies a
     * team within its category, not within its division. Keying on the division
     * instead — which is what the two screens that bothered to check were doing —
     * only catches a duplicate when both teams land in the same division, and lets
     * two men's « A » stand side by side in 3B and 4C.
     *
     * Opponent clubs are deliberately out. Their teams arrive through the
     * federation importer's `firstOrCreate`, and a tighter key there would let two
     * real teams collapse into one and drag their fixtures along. A duplicate row
     * on an opponent is harmless; a silent merge is not.
     */
    public function saving(Team $team): void
    {
        $club = $team->club_id === null ? null : Club::find($team->club_id);

        if ($club?->is_own_club !== true) {
            return;
        }

        $category = $team->league_id === null
            ? null
            : League::find($team->league_id)?->category;

        $clashes = Team::query()
            ->leftJoin('leagues', 'leagues.id', '=', 'teams.league_id')
            ->where('teams.season_id', $team->season_id)
            ->where('teams.club_id', $team->club_id)
            ->where('teams.name', $team->name)
            ->when(
                $category === null,
                fn (Builder $query) => $query->whereNull('leagues.category'),
                fn (Builder $query) => $query->where('leagues.category', $category),
            )
            ->when($team->exists, fn (Builder $query) => $query->whereKeyNot($team->getKey()))
            ->exists();

        if ($clashes) {
            throw new DomainException(__('Team :name already exists in this category for this season.', [
                'name' => $team->name,
            ]));
        }
    }
}
