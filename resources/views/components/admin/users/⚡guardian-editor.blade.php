<?php

declare(strict_types=1);

use App\Actions\User\MergeGuardianAction;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\GuardianDuplicates;
use App\Domains\Shared\Rules\ValidIban;
use App\Domains\Shared\Rules\ValidPhone;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

/*
 * Correcting a responsible adult.
 *
 * Only a guardian with no account of their own is corrected here: one who holds
 * an account keeps their details on it, and changes them in their own profile.
 * One sheet stands for one person across every child they answer for, so the
 * drawer names the members the correction reaches.
 */
new class extends Component
{
    use Toast;

    public bool $drawer = false;

    public ?string $email = null;

    public string $firstName = '';

    public ?int $guardianId = null;

    public ?string $iban = null;

    public string $lastName = '';

    public string $phone = '';

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
        $this->drawer = true;

        unset($this->guardian, $this->counterpart);
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
        $guardian = Guardian::findOrFail($this->guardianId);

        Gate::authorize('update', $guardian);

        $validated = $this->validate([
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', new ValidPhone],
            'email' => ['nullable', 'email', 'max:255'],
            'iban' => ['nullable', new ValidIban],
        ]);

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
};

?>

<div>
    <x-drawer wire:model="drawer" :title="__('Edit the responsible adult')" right separator with-close-button
        class="w-full max-w-xl">
        @if ($this->guardian)
            <x-form wire:submit="save">
                @if ($this->guardian->users->count() > 1)
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

                <x-slot:actions>
                    <x-button :label="__('Cancel')" @click="$wire.drawer = false" />
                    <x-button :label="__('Save')" type="submit" class="btn-primary" spinner="save" />
                </x-slot:actions>
            </x-form>
        @endif
    </x-drawer>
</div>
