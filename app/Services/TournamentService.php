<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\Tournament\UserRegisteredToTournament;
use App\Models\Tournament;
use App\Models\User;
use Event;
use LogicException;

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

    public function registerUser(Tournament $tournament, User $user)
    {

        if ($this->IsFull($tournament)) {
            throw new LogicException('Sorry, the tournament is full, you cannot register more players.');
        }

        // Vérifier si le joueur n'est pas déjà inscrit
        if ($tournament->users->contains($user)) {
            throw new LogicException('This player is already registered to this tournament.');
        }

        $tournament->users()
            ->attach($user);

        $this->countRegisteredUsers($tournament);

        Event::dispatch(new UserRegisteredToTournament($tournament, $user));
    }
}
