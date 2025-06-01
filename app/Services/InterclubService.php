<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Club;
use App\Models\Interclub;
use App\Models\League;
use App\Models\Room;
use App\Models\Season;
use App\Models\Team;

class InterclubService
{
    public function createInterclub(array $validated): void
    {
        // Instanciating elements
        $clubTeam = Team::find($validated['team_id']);
        $season = Season::find($clubTeam->season->id);
        $league = League::find($clubTeam->league->id);
        $oppositeClub = Club::find($validated['opposite_club_id']);

        // Deal with other club's team
        $oppositeTeam = Team::firstorCreate([
            'name' => $validated['opposite_team_name'],
            'club_id' => $oppositeClub->id,
            'season_id' => $season->id,
            'league_id' => $league->id,
        ]);

        // Prepare interclub
        $interclub = new Interclub;

        if (isset($validated['is_visited'])) {       // If visited
            $room = Room::find($validated['room_id']);
            $validated['address'] = sprintf('%s, %s %s', $room->street, $room->city_code, $room->city_name);
            $interclub->visitedTeam()->associate($clubTeam);
            $interclub->visitingTeam()->associate($oppositeTeam);
            $interclub->room()->associate($room);
        } else {                                    // If visiting
            $validated['address'] = sprintf('%s, %s %s', $oppositeClub->street, $oppositeClub->city_code, $oppositeClub->city_name);
            $interclub->visitedTeam()->associate($oppositeTeam);
            $interclub->visitingTeam()->associate($clubTeam);
        }

        $interclub
            ->fill($validated)
            ->setTotalPlayersPerteam($league->category)
            ->setWeekNumber($validated['start_date_time'])
            ->save();
    }
}
