<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\Tournament\UserRegisteredToTournament;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
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
     * Check if the tournament has reached its maximum amount of players
     */
    public function isFull(Tournament $tournament): bool
    {
        return $tournament->total_users >= $tournament->max_users;
    }

    /**
     * @param Tournament $tournament
     * @param User $user
     * @return void
     */
    public function registerUser(Tournament $tournament, User $user): void
    {

        if ($this->isFull($tournament)) {
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

    /**
     * Unregister all the users and returns how many users were detached from the tournament.
     */
    public function unregisterAllUsers(Tournament $tournament): int
    {
        return $tournament->users()->detach();
    }
}
