<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Notifications\MemberLeftTeamNotification;

/**
 * Tell the captain of each team a departing member played in that the member
 * is gone — and nobody else.
 *
 * The captain is the one with a place to fill, whether the member was in a
 * lineup already sent, in a draft, or in no match yet. One mail per captain
 * for the whole departure: a captain of two of the member's teams hears it
 * once, naming both, with the matches the member was taken off.
 *
 * A team whose captain was the member has nobody to tell: its captaincy has
 * just been handed back, and the screen names it among the teams left without
 * a captain.
 */
class TellCaptainsOfDepartureAction
{
    /**
     * @param  array<int, int>  $teamIds  the teams the member held a place in
     * @param  list<int>  $lineupIds  the upcoming matches the member was lined up for, by date
     * @param  list<int>  $shortHandedWithdrawn  among them, those no longer declared to play with three
     * @return list<int> the ids of the captains told, in ascending order
     */
    public static function handle(User $leaver, Season $season, array $teamIds, array $lineupIds, array $shortHandedWithdrawn): array
    {
        $fixtures = Interclub::query()
            ->with(['visitedTeam.club', 'visitingTeam.club'])
            ->whereKey($lineupIds)
            ->orderBy('start_date_time')
            ->orderBy('id')
            ->get();

        // A member lined up for a team they hold no place in — playing up, as
        // a reserve — is that team's captain's news as well.
        $fixtureTeamIds = $fixtures->map(fn (Interclub $fixture): ?int => $fixture->ourTeam()?->id)->filter()->all();

        $teams = Team::query()
            ->with('captain')
            ->whereKey(array_unique([...$teamIds, ...$fixtureTeamIds]))
            ->whereNotNull('captain_id')
            ->where('captain_id', '!=', $leaver->id)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $told = [];

        foreach ($teams->groupBy('captain_id') as $captainTeams) {
            /** @var Team $first */
            $first = $captainTeams->first();
            $captain = $first->captain;

            if (! $captain instanceof User) {
                continue;
            }

            $captainTeamIds = $captainTeams->modelKeys();
            $captainFixtureIds = $fixtures
                ->filter(fn (Interclub $fixture): bool => in_array($fixture->ourTeam()?->id, $captainTeamIds, true))
                ->modelKeys();

            $captain->notify(new MemberLeftTeamNotification(
                $leaver->full_name,
                $captainTeams->whereIn('id', $teamIds)->pluck('name')->values()->all(),
                array_values($captainFixtureIds),
                array_values(array_intersect($captainFixtureIds, $shortHandedWithdrawn)),
            ));

            $told[] = $captain->id;
        }

        sort($told);

        return $told;
    }
}
