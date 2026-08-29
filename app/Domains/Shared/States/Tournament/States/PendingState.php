<?php

declare(strict_types=1);

namespace App\Domains\Shared\States\Tournament\States;

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\States\Tournament\AbstractTournamentState;
use LogicException;

final class PendingState extends AbstractTournamentState
{
    /** A match whose result has been encoded. */
    private const string MATCH_COMPLETED = 'completed';

    /**
     * Match statuses that prove the tournament has actually begun.
     *
     * Once any of these exists, walking the tournament back would strand a
     * result that has already been played and, for a cancellation, told to the
     * players in the room.
     */
    private const array PLAY_HAS_BEGUN = ['in_progress', self::MATCH_COMPLETED];

    public function setUp(Tournament $tournament): void
    {
        $this->refuseIfPlayHasBegun($tournament, 'set up');

        $tournament->status = TournamentStatusEnum::SETUP;
        $tournament->save();
    }

    public function cancel(Tournament $tournament): void
    {
        // TO DO : warns registered users.

        $this->refuseIfPlayHasBegun($tournament, 'cancel');

        $tournament->status = TournamentStatusEnum::CANCELLED;
        $tournament->save();
    }

    #[\Override]
    public function canCreatePools(): bool
    {
        return false;
    }

    #[\Override]
    public function canGenerateMatches(): bool
    {
        return false;
    }

    #[\Override]
    public function canModifyPools(): bool
    {
        return false;
    }

    // Actions spécifiques selon l'état
    #[\Override]
    public function canRegisterUsers(): bool
    {
        return false;
    }

    #[\Override]
    public function canStartMatches(): bool
    {
        return true;
    }

    public function close(Tournament $tournament): void
    {
        // Check that every matches have been played.
        $totalMatchesNotCompleted = $tournament->matches()
            ->where('status', '!=', self::MATCH_COMPLETED)
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

    #[\Override]
    public function hasBeenLaunched(): bool
    {
        // Matches are being played right now.
        return true;
    }

    #[\Override]
    public function hasLockedContract(): bool
    {
        // Play has started against this contract.
        return true;
    }

    /**
     * Refuse to walk a tournament back once any match has been played.
     *
     * @throws LogicException
     */
    private function refuseIfPlayHasBegun(Tournament $tournament, string $action): void
    {
        $matchesStarted = $tournament->matches()
            ->whereIn('status', self::PLAY_HAS_BEGUN)
            ->count();

        if ($matchesStarted > 0) {
            throw new LogicException(
                'At least one match has already started. It is not allowed to ' . $action . ' the tournament anymore.'
            );
        }
    }
}
