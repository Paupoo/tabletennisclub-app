<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Models\TeamUser;
use App\Livewire\Concerns\ComposesInterclubLineup;
use DomainException;

/**
 * One core per category, per season.
 *
 * A place in a team is a declaration, not an option. Playing up for a single
 * fixture already has its own road — the substitute search in the selection
 * drawer — which never writes to the roster, so nothing operational is lost by
 * making the roster exclusive.
 *
 * The rule sits on the pivot rather than on the two screens that write it today.
 * Every path goes through here: sync(), attach(), a seeder, tinker, and the
 * screen nobody has written yet. A rule placed anywhere else would be one an
 * appelant can forget, and this codebase has shipped that mistake before.
 *
 * Categories stay independent on purpose: the same player may hold a place in a
 * men's team and in a veterans' one. Only two teams *of the same category* clash
 * — the same line drawn one level down by {@see ComposesInterclubLineup}
 * for the weekly lineup.
 */
class TeamUserObserver
{
    public function saving(TeamUser $place): void
    {
        $team = Team::with('league')->find($place->team_id);

        if (! $team instanceof Team) {
            return;
        }

        $held = Team::coreHeldBy(
            $place->user_id,
            $team->season_id,
            $team->league?->category,
            exceptTeamId: $team->id,
        );

        if ($held instanceof Team) {
            throw new DomainException(__('This player already holds a place in team :team for this category.', [
                'team' => $held->name,
            ]));
        }
    }
}
