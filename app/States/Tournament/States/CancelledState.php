<?php

declare(strict_types=1);
namespace App\States\Tournament\States;

use App\Enums\TournamentStatusEnum;
use App\States\Tournament\AbstractTournamentState;

final class CancelledState extends AbstractTournamentState
{
    public function getStatus(): TournamentStatusEnum
    {
        return TournamentStatusEnum::CANCELLED;
    }
    public function getAllowedTransitions(): array
    {
        return [];
    }
    
    // Actions spécifiques selon l'état
    public function canRegisterUsers(): bool
    {
        return false;
    }
    public function canCreatePools(): bool
    {
        return false;
    }
    public function canModifyPools(): bool
    {
        return false;
    }
    public function canGenerateMatches(): bool
    {
        return false;
    }
    public function canStartMatches(): bool
    {
        return false;
    }
}