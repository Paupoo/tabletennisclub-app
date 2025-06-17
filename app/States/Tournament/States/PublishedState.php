<?php

declare(strict_types=1);
namespace App\States\Tournament\States;

use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\Services\TournamentService;
use App\States\Tournament\AbstractTournamentState;
use InvalidArgumentException;

final class PublishedState extends AbstractTournamentState
{
    private TournamentService $tournamentService;
    public function __construct() {
        $this->tournamentService = new TournamentService();
    }
    public function getStatus(): TournamentStatusEnum
    {
        return TournamentStatusEnum::PUBLISHED;
    }

    public function getAllowedTransitions(): array
    {
        return [
            TournamentStatusEnum::DRAFT,
            TournamentStatusEnum::SETUP,
            TournamentStatusEnum::CANCELLED,
        ];
    }

    public function canRegisterUsers(): bool
    {
        return true;
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

    public function unpublish(Tournament $tournament): void
    {
        $this->tournamentService->unregisterAllUsers($tournament);

        $tournament->status = TournamentStatusEnum::DRAFT;
        $tournament->save();
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

}