<?php

declare(strict_types=1);

namespace App\Domains\Shared\States\Tournament\States;

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\States\Tournament\AbstractTournamentState;
use InvalidArgumentException;

final class PublishedState extends AbstractTournamentState
{
    private readonly TournamentService $tournamentService;

    public function __construct()
    {
        $this->tournamentService = new TournamentService;
    }

    public function setUp(Tournament $tournament): void
    {
        if ($tournament->users()->count() === 0) {
            throw new InvalidArgumentException('Cannot setup a tournament without players');
        }

        $tournament->status = TournamentStatusEnum::SETUP;
        $tournament->save();
    }

    public function cancel(Tournament $tournament): void
    {
        // TO DO : inform registered users.

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
        return true;
    }

    #[\Override]
    public function canStartMatches(): bool
    {
        return false;
    }

    public function getAllowedTransitions(): array
    {
        return [
            TournamentStatusEnum::DRAFT,
            TournamentStatusEnum::SETUP,
            TournamentStatusEnum::CANCELLED,
        ];
    }

    public function getStatus(): TournamentStatusEnum
    {
        return TournamentStatusEnum::PUBLISHED;
    }

    #[\Override]
    public function hasLockedContract(): bool
    {
        // Members are entering against a published name and price.
        return true;
    }

    public function unpublish(Tournament $tournament): void
    {
        $this->tournamentService->unregisterAllUsers($tournament);

        $tournament->status = TournamentStatusEnum::DRAFT;
        $tournament->save();
    }
}
