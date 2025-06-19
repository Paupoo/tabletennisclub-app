<?php

declare(strict_types=1);

namespace App\States\Tournament;

use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\States\Tournament\Contracts\TournamentStateInterface;

final class TournamentStateMachine
{
    private TournamentStateInterface $state;

    public function __construct(private Tournament $tournament)
    {
        $this->state = TournamentStateFactory::create($tournament->status);
    }

    public function setUp(): void
    {
        $this->state->setUp($this->tournament);
        $this->refreshState();
    }

    public function cancel(): void
    {
        $this->state->cancel($this->tournament);
        $this->refreshState();
    }

    public function canGenerateMatches(): bool
    {
        return $this->state->canGenerateMatches();
    }

    public function canModifyPools(): bool
    {
        return $this->state->canModifyPools();
    }

    // Méthodes de vérification
    public function canRegisterUsers(): bool
    {
        return $this->state->canRegisterUsers();
    }

    public function canStartMatches(): bool
    {
        return $this->state->canStartMatches();
    }

    public function canTransitionTo(TournamentStatusEnum $newStatus): bool
    {
        return $this->state->canTransitionTo($newStatus);
    }

    public function close(): void
    {
        $this->state->close($this->tournament);
        $this->refreshState();
    }

    public function getAllowedTransitions(): array
    {
        return $this->state->getAllowedTransitions();
    }

    public function getCurrentState(): TournamentStateInterface
    {
        return $this->state;
    }

    // Actions

    public function publish(): void
    {
        $this->state->publish($this->tournament);
        $this->refreshState();
    }

    public function start(): void
    {
        $this->state->start($this->tournament);
        $this->refreshState();
    }

    public function unpublish(): void
    {
        $this->state->unpublish($this->tournament);
        $this->refreshState();
    }

    /** Sauvegarder le nouvel état dans la DB */
    private function refreshState(): void
    {
        $this->tournament->refresh();
        $this->state = TournamentStateFactory::create($this->tournament->status);
    }
}
