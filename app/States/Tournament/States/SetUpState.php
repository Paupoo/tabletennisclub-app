<?php

declare(strict_types=1);
namespace App\States\Tournament\States;

use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\States\Tournament\AbstractTournamentState;
use InvalidArgumentException;

final class SetUpState extends AbstractTournamentState
{
    public function getStatus(): TournamentStatusEnum
    {
        return TournamentStatusEnum::SETUP;
    }
    public function getAllowedTransitions(): array
    {
        return [
            TournamentStatusEnum::PUBLISHED,
            TournamentStatusEnum::PENDING,
            TournamentStatusEnum::CANCELLED,
        ];
    }

    // Actions spécifiques selon l'état
    public function canRegisterUsers(): bool
    {
        return false;
    }
    public function canCreatePools(): bool
    {
        return true;
    }
    public function canModifyPools(): bool
    {
        return true;
    }
    public function canGenerateMatches(): bool
    {
        return true;
    }
    public function canStartMatches(): bool
    {
        return false;
    }

    public function publish (Tournament $tournament): void
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

    public function cancel(Tournament $tournament): void
    {
        // TO DO : inform registered users.

        $tournament->status = TournamentStatusEnum::CANCELLED;
        $tournament->save();
    }
}
