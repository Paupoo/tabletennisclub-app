<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Notifications\InterclubAvailabilityRequestNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubLineupBroadcastNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubSelectionNotification;
use Illuminate\Support\Facades\Notification;

class InterclubAvailabilityService
{
    /**
     * Confirm the current selection, notify selected players (with ICS) and
     * broadcast the lineup to the rest of the team.
     */
    public function confirmSelection(Interclub $interclub, string $captainMessage = ''): void
    {
        $interclub->loadMissing(['visitedTeam', 'visitingTeam', 'visitedTeam.club', 'visitingTeam.club']);

        $ourTeam = $interclub->visitedTeam?->club?->licence === config('app.club_licence')
            ? $interclub->visitedTeam
            : $interclub->visitingTeam;

        $selectedPlayers = $interclub->getSelectedPlayers();
        $selectedIds = $selectedPlayers->pluck('id');

        foreach ($selectedPlayers as $player) {
            $player->notify(new InterclubSelectionNotification($interclub, $captainMessage));
        }

        $nonSelected = $ourTeam?->users()->whereNotIn('users.id', $selectedIds)->get() ?? collect();
        foreach ($nonSelected as $player) {
            $player->notify(new InterclubLineupBroadcastNotification($interclub, $selectedPlayers, $captainMessage));
        }

        $interclub->users()
            ->wherePivot('is_selected', true)
            ->each(function (User $user) use ($interclub): void {
                $interclub->users()->updateExistingPivot($user->id, [
                    'selection_confirmed_at' => now(),
                ]);
            });
    }

    /**
     * Send an availability request to all team members who haven't responded yet.
     */
    public function requestAvailability(Interclub $interclub): void
    {
        $team = $interclub->visitedTeam ?? $interclub->visitingTeam;

        if (! $team) {
            return;
        }

        $respondedUserIds = $interclub->users()->wherePivotNotNull('availability')->pluck('users.id');
        $pendingPlayers = $team->users()->whereNotIn('users.id', $respondedUserIds)->get();

        Notification::send($pendingPlayers, new InterclubAvailabilityRequestNotification($interclub));
    }
}
