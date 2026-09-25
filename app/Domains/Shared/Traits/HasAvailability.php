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
    /**
     * A complete lineup at least one name of which the team has not been told:
     * saved and never sent, or changed since it was. Reads the loaded users.
     */
    public function awaitsSending(): bool
    {
        $selected = $this->users->filter(fn (User $player): bool => (bool) $player->registration?->is_selected);

        return $selected->count() >= $this->total_players
            && $selected->contains(fn (User $player): bool => $player->registration?->selection_confirmed_at === null);
    }

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

    /**
     * What a lineup mail says about playing short: how many play, out of how
     * many, and who goes on the sheet as walkover. Null for a full lineup.
     *
     * @return array{playing: int, max: int, walkover: string|null, walkover_id: int|null}|null
     */
    public function shortHandedSummary(): ?array
    {
        if (! $this->isShortHanded()) {
            return null;
        }

        $selected = $this->getSelectedPlayers();
        $walkover = $selected->first(fn (User $player): bool => (bool) $player->registration?->is_walkover);

        return [
            'playing' => $selected->count() - ($walkover ? 1 : 0),
            'max' => $this->total_players,
            'walkover' => $walkover?->full_name,
            'walkover_id' => $walkover?->id,
        ];
    }
}
