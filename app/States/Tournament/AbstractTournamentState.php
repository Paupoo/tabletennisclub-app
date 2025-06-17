<?php

declare(strict_types=1);

namespace App\States\Tournament;

use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\States\Tournament\Contracts\TournamentStateInterface;

abstract class AbstractTournamentState implements TournamentStateInterface
{
    public function canTransitionTo(TournamentStatusEnum $newStatus): bool
    {
        return in_array($newStatus, $this->getAllowedTransitions());
    }

    // Comportements par défaut (peuvent être surchargés)
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
    public function canStartMatches(): bool
    {
        return false;
    }
    public function canGenerateMatches(): bool
    {
        return false;
    }

    // Transitions par défaut (lèvent des exceptions)
    public function publish(Tournament $tournament): void
    {
        throw new \InvalidArgumentException("Cannot publish tournament from " . $this->getStatus()->value . " state");
    }

    public function unpublish(Tournament $tournament): void
    {
        throw new \InvalidArgumentException("Cannot unpublish tournament from " . $this->getStatus()->value . " state");
    }

    public function start(Tournament $tournament): void
    {
        throw new \InvalidArgumentException("Cannot start tournament from " . $this->getStatus()->value . " state");
    }

    public function setUp(Tournament $tournament): void
    {
        throw new \InvalidArgumentException("Cannot set up tournament from " . $this->getStatus()->value . " state");
    }

    public function close(Tournament $tournament): void
    {
        throw new \InvalidArgumentException("Cannot close tournament from " . $this->getStatus()->value . " state");
    }

    public function cancel(Tournament $tournament): void
    {
        throw new \InvalidArgumentException("Cannot cancel tournament from " . $this->getStatus()->value . " state");
    }
}
