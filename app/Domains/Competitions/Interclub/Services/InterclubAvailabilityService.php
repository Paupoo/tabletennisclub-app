<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Jobs\SendInterclubAvailabilityRequestJob;
use App\Jobs\SendInterclubLineupBroadcastJob;
use App\Jobs\SendInterclubPlayerRemovedJob;
use App\Jobs\SendInterclubSelectionJob;

/**
 * Everything this screen mails leaves through the queue.
 *
 * It used to notify inline: a lineup is a dozen recipients, each with an ICS
 * attachment built on the spot, so confirming a team meant a dozen blocking
 * SMTP round trips in the middle of a Livewire request. The screen froze for
 * ten to twenty seconds with no indication it was working, and captains
 * clicked again. The jobs carry the `convocations` limiter, like every other
 * club mailing that announces a date.
 */
class InterclubAvailabilityService
{
    /**
     * Confirm the current selection, notify selected players (with ICS) and
     * broadcast the lineup to the rest of the team.
     */
    public function confirmSelection(Interclub $interclub, string $captainMessage = ''): void
    {
        $interclub->loadMissing(['visitedTeam', 'visitingTeam', 'visitedTeam.club', 'visitingTeam.club']);

        $this->rememberCaptainMessage($interclub, $captainMessage);

        $ourTeam = $interclub->ourTeam();

        $selectedPlayers = $interclub->getSelectedPlayers();
        $selectedIds = $selectedPlayers->pluck('id');

        foreach ($selectedPlayers as $player) {
            SendInterclubSelectionJob::dispatch($interclub->id, $player->id, $captainMessage);
        }

        $nonSelected = $ourTeam?->users()->whereNotIn('users.id', $selectedIds)->get() ?? collect();
        foreach ($nonSelected as $player) {
            SendInterclubLineupBroadcastJob::dispatch(
                $interclub->id,
                $player->id,
                $selectedIds->all(),
                $captainMessage,
            );
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

        $this->rememberCaptainMessage($interclub, $captainMessage);

        foreach ($removedUserIds as $userId) {
            SendInterclubPlayerRemovedJob::dispatch($interclub->id, $userId);
            $interclub->users()->updateExistingPivot($userId, ['selection_confirmed_at' => null]);
        }

        if (! $interclub->isSelectionComplete()) {
            return;
        }

        $ourTeam = $interclub->ourTeam();

        $selectedPlayers = $interclub->getSelectedPlayers();
        $selectedIds = $selectedPlayers->pluck('id');

        if ($addedUserIds !== []) {
            foreach ($selectedPlayers->whereIn('id', $addedUserIds) as $player) {
                SendInterclubSelectionJob::dispatch($interclub->id, $player->id, $captainMessage);
            }

            foreach ($addedUserIds as $userId) {
                $interclub->users()->updateExistingPivot($userId, ['selection_confirmed_at' => now()]);
            }
        }

        $nonSelected = $ourTeam?->users()->whereNotIn('users.id', $selectedIds)->get() ?? collect();
        foreach ($nonSelected as $player) {
            SendInterclubLineupBroadcastJob::dispatch(
                $interclub->id,
                $player->id,
                $selectedIds->all(),
                $captainMessage,
                isUpdate: true,
            );
        }
    }

    /**
     * Send an availability request to all team members who haven't responded yet.
     */
    public function requestAvailability(Interclub $interclub): void
    {
        // Our side of the fixture, never the home side: on an away match the
        // home team is the opponent's, whose roster lives in their club and not
        // in ours — the request then went to nobody, in silence, on half the
        // calendar. Same rule as confirmSelection() and notifySelectionChange().
        $team = $interclub->ourTeam();

        if (! $team) {
            return;
        }

        $respondedUserIds = $interclub->users()->wherePivotNotNull('availability')->pluck('users.id');
        $pendingPlayers = $team->users()->whereNotIn('users.id', $respondedUserIds)->get();

        foreach ($pendingPlayers as $player) {
            SendInterclubAvailabilityRequestJob::dispatch($interclub->id, $player->id);
        }
    }

    /**
     * Keep the captain's meet-up instructions on the fixture.
     *
     * "RDV 19h au club, maillot rouge" used to exist only inside a sent mail:
     * the screen's property was handed to the jobs and then reset, so the match
     * page could show everything about the evening except the one sentence a
     * player actually needed on the night.
     *
     * An empty message never erases a stored one. A captain who re-sends a
     * lineup without retyping their instructions means "same as before", not
     * "forget what I said".
     */
    private function rememberCaptainMessage(Interclub $interclub, string $captainMessage): void
    {
        if (trim($captainMessage) === '') {
            return;
        }

        $interclub->update(['captain_message' => $captainMessage]);
    }
}
