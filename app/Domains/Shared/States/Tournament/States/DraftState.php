<?php

declare(strict_types=1);

namespace App\Domains\Shared\States\Tournament\States;

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\States\Tournament\AbstractTournamentState;

final class DraftState extends AbstractTournamentState
{
    public function cancel(Tournament $tournament): void
    {
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
            TournamentStatusEnum::LOCKED,
            TournamentStatusEnum::CANCELLED,
        ];
    }

    public function getStatus(): TournamentStatusEnum
    {
        return TournamentStatusEnum::DRAFT;
    }

    /**
     * Validate the contract: the name and the price stop being editable.
     *
     * This is the wizard's fourth step. It does not open anything — a locked
     * tournament is still invisible to members until the registrations are
     * explicitly opened (issue #35).
     */
    public function lock(Tournament $tournament): void
    {
        $tournament->status = TournamentStatusEnum::LOCKED;
        $tournament->save();
    }
}
