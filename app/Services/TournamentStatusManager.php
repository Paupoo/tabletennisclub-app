<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use InvalidArgumentException;
use LogicException;

final class TournamentStatusManager
{
    // How to use this class ?
    // $manager = new TournamentStatusManager($tournament);
    // $manager->setStatus(TournamentStatusEnum::LOCKED);

    public function __construct(
        public Tournament $tournament
    ) {}

    /**
     * Return the list of statuses allowed as next transitions.
     *
     * @return TournamentStatusEnum[]
     */
    public function getAllowedNextStatuses(): array
    {
        return match ($this->tournament->status) {
            TournamentStatusEnum::DRAFT => [
                TournamentStatusEnum::PUBLISHED,
            ],
            TournamentStatusEnum::PUBLISHED => [
                TournamentStatusEnum::DRAFT,
                TournamentStatusEnum::LOCKED,
                TournamentStatusEnum::CANCELLED,
            ],
            TournamentStatusEnum::LOCKED => [
                TournamentStatusEnum::PUBLISHED,
                TournamentStatusEnum::PENDING,
                TournamentStatusEnum::CANCELLED,
            ],
            TournamentStatusEnum::PENDING => [
                TournamentStatusEnum::LOCKED,
                TournamentStatusEnum::CLOSED,
                TournamentStatusEnum::CANCELLED,
            ],
            TournamentStatusEnum::CLOSED, TournamentStatusEnum::CANCELLED => [],
        };
    }

    /**
     * Change the tournament status to a new one, if allowed.
     *
     * @throws InvalidArgumentException
     */
    public function setStatus(TournamentStatusEnum $newStatus): void
    {
        $currentStatus = $this->tournament->status;

        $allowedStatuses = $this->getAllowedNextStatuses();

        if (! in_array($newStatus, $allowedStatuses, true)) {
            throw new InvalidArgumentException("Transition from {$currentStatus->value} to {$newStatus->value} is not allowed.");
        }

        // TODO: implement match checks for specific transitions
        if ($currentStatus === TournamentStatusEnum::PENDING && $newStatus === TournamentStatusEnum::LOCKED) {
            $totalMatchesStarted = $this->tournament->matches()
                ->wherein('status', ['in_progress', 'completed'])
                ->count();

            if ($totalMatchesStarted > 0) {
                throw new LogicException('At least one match has already started. Is not allow to lock the tournament anymore.');
            }
        }

        if ($currentStatus === TournamentStatusEnum::PENDING && $newStatus === TournamentStatusEnum::CANCELLED) {
            $totalMatchesStarted = $this->tournament->matches()
                ->wherein('status', ['in_progress', 'completed'])
                ->count();

            if ($totalMatchesStarted > 0) {
                throw new LogicException('At least one match has already started. Is not allow to cancel the tournament anymore');
            }
        }

        if ($currentStatus === TournamentStatusEnum::PENDING && $newStatus === TournamentStatusEnum::CLOSED) {
            // TODO: ensure a winner is calculated and no open matches remain
        }

        $this->tournament->update([
            'status' => $newStatus,
        ]);
    }
}
