<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Rules\ValidIban;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;

trait ManagesGuardians
{
    public ?string $guardianEmail = null;

    public string $guardianFirstName = '';

    public ?string $guardianIban = null;

    /** @var array<int> Linked guardian ids (source of truth, synced on save). */
    public array $guardianIds = [];

    public string $guardianLastName = '';

    public string $guardianPhone = '';

    public string $guardianSearch = '';

    public bool $showGuardianForm = false;

    public function attachGuardian(int $guardianId): void
    {
        if (! in_array($guardianId, $this->guardianIds, true)) {
            $this->guardianIds[] = $guardianId;
        }

        $this->guardianSearch = '';
        unset($this->linkedGuardians, $this->guardianSearchResults, $this->memberSearchResults);
    }

    /**
     * Link an existing club member as a guardian: reuse or create a Guardian
     * record pre-filled from the member's data, keyed by user_id.
     */
    public function attachMemberAsGuardian(int $userId): void
    {
        Gate::authorize('create', [Guardian::class, $this->user]);

        $member = User::findOrFail($userId);

        $guardian = Guardian::firstOrCreate(
            ['user_id' => $member->id],
            [
                'first_name' => $member->first_name,
                'last_name' => $member->last_name,
                'phone' => $member->phone_number,
                'email' => $member->email,
                'iban' => $member->iban,
            ],
        );

        if (! in_array($guardian->id, $this->guardianIds, true)) {
            $this->guardianIds[] = $guardian->id;
        }

        $this->guardianSearch = '';
        unset($this->linkedGuardians, $this->guardianSearchResults, $this->memberSearchResults);
    }

    public function createGuardian(): void
    {
        Gate::authorize('create', [Guardian::class, $this->user]);

        $validated = $this->validate([
            'guardianFirstName' => ['required', 'string', 'max:255'],
            'guardianLastName' => ['required', 'string', 'max:255'],
            'guardianPhone' => ['required', 'string', 'max:30'],
            'guardianEmail' => ['nullable', 'email', 'max:255'],
            'guardianIban' => ['nullable', new ValidIban],
        ]);

        $guardian = Guardian::create([
            'first_name' => $validated['guardianFirstName'],
            'last_name' => $validated['guardianLastName'],
            'phone' => $validated['guardianPhone'],
            'email' => $validated['guardianEmail'] ?? null,
            'iban' => $validated['guardianIban'] ?? null,
        ]);

        $this->guardianIds[] = $guardian->id;

        $this->reset([
            'guardianFirstName',
            'guardianLastName',
            'guardianPhone',
            'guardianEmail',
            'guardianIban',
            'showGuardianForm',
        ]);

        unset($this->linkedGuardians, $this->guardianSearchResults, $this->memberSearchResults);

        $this->success(__('Guardian added and linked.'));
    }

    public function detachGuardian(int $guardianId): void
    {
        $this->guardianIds = array_values(
            array_filter($this->guardianIds, fn (int $id): bool => $id !== $guardianId)
        );

        unset($this->linkedGuardians, $this->guardianSearchResults, $this->memberSearchResults);
    }

    /**
     * Existing guardians matching the search box, excluding already-linked ones.
     *
     * @return Collection<int, Guardian>
     */
    #[Computed()]
    public function guardianSearchResults(): Collection
    {
        $term = trim($this->guardianSearch);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return Guardian::query()
            ->whereNotIn('id', $this->guardianIds)
            ->where(function ($query) use ($term): void {
                $query->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->orderBy('last_name')
            ->limit(8)
            ->get();
    }

    /**
     * Guardians currently linked to the member (from in-memory selection).
     *
     * @return Collection<int, Guardian>
     */
    #[Computed()]
    public function linkedGuardians(): Collection
    {
        if ($this->guardianIds === []) {
            return collect();
        }

        return Guardian::whereIn('id', $this->guardianIds)->get();
    }

    /**
     * Adult club members matching the search box, who can be linked as a guardian.
     * Excludes the member being edited, minors, and members already a guardian.
     *
     * @return Collection<int, User>
     */
    #[Computed()]
    public function memberSearchResults(): Collection
    {
        $term = trim($this->guardianSearch);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return User::query()
            ->when($this->user?->exists, fn ($query) => $query->whereKeyNot($this->user->id))
            ->whereNotIn('id', Guardian::whereNotNull('user_id')->pluck('user_id'))
            ->where(function ($query): void {
                $query->whereNull('birthdate')
                    ->orWhereDate('birthdate', '<=', now()->subYears(18));
            })
            ->where(function ($query) use ($term): void {
                $query->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->orderBy('last_name')
            ->limit(8)
            ->get();
    }
}
