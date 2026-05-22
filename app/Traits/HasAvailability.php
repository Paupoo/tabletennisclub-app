<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\InterclubAvailability;
use App\Models\ClubAdmin\Users\User;
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
