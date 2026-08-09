<?php

declare(strict_types=1);

namespace App\Domains\Shared\States\Tournament\States;

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\States\Tournament\AbstractTournamentState;
use InvalidArgumentException;

final class DraftState extends AbstractTournamentState
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
        return [TournamentStatusEnum::PUBLISHED];
    }

    public function getStatus(): TournamentStatusEnum
    {
        return TournamentStatusEnum::DRAFT;
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
