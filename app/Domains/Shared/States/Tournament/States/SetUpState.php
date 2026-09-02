<?php

declare(strict_types=1);

namespace App\Domains\Shared\States\Tournament\States;

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\States\Tournament\AbstractTournamentState;
use InvalidArgumentException;

final class SetUpState extends AbstractTournamentState
{
    public function cancel(Tournament $tournament): void
    {
        // TO DO : inform registered users.

        $tournament->status = TournamentStatusEnum::CANCELLED;
        $tournament->save();
    }

    #[\Override]
    public function canCreatePools(): bool
    {
        return true;
    }

    #[\Override]
    public function canGenerateMatches(): bool
    {
        return true;
    }

    #[\Override]
    public function canModifyPools(): bool
    {
        return true;
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
        return false;
    }

    public function getAllowedTransitions(): array
    {
        return [
            TournamentStatusEnum::PUBLISHED,
            TournamentStatusEnum::PENDING,
            TournamentStatusEnum::CANCELLED,
        ];
    }

    public function getStatus(): TournamentStatusEnum
    {
        return TournamentStatusEnum::SETUP;
    }

    #[\Override]
    public function hasLockedContract(): bool
    {
        // Registrations have been taken: the contract cannot move now.
        return true;
    }

    public function publish(Tournament $tournament): void
    {
        $tournament->status = TournamentStatusEnum::PUBLISHED;
        $tournament->save();
    }

    public function start(Tournament $tournament): void
    {
        if ($tournament->pools()->count() === 0) {
            throw new InvalidArgumentException('Please generate pools first');
        }

        if ($tournament->matches()->count() === 0) {
            throw new InvalidArgumentException('Please generate matches first');
        }

        $tournament->status = TournamentStatusEnum::PENDING;
        $tournament->save();
    }
}
