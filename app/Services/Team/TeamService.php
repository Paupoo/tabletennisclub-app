<?php

declare(strict_types=1);

namespace App\Services\Team;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\LeagueLevel;

class TeamService
{
    public function __construct(protected Team $team) {}

    public function addPlayersToTeam(array $players): void {}

    public function addPlayerToTeam(User $player): void {}

    // Status filtering
    public function getActiveTeams(): void {}

    public function getInactiveTeams(): void {}

    public function getTeamsCurrentSeason(): void {}

    public function getTeamsFromClub(Club $club): void {}

    public function getTeamsInCategory(LeagueCategory $category): void {}

    public function getTeamsInDivision(string $division): void {}

    // League filtering
    public function getTeamsInLevel(LeagueLevel $level): void {}

    // Season based filtering
    public function getTeamsInSeason(Season $season): void {}

    public function getTeamsInSeasonsRange(Season $start, Season $end): void {}

    public function getTeamsNextSeason(): void {}

    public function getTeamsNotInClub(): void {}

    public function getTeamsPreviousSeason(): void {}

    // Captain filtering
    public function getTeamsWithCaptain(User $captain): void {}

    public function getTeamsWithoutCaptain(User $captain): void {}

    public function getTeamsWithoutPlayers(): void {}

    // Players fitlering
    public function getTeamsWithPlayers(): void {}

    public function removePlayerFromTeam(User $player): void {}

    public function removePlayersFromTeam(array $players): void {}

    public function setTeamActive(): void {}

    public function setTeamCaptain(): void {}

    public function setTeamClub(): void {}

    public function setTeamInactive(): void {}

    public function setTeamLeague(LeagueLevel $level, LeagueCategory $category, string $division): void {}

    public function setTeamSeason(): void {}
}
