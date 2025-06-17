<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;

class TournamentService
{
    public function countRegisteredUsers(Tournament $tournament): int
    {
        $totalUsers = $tournament->users()->count();
        $tournament->total_users = $totalUsers;
        $tournament->update();

        return $totalUsers;
    }

    /**
     * Check if there the tournament has reached its maximum amount of players
     */
    public function isFull(Tournament $tournament): bool
    {
        return $tournament->total_users >= $tournament->max_users;
    }

    /**
     * Unregister all the users and returns how many users were detached from the tournament.
     * @param \App\Models\Tournament $tournament
     * @return int
     */
    public function unregisterAllUsers(Tournament $tournament): int
    {
        return $tournament->users()->detach();
    }
}
