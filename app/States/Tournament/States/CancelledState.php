<?php

declare(strict_types=1);

namespace App\States\Tournament\States;

use App\Enums\TournamentStatusEnum;
use App\States\Tournament\AbstractTournamentState;

final class CancelledState extends AbstractTournamentState
{
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
        return false;
    }

    public function getAllowedTransitions(): array
    {
        return [];
    }

    public function getStatus(): TournamentStatusEnum
    {
        return TournamentStatusEnum::CANCELLED;
    }
}
