<?php

declare(strict_types=1);

use App\Actions\User\MergeGuardianAction;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\GuardianDuplicates;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Rules\ValidIban;
use App\Domains\Shared\Rules\ValidPhone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

/*
 * Creating or correcting a responsible adult.
 *
 * Only a guardian with no account of their own is corrected here: one who holds
 * an account keeps their details on it, and changes them in their own profile.
 * One sheet stands for one person across every child they answer for, so the
 * drawer names the members the correction reaches.
 *
 * The office also types new adults in here, from the list of them. A new sheet
 * always answers for at least one child: one that answers for no one reaches
 * no one, and is how the club ended up with adults on file twice.
 */
new class extends Component
{
    use Toast;

    /** @var array<int, int> The children the sheet answers for, as the office lays them out. */
    public array $childIds = [];

    public string $childSearch = '';

    public bool $drawer = false;

    /** The sheet already on file for the adult being typed in, if any. */
    public ?int $duplicateGuardianId = null;

    public ?string $email = null;

    public string $firstName = '';

    public ?int $guardianId = null;

    public ?string $iban = null;

    public string $lastName = '';

    public string $phone = '';

    public function addChild(int $childId): void
    {
        Gate::authorize(Permission::UsersUpdate->value);

        $child = User::findOrFail($childId);

        if (! in_array($child->id, $this->childIds, true)) {
            $this->childIds[] = $child->id;
        }

        $this->childSearch = '';
        unset($this->children, $this->childSearchResults);
    }

    /**
     * Members a search names, to lay out as children of the sheet. Archived
     * members are left out: their file no longer opens.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function childSearchResults(): Collection
    {
        if (mb_strlen(trim($this->childSearch)) < 2) {
            return new Collection;
        }

        return User::query()
            ->whereKeyNot($this->childIds)
            ->searchName($this->childSearch)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(8)
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function children(): Collection
    {
        return User::query()->whereKey($this->childIds)->orderBy('first_name')->get();
    }

    #[On('create-guardian')]
    public function create(): void
    {
        Gate::authorize('create', Guardian::class);

        $this->resetValidation();
        $this->reset(['guardianId', 'firstName', 'lastName', 'phone', 'email', 'iban', 'childIds', 'childSearch', 'duplicateGuardianId']);
        $this->drawer = true;

        unset($this->guardian, $this->counterpart, $this->children, $this->duplicateGuardian);
    }

    /** The sheet already on file for the adult being typed in. */
    #[Computed]
    public function duplicateGuardian(): ?Guardian
    {
        return $this->duplicateGuardianId === null ? null : Guardian::find($this->duplicateGuardianId);
    }

    /** The adult typed in was already on file: their children join that sheet. */
    public function linkChildrenToDuplicate(): void
    {
        Gate::authorize('create', Guardian::class);

        $existing = Guardian::findOrFail($this->duplicateGuardianId);
        $existing->users()->syncWithoutDetaching($this->childIds);

        $this->drawer = false;
        $this->dispatch('guardian-updated');
        $this->success(__('The children now answer to :name.', ['name' => $existing->full_name]));
    }

    public function removeChild(int $childId): void
    {
        Gate::authorize(Permission::UsersUpdate->value);

        $this->childIds = array_values(array_filter($this->childIds, fn (int $id): bool => $id !== $childId));

        unset($this->children);
    }

    #[On('edit-guardian')]
    public function edit(int $guardianId): void
    {
        $guardian = Guardian::findOrFail($guardianId);

        Gate::authorize('update', $guardian);

        $this->resetValidation();
        $this->guardianId = $guardian->id;
        $this->firstName = $guardian->first_name;
        $this->lastName = $guardian->last_name;
        $this->phone = (string) $guardian->phone;
        $this->email = $guardian->email;
        $this->iban = $guardian->iban;
        $this->duplicateGuardianId = null;
        $this->childSearch = '';
        // Only the office lays out children; archived ones stay linked, unseen.
        $this->childIds = $this->managesChildren() ? $guardian->users()->pluck('users.id')->all() : [];
        $this->drawer = true;

        unset($this->guardian, $this->counterpart, $this->children, $this->duplicateGuardian);
    }

    public function merge(): void
    {
        $guardian = Guardian::findOrFail($this->guardianId);

        Gate::authorize('merge', $guardian);

        $counterpart = (new GuardianDuplicates)->counterpartOf($guardian);

        if ($counterpart === null) {
            return;
        }

        MergeGuardianAction::handle($guardian, $counterpart);

        $this->drawer = false;
        $this->guardianId = null;
        unset($this->guardian, $this->counterpart);
        $this->dispatch('guardian-updated');
        $this->success(__('The two records are now one.'));
    }

    /**
     * The person this sheet is already on file as, shown to whoever may merge
     * them. A member correcting their parent never sees it: their correction is
     * kept, and the office finds the pair on the dashboard.
     */
    #[Computed]
    public function counterpart(): Guardian|User|null
    {
        $guardian = $this->guardian;

        if (! $guardian instanceof Guardian || Gate::denies('merge', $guardian)) {
            return null;
        }

        return (new GuardianDuplicates)->counterpartOf($guardian);
    }

    /** The sheet being corrected. */
    #[Computed]
    public function guardian(): ?Guardian
    {
        return $this->guardianId === null ? null : Guardian::with('users')->find($this->guardianId);
    }

    public function save(): void
    {
        if ($this->guardianId === null) {
            $this->store();

            return;
        }

        $guardian = Guardian::findOrFail($this->guardianId);

        Gate::authorize('update', $guardian);

        $validated = $this->validate($this->detailsRules());

        if ($this->managesChildren() && ! $this->layOutChildren($guardian)) {
            return;
        }

        $guardian->update([
            'first_name' => $validated['firstName'],
            'last_name' => $validated['lastName'],
            'phone' => $validated['phone'],
            'email' => filled($validated['email']) ? trim($validated['email']) : null,
            'iban' => $validated['iban'] ?? null,
        ]);

        unset($this->guardian, $this->counterpart);
        $this->dispatch('guardian-updated');
        $this->success(__('Guardian details updated.'));

        // The correction may reveal a person already on file: the office stays
        // in the drawer to merge them.
        if ($this->counterpart === null) {
            $this->drawer = false;
        }
    }

    /**
     * Links the children added and unlinks the ones removed. Archived children
     * are never in the list, so they stay linked. A minor is never left with
     * no responsible adult: the office names another one first.
     */
    private function layOutChildren(Guardian $guardian): bool
    {
        $current = $guardian->users()->get();
        $removed = $current->reject(fn (User $child): bool => in_array($child->id, $this->childIds, true));

        $stranded = $removed->filter(fn (User $child): bool => $child->isMinor()
            && $child->guardians()->whereKeyNot($guardian->id)->doesntExist());

        if ($stranded->isNotEmpty()) {
            $this->addError('childIds', __(':names would be left with no responsible adult: add another one on their file first.', [
                'names' => $stranded->map(fn (User $child): string => $child->first_name . ' ' . $child->last_name)->join(', '),
            ]));

            return false;
        }

        $guardian->users()->detach($removed->modelKeys());
        $guardian->users()->syncWithoutDetaching($this->childIds);

        return true;
    }

    /** Whether the person in the drawer may decide which children a sheet answers for. */
    private function managesChildren(): bool
    {
        return Gate::allows(Permission::UsersUpdate->value);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function detailsRules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', new ValidPhone],
            'email' => ['nullable', 'email', 'max:255'],
            'iban' => ['nullable', new ValidIban],
        ];
    }

    /**
     * A new sheet, for the children laid out. An adult already on file by their
     * email or phone is not typed in twice: the office links the children to
     * the sheet that exists instead.
     */
    private function store(): void
    {
        Gate::authorize('create', Guardian::class);

        $validated = $this->validate([
            ...$this->detailsRules(),
            'childIds' => ['required', 'array', 'min:1'],
            'childIds.*' => ['integer', 'exists:users,id,deleted_at,NULL'],
        ], [
            'childIds.required' => __('Choose at least one child this adult answers for.'),
        ]);

        $existing = (new GuardianDuplicates)->existingFor($validated['phone'], $validated['email'] ?? null);

        if ($existing instanceof Guardian) {
            $this->duplicateGuardianId = $existing->id;
            unset($this->duplicateGuardian);

            return;
        }

        $guardian = Guardian::create([
            'first_name' => $validated['firstName'],
            'last_name' => $validated['lastName'],
            'phone' => $validated['phone'],
            'email' => filled($validated['email']) ? trim($validated['email']) : null,
            'iban' => $validated['iban'] ?? null,
        ]);
        $guardian->users()->attach($this->childIds);

        $this->drawer = false;
        $this->dispatch('guardian-updated');
        $this->success(__(':name was added.', ['name' => $guardian->full_name]));
    }
};

?>

<div>
    <x-drawer wire:model="drawer" :title="$guardianId === null ? __('New responsible adult') : __('Edit the responsible adult')"
        right separator with-close-button class="w-full max-w-xl">
        @if ($this->guardian || ($drawer && $guardianId === null))
            <x-form wire:submit="save">
                @if ($this->duplicateGuardian)
                    <x-alert icon="o-exclamation-triangle" class="alert-warning alert-soft">
                        <div class="space-y-2 text-sm">
                            <p>{{ __(':name is already on file with this email or phone number.', ['name' => $this->duplicateGuardian->full_name]) }}</p>
                            <x-button :label="__('Link the children to this sheet')" class="btn-warning btn-sm"
                                wire:click="linkChildrenToDuplicate" spinner="linkChildrenToDuplicate" />
                        </div>
                    </x-alert>
                @endif

                @if ($this->guardian && $this->guardian->users->count() > 1)
                    <x-alert icon="o-user-group" class="alert-info alert-soft">
                        <span class="text-sm">
                            {{ __('Shared with: :names', ['names' => $this->guardian->users->pluck('first_name')->join(', ')]) }}
                        </span>
                    </x-alert>
                @endif

                @if ($this->counterpart)
                    @php
                        $wardsOfCounterpart = $this->counterpart instanceof User
                            ? ($this->counterpart->guardianRecord?->users ?? collect())
                            : $this->counterpart->users;
                    @endphp
                    <x-alert icon="o-exclamation-triangle" class="alert-warning alert-soft">
                        <div class="space-y-2 text-sm">
                            <p>{{ __('This person is already on file as :name.', ['name' => $this->counterpart->full_name]) }}</p>
                            @if ($wardsOfCounterpart->isNotEmpty())
                                <p>{{ __('Responsible for: :names', ['names' => $wardsOfCounterpart->pluck('first_name')->join(', ')]) }}</p>
                            @elseif ($this->counterpart instanceof User)
                                <p>{{ __('They are a member of the club.') }}</p>
                            @endif
                            <x-button :label="__('Merge the two records')" class="btn-warning btn-sm" wire:click="merge"
                                spinner="merge" />
                        </div>
                    </x-alert>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-input :label="__('First name')" wire:model="firstName" required />
                    <x-input :label="__('Last name')" wire:model="lastName" required />
                    <x-input :label="__('Phone')" wire:model="phone" placeholder="0470 00 00 00" required />
                    <x-input :label="__('Email')" type="email" wire:model="email" />
                    <x-input :label="__('IBAN')" wire:model="iban" placeholder="BE00 0000 0000 0000"
                        :hint="__('Optional — used for refunds.')" class="sm:col-span-2" />
                </div>

                @can(\App\Domains\Shared\Enums\Permission::UsersUpdate->value)
                    <x-admin.users.guardian-children-picker />
                @endcan

                <x-slot:actions>
                    <x-button :label="__('Cancel')" @click="$wire.drawer = false" />
                    <x-button :label="__('Save')" type="submit" class="btn-primary" spinner="save" />
                </x-slot:actions>
            </x-form>
        @endif
    </x-drawer>
</div>
