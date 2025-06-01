<?php

declare(strict_types=1);

namespace App\Services\Team;

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Models\Club;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;

class TeamService
{
    public function __construct(protected Team $team) {}

    public function addPlayersToTeam(array $players) {}

    public function addPlayerToTeam(User $player) {}

    // Status filtering
    public function getActiveTeams() {}

    public function getInactiveTeams() {}

    public function getTeamsCurrentSeason() {}

    public function getTeamsFromClub(Club $club) {}

    public function getTeamsInCategory(LeagueCategory $category) {}

    public function getTeamsInDivision(string $division) {}

    // League filtering
    public function getTeamsInLevel(LeagueLevel $level) {}

    // Season based filtering
    public function getTeamsInSeason(Season $season) {}

    public function getTeamsInSeasonsRange(Season $start, Season $end) {}

    public function getTeamsNextSeason() {}

    public function getTeamsNotInClub() {}

    public function getTeamsPreviousSeason() {}

    // Captain filtering
    public function getTeamsWithCaptain(User $captain) {}

    public function getTeamsWithoutCaptain(User $captain) {}

    public function getTeamsWithoutPlayers() {}

    // Players fitlering
    public function getTeamsWithPlayers() {}

    public function removePlayerFromTeam(User $player) {}

    public function removePlayersFromTeam(array $players) {}

    public function setTeamActive() {}

    public function setTeamCaptain() {}

    public function setTeamClub() {}

    public function setTeamInactive() {}

    public function setTeamLeague(LeagueLevel $level, LeagueCategory $category, string $division) {}

    public function setTeamSeason() {}
}
