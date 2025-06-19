<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tournament;

use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\States\Tournament\TournamentStateMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChangeTournamentStatusController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Tournament $tournament, TournamentStatusEnum $newStatus): RedirectResponse
    {
        $stateMachine = new TournamentStateMachine($tournament);

        if (! $stateMachine->canTransitionTo($newStatus)) {
            return redirect()
                ->back()
                ->with('error', "Cannot transition from {$tournament->status->value} to {$newStatus->value}");
        }

        try {
            // Déléguer à la state machine selon le nouveau statut
            match ($newStatus) {
                TournamentStatusEnum::DRAFT => $stateMachine->unpublish(),
                TournamentStatusEnum::PUBLISHED => $stateMachine->publish(),
                TournamentStatusEnum::SETUP => $stateMachine->setUp(),
                TournamentStatusEnum::PENDING => $stateMachine->start(),
                TournamentStatusEnum::CLOSED => $stateMachine->close(),
                TournamentStatusEnum::CANCELLED => $stateMachine->cancel(),
                default => throw new \InvalidArgumentException("Unsupported transition to {$newStatus->value}")
            };

            return redirect()
                ->back()
                ->with('success', "Tournament status updated to {$newStatus->value}");

        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }
}
