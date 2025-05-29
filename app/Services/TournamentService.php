<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;
use App\Models\Pool;
use Exception;
use Illuminate\Support\Collection;

class TournamentService
{

    /**
     * Check if there the tournament has reached its maximum amount of players
     *
     * @param Tournament $tournament
     * @return boolean
     */
    public function IsFull(Tournament $tournament): bool
    {
        return ($tournament->total_users >= $tournament->max_users);
    }

    public function countRegisteredUsers(Tournament $tournament): Int
    {
        $totalUsers = $tournament->users->count();
        $tournament->total_users = $totalUsers;
        $tournament->save();

        return $totalUsers;
    }
}
