<?php

declare(strict_types=1);

namespace App\Domains\Shared\States\Tournament\Contracts;

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;

/**
 * Interface commune pour tous les états du tournoi
 */
interface TournamentStateInterface
{
    public function setUp(Tournament $tournament): void;

    public function cancel(Tournament $tournament): void;

    public function canCreatePools(): bool;

    public function canGenerateMatches(): bool;

    public function canModifyPools(): bool;

    // Actions spécifiques selon l'état
    public function canRegisterUsers(): bool;

    public function canStartMatches(): bool;

    public function canTransitionTo(TournamentStatusEnum $newStatus): bool;

    public function close(Tournament $tournament): void;

    public function getAllowedTransitions(): array;

    public function getStatus(): TournamentStatusEnum;

    // Transitions
    public function publish(Tournament $tournament): void;

    public function start(Tournament $tournament): void;

    public function unpublish(Tournament $tournament): void;
}
