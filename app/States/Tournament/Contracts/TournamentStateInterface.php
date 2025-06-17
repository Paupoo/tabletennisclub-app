<?php

namespace App\States\Tournament\Contracts;


use App\Models\Tournament;
use App\Enums\TournamentStatusEnum;

/**
 * Interface commune pour tous les états du tournoi
 */
interface TournamentStateInterface
{
    public function getStatus(): TournamentStatusEnum;
    public function canTransitionTo(TournamentStatusEnum $newStatus): bool;
    public function getAllowedTransitions(): array;
    
    // Actions spécifiques selon l'état
    public function canRegisterUsers(): bool;
    public function canCreatePools(): bool;
    public function canModifyPools(): bool;
    public function canGenerateMatches(): bool;
    public function canStartMatches(): bool;
    
    // Transitions
    public function publish(Tournament $tournament): void;
    public function unpublish(Tournament $tournament): void;
    public function setUp(Tournament $tournament): void;
    public function start(Tournament $tournament): void;
    public function close(Tournament $tournament): void;
    public function cancel(Tournament $tournament): void;
}