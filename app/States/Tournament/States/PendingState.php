<?php

declare(strict_types=1);

namespace App\States\Tournament\States;

use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\States\Tournament\AbstractTournamentState;
use LogicException;

final class PendingState extends AbstractTournamentState
{
    public function setUp(Tournament $tournament): void
    {
        // Check that there is no pending matches
        $totalMatchesStarted = $tournament->matches()
            ->whereIn('status', ['in_progress', 'completed'])
            ->count();

        if ($totalMatchesStarted > 0) {
            throw new LogicException('At least one match has already started. Is not allow to setup the tournament anymore.');
        }

        $tournament->status = TournamentStatusEnum::SETUP;
        $tournament->save();
    }

    public function cancel(Tournament $tournament): void
    {
        // TO DO : warns registered users.

        $tournament->status = TournamentStatusEnum::CANCELLED;
        $tournament->save();
    }

    public function canCreatePools(): bool
    {
        return false;
    }

    public function canGenerateMatches(): bool
    {
        return false;
    }

    public function canModifyPools(): bool
    {
        return false;
    }

    // Actions spécifiques selon l'état
    public function canRegisterUsers(): bool
    {
        return false;
    }

    public function canStartMatches(): bool
    {
        return true;
    }

    public function close(Tournament $tournament): void
    {
        // Check that every matches have been played.
        $totalMatchesNotCompleted = $tournament->matches()
            ->whereNot('completed')
            ->count();

        if ($totalMatchesNotCompleted > 0) {
            throw new LogicException('Before closing the tournament, all matches must be completed. Please encode the results of the ' . $totalMatchesNotCompleted . ' remaning matches first.');
        }

        $tournament->status = TournamentStatusEnum::CLOSED;
        $tournament->save();
    }

    public function getAllowedTransitions(): array
    {
        return [
            TournamentStatusEnum::SETUP,
            TournamentStatusEnum::CLOSED,
            TournamentStatusEnum::CANCELLED,
        ];
    }

    public function getStatus(): TournamentStatusEnum
    {
        return TournamentStatusEnum::PENDING;
    }
}
