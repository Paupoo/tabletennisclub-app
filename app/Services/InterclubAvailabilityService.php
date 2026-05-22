<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Interclub;
use App\Notifications\Interclub\InterclubAvailabilityRequestNotification;
use App\Notifications\Interclub\InterclubSelectionNotification;
use Illuminate\Support\Facades\Notification;

class InterclubAvailabilityService
{
    /**
     * Confirm the current selection and notify each selected player with an ICS invite.
     */
    public function confirmSelection(Interclub $interclub, string $captainMessage = ''): void
    {
        $selectedPlayers = $interclub->getSelectedPlayers();

        foreach ($selectedPlayers as $player) {
            $player->notify(new InterclubSelectionNotification($interclub, $captainMessage));
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
