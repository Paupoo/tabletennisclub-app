<?php

declare(strict_types=1);

namespace App\Domains\Shared\States\Tournament\States;

use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\States\Tournament\AbstractTournamentState;

final class ClosedState extends AbstractTournamentState
{
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
        return [];
    }

    public function getStatus(): TournamentStatusEnum
    {
        return TournamentStatusEnum::CLOSED;
    }

    #[\Override]
    public function hasBeenLaunched(): bool
    {
        // It was played and is now over.
        return true;
    }

    #[\Override]
    public function hasLockedContract(): bool
    {
        // The tournament is over; nothing about it moves again.
        return true;
    }
}
