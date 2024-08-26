<?php

namespace App\Services\Team;

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Models\Club;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;

class TeamService
{
    public function __construct(protected Team $team){}
    
    public function getAllTeams(){}

    // Club filtering
    public function getTeamsInClub(){}
    public function getTeamsNotInClub(){}
    public function getTeamsFromClub(Club $club){}

    // Season based filtering
    public function getTeamsInSeason(Season $season){}
    public function getTeamsInSeasonsRange(Season $start, Season $end){}
    public function getTeamsCurrentSeason(){}
    public function getTeamsPreviousSeason(){}
    public function getTeamsNextSeason(){}

    // Captain filtering
    public function getTeamsWithCaptain(User $captain){}
    public function getTeamsWithoutCaptain(User $captain){}

    // Players fitlering
    public function getTeamsWithPlayers(){}
    public function getTeamsWithoutPlayers(){}

    // League filtering
    public function getTeamsInLevel(LeagueLevel $level){}
    public function getTeamsInCategory(LeagueCategory $category){}
    public function getTeamsInDivision(string $division){}

    // Status filtering
    public function getActiveTeams(){}
    public function getInactiveTeams(){}

    public function setTeamActive(){}
    public function setTeamInactive(){}
    public function setTeamClub(){}
    public function setTeamSeason(){}
    public function setTeamCaptain(){}
    public function setTeamLeague(LeagueLevel $level, LeagueCategory $category, string $division){}

    public function addPlayerToTeam(User $player){}
    public function addPlayersToTeam(array $players){}
    public function removePlayerFromTeam(User $player){}
    public function removePlayersFromTeam(array $players){}



}