<?php

declare(strict_types=1);
namespace App\States\Tournament\States;

use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\States\Tournament\AbstractTournamentState;
use InvalidArgumentException;

final class DraftState extends AbstractTournamentState
{
    public function getStatus(): TournamentStatusEnum
    {
        return TournamentStatusEnum::DRAFT;
    }

    public function getAllowedTransitions(): array
    {
        return [TournamentStatusEnum::PUBLISHED];
    }

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

    public function publish(Tournament $tournament): void
    {
        if ($tournament->tables()->count() === 0) {
            throw new InvalidArgumentException('Cannot publish tournament without at least one table');
        }

        if ($tournament->start_date <= today()) {
            throw new InvalidArgumentException('Cannot public a tournament not in the future.');
        }

        $tournament->status = TournamentStatusEnum::PUBLISHED;
        $tournament->save();
    }
}
