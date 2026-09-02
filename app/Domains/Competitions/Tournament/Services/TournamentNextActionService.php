<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Services;

use App\Data\Tournament\NextAction;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;

/**
 * What each tournament is waiting for from the committee.
 *
 * The list showed four counters nobody reads twice and never answered the
 * question asked on every visit: which of these needs me? The rule lives here
 * rather than in a `match` inside a view, beside the state machine that already
 * knows which transitions a status allows -- and where it can be tested.
 */
final class TournamentNextActionService
{
    public function for(Tournament $tournament): ?NextAction
    {
        return match ($tournament->status) {
            TournamentStatusEnum::DRAFT => new NextAction(
                label: __('Finish the setup'),
                url: route('admin.tournaments.wizard.edit', [$tournament, 'step' => 1]),
            ),

            TournamentStatusEnum::LOCKED => new NextAction(
                label: __('Open registrations'),
                url: route('admin.tournaments.wizard.edit', [$tournament, 'step' => 4]),
            ),

            TournamentStatusEnum::PUBLISHED => $this->whileRegistrationsAreOpen($tournament),

            TournamentStatusEnum::SETUP => new NextAction(
                label: __('Draw the pools'),
                url: route('admin.tournaments.wizard.edit', [$tournament, 'step' => 6]),
            ),

            TournamentStatusEnum::PENDING => new NextAction(
                label: __('Open the control room'),
                url: route('admin.tournaments.live-center', $tournament),
            ),

            // Un tournoi terminé ou annulé n'attend plus rien.
            TournamentStatusEnum::CLOSED,
            TournamentStatusEnum::CANCELLED => null,
        };
    }

    /**
     * Registrations are open: the tournament mostly waits for its members.
     *
     * Two things can still be owed. An open tournament nobody can find is the
     * more urgent of the two -- members reach it from the website -- and once
     * the deadline has passed, waiting is no longer the plan.
     */
    private function whileRegistrationsAreOpen(Tournament $tournament): ?NextAction
    {
        if ($tournament->registration_deadline?->isPast() === true) {
            return new NextAction(
                label: __('Close registrations'),
                url: route('admin.tournaments.wizard.edit', [$tournament, 'step' => 5]),
                urgent: true,
            );
        }

        if (! $tournament->isOnPublicWebsite()) {
            return new NextAction(
                label: __('Publish on the website'),
                url: route('admin.tournaments.wizard.edit', [$tournament, 'step' => 2]),
                urgent: true,
            );
        }

        return null;
    }
}
