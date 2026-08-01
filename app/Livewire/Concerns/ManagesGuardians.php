<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Rules\ValidIban;
use App\Domains\Shared\Rules\ValidPhone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;

trait ManagesGuardians
{
    /** Guardian already on file matching the details being typed, if any. */
    public ?int $duplicateGuardianId = null;

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
        unset(
            $this->linkedGuardians,
            $this->guardianSearchResults,
            $this->memberSearchResults,
            $this->duplicateGuardian,
            $this->duplicateGuardianAlreadyLinked,
        );
    }

    /**
     * Link an existing club member as a guardian: reuse or create a Guardian
     * record pre-filled from the member's data, keyed by user_id.
     */
    public function attachMemberAsGuardian(int $userId): void
    {
        Gate::authorize('create', [Guardian::class, $this->guardianSubject()]);

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
        Gate::authorize('create', [Guardian::class, $this->guardianSubject()]);

        $validated = $this->validate($this->guardianRules());

        $duplicate = $this->findDuplicateGuardian($validated['guardianPhone'], $validated['guardianEmail'] ?? null);

        if ($duplicate instanceof Guardian) {
            $this->duplicateGuardianId = $duplicate->id;
            unset($this->duplicateGuardian, $this->duplicateGuardianAlreadyLinked);

            return;
        }

        $guardian = Guardian::create([
            'first_name' => $validated['guardianFirstName'],
            'last_name' => $validated['guardianLastName'],
            'phone' => $validated['guardianPhone'],
            'email' => $validated['guardianEmail'] ?? null,
            'iban' => $validated['guardianIban'] ?? null,
        ]);

        $this->guardianIds[] = $guardian->id;

        $this->resetGuardianForm();

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
     * The guardian already on file whose email or phone matches what is being
     * typed in the creation form. Drives the "this guardian already exists"
     * notice offering to link them instead of creating a duplicate.
     */
    #[Computed()]
    public function duplicateGuardian(): ?Guardian
    {
        if ($this->duplicateGuardianId === null) {
            return null;
        }

        return Guardian::find($this->duplicateGuardianId);
    }

    /** Whether the detected duplicate is already one of this member's guardians. */
    #[Computed()]
    public function duplicateGuardianAlreadyLinked(): bool
    {
        return $this->duplicateGuardianId !== null
            && in_array($this->duplicateGuardianId, $this->guardianIds, true);
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
     * Link the guardian detected as a duplicate rather than creating a second
     * record for the same person.
     */
    public function linkDuplicateGuardian(): void
    {
        if ($this->duplicateGuardianId === null) {
            return;
        }

        $this->attachGuardian($this->duplicateGuardianId);
        $this->resetGuardianForm();

        $this->success(__('Existing guardian linked.'));
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

        $subject = $this->guardianSubject();

        return User::query()
            ->when($subject?->exists, fn ($query) => $query->whereKeyNot($subject->id))
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

    /**
     * Validate each guardian field as the member leaves it, so mistakes surface
     * where they were made instead of on the "Add guardian" click.
     */
    public function updatedManagesGuardians(string $property): void
    {
        $rules = $this->guardianRules();

        if (! array_key_exists($property, $rules)) {
            return;
        }

        // The details that identified the duplicate just changed: re-check on submit.
        if ($property === 'guardianEmail' || $property === 'guardianPhone') {
            $this->duplicateGuardianId = null;
            unset($this->duplicateGuardian, $this->duplicateGuardianAlreadyLinked);
        }

        $this->validateOnly($property, $rules);
    }

    /**
     * Rules for the guardian email address.
     *
     * Optional by default: the secretary often encodes a guardian over the
     * phone and does not have their address at hand. The onboarding wizard,
     * where the family fills the form itself, overrides this to require it.
     *
     * @return array<int, mixed>
     */
    protected function guardianEmailRules(): array
    {
        return ['nullable', 'email', 'max:255'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function guardianRules(): array
    {
        return [
            'guardianFirstName' => ['required', 'string', 'max:255'],
            'guardianLastName' => ['required', 'string', 'max:255'],
            'guardianPhone' => ['required', 'string', 'max:30', new ValidPhone],
            'guardianEmail' => $this->guardianEmailRules(),
            'guardianIban' => ['nullable', new ValidIban],
        ];
    }

    /**
     * The member the guardian is being encoded for, when the component edits
     * exactly one. Components that handle a whole group in one go — the
     * walk-in affiliation drawer — have no single subject and leave it null.
     */
    protected function guardianSubject(): ?User
    {
        return $this->user ?? null;
    }

    /**
     * An existing guardian sharing the email or the phone number just entered.
     *
     * Phone numbers are stored as typed, so the comparison runs on the
     * normalized value in PHP: `+32 475 12 34 56` and `0475123456` are the
     * same person. The table holds one row per guardian of the club, so the
     * scan stays cheap.
     */
    private function findDuplicateGuardian(string $phone, ?string $email): ?Guardian
    {
        if (filled($email)) {
            $byEmail = Guardian::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->first();

            if ($byEmail instanceof Guardian) {
                return $byEmail;
            }
        }

        $normalizedPhone = ValidPhone::normalize($phone);

        if ($normalizedPhone === null) {
            return null;
        }

        return Guardian::query()
            ->whereNotNull('phone')
            ->get()
            ->first(fn (Guardian $guardian): bool => ValidPhone::normalize($guardian->phone) === $normalizedPhone);
    }

    /** Clear the inline creation form and every cache derived from it. */
    private function resetGuardianForm(): void
    {
        $this->reset([
            'guardianFirstName',
            'guardianLastName',
            'guardianPhone',
            'guardianEmail',
            'guardianIban',
            'showGuardianForm',
            'duplicateGuardianId',
        ]);

        unset(
            $this->linkedGuardians,
            $this->guardianSearchResults,
            $this->memberSearchResults,
            $this->duplicateGuardian,
            $this->duplicateGuardianAlreadyLinked,
        );
    }
}
