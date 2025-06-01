<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;

class TournamentService
{
    /**
     * Check if there the tournament has reached its maximum amount of players
     */
    public function IsFull(Tournament $tournament): bool
    {
        return $tournament->total_users >= $tournament->max_users;
    }

    public function countRegisteredUsers(Tournament $tournament): int
    {
        $totalUsers = $tournament->users->count();
        $tournament->total_users = $totalUsers;
        $tournament->save();

        return $totalUsers;
    }
}
