<?php

declare(strict_types=1);

namespace App\Domains\Shared\Traits;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\InterclubAvailability;
use Illuminate\Database\Eloquent\Collection;

/**
 * Adds player availability and captain selection logic to event models
 * that have a users() BelongsToMany with the interclub_user pivot structure.
 */
trait HasAvailability
{
    public function deselect(User $user): void
    {
        $this->users()->updateExistingPivot($user->id, ['is_selected' => false]);
    }

    /** @return Collection<int, User> */
    public function getAvailablePlayers(): Collection
    {
        return $this->users()->wherePivot('availability', InterclubAvailability::AVAILABLE->value)->get();
    }

    /** @return Collection<int, User> */
    public function getMaybePlayers(): Collection
    {
        return $this->users()->wherePivot('availability', InterclubAvailability::MAYBE->value)->get();
    }

    /** @return Collection<int, User> */
    public function getPendingPlayers(): Collection
    {
        return $this->users()->wherePivotNull('availability')->get();
    }

    /** @return Collection<int, User> */
    public function getSelectedPlayers(): Collection
    {
        return $this->users()->wherePivot('is_selected', true)->get();
    }

    /** @return Collection<int, User> */
    public function getUnavailablePlayers(): Collection
    {
        return $this->users()->wherePivot('availability', InterclubAvailability::UNAVAILABLE->value)->get();
    }

    /**
     * Whether the lineup may be announced to the team: complete, or declared
     * short-handed by the captain and still at the minimum the rules allow.
     */
    public function isLineupReady(): bool
    {
        $selected = $this->users()->wherePivot('is_selected', true)->count();

        return $selected >= $this->total_players
            || ($this->isShortHanded() && $selected >= $this->minimumPlayers());
    }

    public function isSelectionComplete(): bool
    {
        return $this->users()->wherePivot('is_selected', true)->count() >= $this->total_players;
    }

    public function markAvailability(User $user, InterclubAvailability $availability, ?string $note = null): void
    {
        $pivotData = [
            'availability' => $availability->value,
            'availability_note' => $note,
            'is_subscribed' => $availability === InterclubAvailability::AVAILABLE,
        ];

        if ($this->users()->where('users.id', $user->id)->exists()) {
            $this->users()->updateExistingPivot($user->id, $pivotData);
        } else {
            $this->users()->attach($user->id, $pivotData);
        }
    }

    /**
     * The fewest players a team may start the fixture with: one short of a full
     * team — three of four in the men's interclub (C.25.6), two of three in the
     * ladies', youth and veterans' ones (C.25.7). Below that, the fixture cannot
     * be played at all.
     */
    public function minimumPlayers(): int
    {
        return max(1, $this->total_players - 1);
    }

    public function select(User $user): void
    {
        $pivotData = ['is_selected' => true];

        if ($this->users()->where('users.id', $user->id)->exists()) {
            $this->users()->updateExistingPivot($user->id, $pivotData);
        } else {
            $this->users()->attach($user->id, $pivotData);
        }
    }
}
