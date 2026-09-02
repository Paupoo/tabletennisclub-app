<?php

declare(strict_types=1);

namespace App\Domains\Shared\States\Tournament\States;

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\States\Tournament\AbstractTournamentState;

final class LockedState extends AbstractTournamentState
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
            TournamentStatusEnum::CANCELLED,
        ];
    }

    public function getStatus(): TournamentStatusEnum
    {
        return TournamentStatusEnum::LOCKED;
    }

    #[\Override]
    public function hasLockedContract(): bool
    {
        // The moment the contract is validated the name and the price freeze.
        return true;
    }

    /**
     * Open the registrations for the first time.
     *
     * The tournament becomes visible to members and inscribable. Before option
     * A this transition happened as a side effect of the first invitation
     * sent, which nothing announced.
     */
    public function publish(Tournament $tournament): void
    {
        $tournament->status = TournamentStatusEnum::PUBLISHED;
        $tournament->save();
    }
}
