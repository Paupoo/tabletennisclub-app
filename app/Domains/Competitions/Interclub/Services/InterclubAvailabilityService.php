<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Notifications\InterclubAvailabilityRequestNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubLineupBroadcastNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubPlayerRemovedNotification;
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

        $ourTeam = $interclub->visitedTeam?->club?->is_own_club
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
     * Notify players added/removed since the last confirmation. Removed players are
     * always informed, even if the resulting selection is incomplete. Added players
     * and the team broadcast are only notified once the selection is complete again.
     *
     * @param  array<int, int>  $addedUserIds
     * @param  array<int, int>  $removedUserIds
     */
    public function notifySelectionChange(Interclub $interclub, array $addedUserIds, array $removedUserIds, string $captainMessage = ''): void
    {
        $interclub->loadMissing(['visitedTeam', 'visitingTeam', 'visitedTeam.club', 'visitingTeam.club']);

        if ($removedUserIds !== []) {
            $removedPlayers = User::whereIn('id', $removedUserIds)->get();

            foreach ($removedPlayers as $player) {
                $player->notify(new InterclubPlayerRemovedNotification($interclub));
            }

            foreach ($removedUserIds as $userId) {
                $interclub->users()->updateExistingPivot($userId, ['selection_confirmed_at' => null]);
            }
        }

        if (! $interclub->isSelectionComplete()) {
            return;
        }

        $ourTeam = $interclub->visitedTeam?->club?->is_own_club
            ? $interclub->visitedTeam
            : $interclub->visitingTeam;

        $selectedPlayers = $interclub->getSelectedPlayers();
        $selectedIds = $selectedPlayers->pluck('id');

        if ($addedUserIds !== []) {
            foreach ($selectedPlayers->whereIn('id', $addedUserIds) as $player) {
                $player->notify(new InterclubSelectionNotification($interclub, $captainMessage));
            }

            foreach ($addedUserIds as $userId) {
                $interclub->users()->updateExistingPivot($userId, ['selection_confirmed_at' => now()]);
            }
        }

        $nonSelected = $ourTeam?->users()->whereNotIn('users.id', $selectedIds)->get() ?? collect();
        foreach ($nonSelected as $player) {
            $player->notify(new InterclubLineupBroadcastNotification($interclub, $selectedPlayers, $captainMessage, isUpdate: true));
        }
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
