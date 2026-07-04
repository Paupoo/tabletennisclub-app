<div>
    @php
        $wizardSteps = [
            1 => ['label' => __('Identity'), 'icon' => 'o-user-circle'],
            2 => ['label' => __('Address'), 'icon' => 'o-map-pin'],
            3 => ['label' => __('Photo and documents'), 'icon' => 'o-sparkles'],
        ];
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
            @if ($num < 3)
                <x-icon name="o-chevron-right" class="w-4 h-4 text-base-content/15 shrink-0 mx-0.5" />
            @endif
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

    {{-- STEP 2 — Address --}}
    @if ($step === 2)
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

    {{-- STEP 3 — Optional extras --}}
    @if ($step === 3)
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
