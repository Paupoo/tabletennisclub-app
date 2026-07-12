<div>
    @php
        $wizardSteps = [1 => ['label' => __('Identity'), 'icon' => 'o-user-circle']];

        if ($this->isMinor) {
            $wizardSteps[2] = ['label' => __('Legal guardian'), 'icon' => 'o-shield-check'];
        }

        $wizardSteps[3] = ['label' => __('Address'), 'icon' => 'o-map-pin'];
        $wizardSteps[4] = ['label' => __('Photo and documents'), 'icon' => 'o-sparkles'];
    @endphp

    {{-- ── Intro ────────────────────────────────────────────────────────────── --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-base-content">{{ __('Complete your profile') }}</h1>
        <p class="mt-1 text-sm text-base-content/60">
            {{ __('A few details are needed before you can use the member area.') }}
        </p>
    </div>

    {{-- ── Metro-line navigation ────────────────────────────────────────────── --}}
    <div class="flex items-center gap-0 mb-8 overflow-x-auto pb-2">
        @foreach ($wizardSteps as $num => $info)
            @php $reachable = $num <= $maxReachable; @endphp
            <button
                wire:click="{{ $reachable ? "goToStep({$num})" : 'null' }}"
                @class([
                    'flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap',
                    'bg-primary text-primary-content shadow' => $num === $step,
                    'text-base-content/70 hover:bg-base-200 hover:text-base-content cursor-pointer' => $reachable && $num !== $step,
                    'text-base-content/30 cursor-not-allowed' => ! $reachable,
                ])
            >
                <x-icon name="{{ $info['icon'] }}" class="w-4 h-4 shrink-0" />
                <span class="hidden sm:inline">{{ $info['label'] }}</span>
                @if ($reachable && $num < $step)
                    <x-icon name="o-check-circle" class="w-3.5 h-3.5 text-success shrink-0" />
                @endif
            </button>
            @unless ($loop->last)
                <x-icon name="o-chevron-right" class="w-4 h-4 text-base-content/15 shrink-0 mx-0.5" />
            @endunless
        @endforeach
    </div>

    {{-- STEP 1 — Identity --}}
    @if ($step === 1)
        <div class="animate-in fade-in duration-500">
            <h2 class="text-lg font-semibold text-base-content mb-1">{{ __('Identity') }}</h2>
            <p class="text-sm text-base-content/60 mb-6">{{ __('Who you are') }}</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-group :options="$genders" class="btn-soft" inline :label="__('Gender')"
                    wire:model="gender" />
                <x-input :label="__('Birthdate')" type="date" wire:model.live="birthdate" required />
                <x-input :label="__('Phone Number')" wire:model="phone_number"
                    placeholder="0470 00 00 00" required />
            </div>

            <div class="flex justify-end mt-6">
                <x-button
                    :label="__('Next')"
                    icon-right="o-arrow-right"
                    class="btn-primary"
                    wire:click="completeIdentityStep"
                    spinner="completeIdentityStep"
                />
            </div>
        </div>
    @endif

    {{-- STEP 2 — Legal guardian (minors only) --}}
    @if ($step === 2)
        <div class="animate-in fade-in duration-500">
            <h2 class="text-lg font-semibold text-base-content mb-1">{{ __('Legal guardian') }}</h2>
            <p class="text-sm text-base-content/60 mb-6">{{ __('Who is responsible for you') }}</p>

            {{-- Linked guardians --}}
            @if ($this->linkedGuardians->isNotEmpty())
                <div class="space-y-2 mb-4">
                    @foreach ($this->linkedGuardians as $guardian)
                        <div wire:key="guardian-{{ $guardian->id }}"
                            class="flex items-center gap-3 p-3 rounded-lg border border-base-200 bg-base-100">
                            <x-icon name="o-user" class="w-5 h-5 text-primary shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold truncate">
                                    {{ $guardian->first_name }} {{ $guardian->last_name }}
                                </div>
                                <div class="text-xs text-base-content/60 truncate">
                                    {{ $guardian->phone }}{{ $guardian->email ? ' · ' . $guardian->email : '' }}
                                </div>
                            </div>
                            <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-x-mark"
                                :tooltip="__('Unlink')" wire:click="detachGuardian({{ $guardian->id }})" />
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Search existing guardians and club members --}}
            <div>
                <x-input :label="__('Find an existing guardian or member')" icon="o-magnifying-glass"
                    :placeholder="__('Search by name or email…')"
                    wire:model.live.debounce.300ms="guardianSearch" />

                @php
                    $guardianResults = $this->guardianSearchResults;
                    $memberResults = $this->memberSearchResults;
                    $hasResults = $guardianResults->isNotEmpty() || $memberResults->isNotEmpty();
                @endphp

                @if ($hasResults)
                    <div class="mt-2 space-y-1 rounded-lg border border-base-200 p-1">
                        @if ($guardianResults->isNotEmpty())
                            <div class="px-3 pt-1 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                                {{ __('Existing guardians') }}
                            </div>
                            @foreach ($guardianResults as $result)
                                <button type="button" wire:key="guardian-result-{{ $result->id }}"
                                    wire:click="attachGuardian({{ $result->id }})"
                                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm hover:bg-base-200">
                                    <x-icon name="o-plus-circle" class="w-4 h-4 text-success shrink-0" />
                                    <span class="flex-1 truncate">
                                        {{ $result->first_name }} {{ $result->last_name }}
                                        <span class="text-base-content/50">· {{ $result->phone }}</span>
                                    </span>
                                </button>
                            @endforeach
                        @endif

                        @if ($memberResults->isNotEmpty())
                            <div class="px-3 pt-1 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                                {{ __('Club members') }}
                            </div>
                            @foreach ($memberResults as $member)
                                <button type="button" wire:key="member-result-{{ $member->id }}"
                                    wire:click="attachMemberAsGuardian({{ $member->id }})"
                                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm hover:bg-base-200">
                                    <x-icon name="o-user-plus" class="w-4 h-4 text-primary shrink-0" />
                                    <span class="flex-1 truncate">
                                        {{ $member->first_name }} {{ $member->last_name }}
                                        <span class="text-base-content/50">· {{ __('member') }}</span>
                                    </span>
                                </button>
                            @endforeach
                        @endif
                    </div>
                @elseif (strlen(trim($guardianSearch)) >= 2)
                    <p class="mt-2 text-xs text-base-content/50">
                        {{ __('No guardian or member found. Create a new guardian below.') }}
                    </p>
                @endif
            </div>

            {{-- Inline creation --}}
            @if (! $showGuardianForm)
                <x-button class="btn-soft btn-sm mt-3" icon="o-plus" :label="__('Create a new guardian')"
                    wire:click="$set('showGuardianForm', true)" />
            @else
                <div class="space-y-3 rounded-lg border border-base-200 p-4 mt-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-input :label="__('First name')" wire:model="guardianFirstName" />
                        <x-input :label="__('Last name')" wire:model="guardianLastName" />
                        <x-input :label="__('Phone')" wire:model="guardianPhone" />
                        <x-input :label="__('Email')" type="email" wire:model="guardianEmail" />
                    </div>
                    <div class="flex gap-2">
                        <x-button class="btn-primary btn-sm" icon="o-check" :label="__('Add guardian')"
                            wire:click="createGuardian" spinner="createGuardian" />
                        <x-button class="btn-ghost btn-sm" :label="__('Cancel')"
                            wire:click="$set('showGuardianForm', false)" />
                    </div>
                </div>
            @endif

            <div class="flex justify-end mt-6">
                <x-button
                    :label="__('Next')"
                    icon-right="o-arrow-right"
                    class="btn-primary"
                    wire:click="completeGuardianStep"
                    spinner="completeGuardianStep"
                />
            </div>
        </div>
    @endif

    {{-- STEP 3 — Address --}}
    @if ($step === 3)
        <div class="animate-in fade-in duration-500">
            <h2 class="text-lg font-semibold text-base-content mb-1">{{ __('Address') }}</h2>
            <p class="text-sm text-base-content/60 mb-6">{{ __('How to reach you') }}</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-input :label="__('Street')" wire:model="street"
                    placeholder="Rue de la Station 1" class="sm:col-span-2" required />
                <x-input :label="__('Postal Code')" wire:model="city_code"
                    type="number" inputmode="numeric" pattern="[0-9]*"
                    min="1000" max="9999" placeholder="1340" required />
                <x-input :label="__('City')" wire:model="city_name"
                    placeholder="Ottignies" required />
            </div>

            <div class="flex justify-end mt-6">
                <x-button
                    :label="__('Next')"
                    icon-right="o-arrow-right"
                    class="btn-primary"
                    wire:click="completeAddressStep"
                    spinner="completeAddressStep"
                />
            </div>
        </div>
    @endif

    {{-- STEP 4 — Optional extras --}}
    @if ($step === 4)
        <div class="animate-in fade-in duration-500">
            <div class="flex items-start justify-between mb-1">
                <h2 class="text-lg font-semibold text-base-content">{{ __('Photo and documents') }}</h2>
                <span class="badge badge-soft badge-ghost text-xs">{{ __('Optional') }}</span>
            </div>
            <p class="text-sm text-base-content/60 mb-6">
                {{ __('Everything here is optional — you can add it later from your profile.') }}
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div wire:key="photo-container-{{ $imageKey }}">
                    <x-file :label="__('Photo')" wire:model="photo"
                        accept="image/png, image/jpeg, image/webp" crop-after-change>
                        <img src="{{ $photo ? $photo->temporaryUrl() : ($currentPhoto ? asset($currentPhoto) : asset('images/empty-user.jpg')) }}"
                            alt="{{ __('Avatar') }}" class="h-36 rounded-lg object-cover">
                    </x-file>
                </div>

                <div class="space-y-4">
                    <x-input :label="__('IBAN')" wire:model="iban"
                        placeholder="BE00 0000 0000 0000"
                        :hint="__('payment.iban_format_hint')" />

                    <x-file :label="__('Medical certificate')" wire:model="medicalCertificate"
                        accept="image/png, image/jpeg, application/pdf"
                        :hint="__('JPG, PNG or PDF — max 4 MB')" />

                    @if ($this->isMinor)
                        <x-file :label="__('Parental consent')" wire:model="parentalConsent"
                            accept="image/png, image/jpeg, application/pdf"
                            :hint="__('Required for minors — JPG, PNG or PDF, max 4 MB')" />
                    @endif
                </div>
            </div>

            <div class="flex items-center justify-between mt-6">
                <x-button
                    :label="__('Skip this step')"
                    class="btn-ghost btn-sm"
                    wire:click="skipOptionalStep"
                />
                <x-button
                    :label="__('Finish')"
                    icon-right="o-check"
                    class="btn-primary"
                    wire:click="finish"
                    spinner="finish"
                />
            </div>
        </div>
    @endif
</div>
