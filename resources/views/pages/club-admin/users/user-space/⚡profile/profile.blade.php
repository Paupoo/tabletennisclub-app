<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header :title="__('My Profile')"
        :subtitle="__('Member since :date', ['date' => $memberSince->translatedFormat('F Y')])"
        separator progress-indicator>
        <x-slot:actions>
            <x-button :label="__('Edit Profile')" icon="o-pencil" class="btn-outline btn-sm"
                @click="$wire.drawer = true" responsive />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-8 items-start lg:grid-cols-3">

        {{-- ════════════════════════════════
             SIDEBAR GAUCHE
        ════════════════════════════════ --}}
        <div class="space-y-4">

            {{-- Avatar + nom + badges --}}
           
            <x-card>
                <div class="flex flex-col items-center text-center gap-3">
                <x-avatar :image="$user->photo ?? '/images/empty-user.jpg'" class="!w-24 !rounded-full" />
                <div>
                    <div class="font-bold text-xl">{{ $user->first_name }} {{ $user->last_name }}</div>
                    <div class="text-sm opacity-50 mt-0.5">{{ $user->is_active ? __('Active member') : __('Inactive member') }}</div>
                </div>
                <div class="flex flex-wrap justify-center gap-1">
                    @if ($user->is_admin)
                        <x-badge value="{{ __('Admin') }}" icon="o-power" class="badge-primary badge-sm" />
                    @endif
                    @if ($user->is_committee_member && $user->committee_role)
                        <x-badge :value="$user->committee_role->label()" icon="o-star" class="badge-secondary badge-sm text-black" />
                    @endif
                    @if (!$user->is_active)
                        <x-badge value="{{ __('Inactive') }}" class="badge-neutral badge-sm" />
                    @endif
                </div>
                <x-button :label="__('Edit')" icon="o-pencil" class="btn-outline btn-sm w-fit"
                    @click="$wire.drawer = true" />
            </div>
            </x-card>

            {{-- Infos contact --}}
            <x-card>
            <div class="divide-y divide-base-200">
                <div class="flex items-center gap-3 px-4 py-3">
                    @if (in_array($currentSubscription?->status, ['confirmed', 'paid'], true))
                        <x-icon name="o-shield-check" class="w-4 h-4 text-success shrink-0" />
                        <div class="min-w-0">
                            <div class="text-xs opacity-60 uppercase tracking-wide font-semibold">{{ __('Status') }}</div>
                            <div class="text-sm font-semibold text-success truncate">
                                {{ __('Affiliated · season :season', ['season' => $currentSeason->name]) }}
                            </div>
                        </div>
                    @elseif ($currentSubscription?->status === 'pending')
                        <x-icon name="o-clock" class="w-4 h-4 text-warning-content shrink-0" />
                        <div class="min-w-0">
                            <div class="text-xs opacity-60 uppercase tracking-wide font-semibold">{{ __('Status') }}</div>
                            <div class="text-sm font-semibold text-warning-content truncate">
                                {{ __('Affiliation awaiting validation') }}
                            </div>
                        </div>
                    @else
                        <x-icon name="o-shield-exclamation" class="w-4 h-4 opacity-40 shrink-0" />
                        <div class="min-w-0">
                            <div class="text-xs opacity-60 uppercase tracking-wide font-semibold">{{ __('Status') }}</div>
                            <div class="text-sm font-semibold truncate">
                                <a href="{{ route('admin.user.registration-management', $user) }}" class="link link-hover">
                                    {{ __('Not affiliated this season') }}
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
                @if ($user->ranking)
                    <div class="flex items-center gap-3 px-4 py-3">
                        <x-icon name="o-chevron-double-up" class="w-4 h-4 opacity-40 shrink-0" />
                        <div class="min-w-0">
                            <div class="text-xs opacity-60 uppercase tracking-wide font-semibold">{{ __('Ranking') }}</div>
                            <div class="text-sm font-semibold truncate">{{ $user->ranking }}</div>
                        </div>
                    </div>
                @endif
                <div class="flex items-center gap-3 px-4 py-3">
                    <x-icon name="o-identification" class="w-4 h-4 opacity-40 shrink-0" />
                    <div class="min-w-0">
                        <div class="text-xs opacity-60 uppercase tracking-wide font-semibold">{{ __('Licence') }}</div>
                        <div class="text-sm font-semibold truncate">
                            {{ $user->is_competitor ? __('Competitor') : __('Recreative') }}
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-4 py-3">
                    <x-icon name="o-map-pin" class="w-4 h-4 opacity-40 shrink-0" />
                    <div class="min-w-0">
                        <div class="text-xs opacity-60 uppercase tracking-wide font-semibold">{{ __('Address') }}</div>
                        <div class="text-sm font-semibold truncate">{{ $user->street }}</div>
                        <div class="text-sm font-semibold truncate">{{ $user->city_name }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-4 py-3">
                    <x-icon name="o-envelope" class="w-4 h-4 opacity-40 shrink-0" />
                    <div class="min-w-0">
                        <div class="text-xs opacity-60 uppercase tracking-wide font-semibold">{{ __('Email') }}</div>
                        <div class="text-sm font-semibold truncate">{{ $user->email }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-4 py-3">
                    <x-icon name="o-phone" class="w-4 h-4 opacity-40 shrink-0" />
                    <div class="min-w-0">
                        <div class="text-xs opacity-60 uppercase tracking-wide font-semibold">{{ __('Phone') }}</div>
                        <div class="text-sm font-semibold truncate">{{ $user->phone_number }}</div>
                    </div>
                </div>
                @if ($user->iban)
                <div class="flex items-center gap-3 px-4 py-3">
                    <x-icon name="o-building-library" class="w-4 h-4 opacity-40 shrink-0" />
                    <div class="min-w-0">
                        <div class="text-xs opacity-60 uppercase tracking-wide font-semibold">{{ __('IBAN') }}</div>
                        <div class="text-sm font-semibold font-mono truncate">{{ $user->iban_formatted }}</div>
                    </div>
                </div>
                @endif
                @if ($user->guardian_phone_number)
                    <div class="flex items-center gap-3 px-4 py-3">
                        <x-icon name="o-phone" class="w-4 h-4 opacity-40 shrink-0" />
                        <div class="min-w-0">
                            <div class="text-xs opacity-60 uppercase tracking-wide font-semibold">{{ __('Parent / Tutor') }}</div>
                            <div class="text-sm font-semibold truncate">{{ $user->guardian_phone_number }}</div>
                        </div>
                    </div>
                @endif
            </div>
            </x-card>

        </div>

        {{-- ════════════════════════════════
             CONTENU PRINCIPAL
        ════════════════════════════════ --}}
        <div class="min-w-0 space-y-8 lg:col-span-2">

            {{-- Équipes --}}
            <x-card :title="__('My Teams')" icon="o-user-group" separator>
                <x-slot:menu>
                    <x-button :label="__('Team page')" icon-right="o-arrow-right" class="btn-ghost btn-sm"
                        link="{{ route('admin.user.teams', $user) }}" />
                </x-slot:menu>

                @if ($user->teams->isEmpty())
                    <x-empty-state icon="o-user-group" :heading="__('No team yet')"
                        :message="__('You are not part of any team this season.')" />
                @else
                    <div class="flex flex-wrap gap-2">
                        @foreach ($user->teams as $team)
                            @php
                                $teamMeta = collect([
                                    \App\Domains\Shared\Enums\LeagueCategory::fromName($team->league?->category)?->label(),
                                    $team->league?->division,
                                ])->filter()->implode(' · ');
                            @endphp
                            <a href="{{ route('admin.user.teams', $user) }}"
                                class="inline-flex items-center gap-2 rounded-full border border-base-300 bg-base-100 px-4 py-1.5 text-sm transition-colors hover:border-primary hover:text-primary">
                                <span class="font-semibold">{{ $team->fullName() }}</span>
                                @if ($teamMeta)
                                    <span class="text-xs text-base-content/50">{{ $teamMeta }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-card>

        </div>
    </div>

    {{-- ════════════════════════════════
         DRAWER — Edit profile
    ════════════════════════════════ --}}
    <x-drawer wire:model="drawer" :title="__('Update info')" right separator with-close-button
        class="w-full lg:w-1/2 2xl:w-1/2">
        <x-form wire:submit="save">
            <div class="grid grid-cols-6 gap-4 md:gap-6">

                {{-- Identity --}}
                <div class="col-span-6 md:col-span-2">
                    <x-header :title="__('Identity')" :subtitle="__('Who you are')" />
                </div>
                <div class="col-span-6 md:col-span-4">
                    <div class="grid lg:grid-cols-2 gap-6">
                        <x-input :label="__('First Name')" wire:model="first_name" />
                        <x-input :label="__('Last Name')" wire:model="last_name" />
                        <x-group :options="$genders" class="btn-soft" inline :label="__('Gender')"
                            wire:model="gender" />
                        <x-input :label="__('Birthdate')" type="date" wire:model.live="birthdate" />
                    </div>
                </div>

                <div class="col-span-6">
                    <x-menu-separator />
                </div>

                {{-- Contact --}}
                <div class="col-span-6 md:col-span-2">
                    <x-header :title="__('Contact')" :subtitle="__('How to reach you')" />
                </div>
                <div class="col-span-6 md:col-span-4">
                    <div class="grid lg:grid-cols-2 gap-6">
                        <x-input :label="__('Email')" wire:model="email" />
                        <x-input :label="__('Phone Number')" wire:model="phone_number" />
                        <x-input :label="__('Street')" wire:model="street" />
                        <x-input :label="__('Postal Code')" wire:model.live.debounce.500ms="city_code"
                            type="number" inputmode="numeric" pattern="[0-9]*"
                            autocomplete="city-code" min="1000" max="9999" />
                        <x-input :label="__('City')" wire:model="city_name" />
                        <x-input :label="__('IBAN')" wire:model="iban"
                            placeholder="BE00 0000 0000 0000"
                            :hint="__('payment.iban_format_hint')" />
                        <div>
                            <div wire:key="photo-container-{{ $imageKey }}">
                                <x-file :label="__('Photo')" wire:model="photo"
                                    accept="image/png, image/jpeg, image/webp" crop-after-change>
                                    <img src="{{ $photo ? $photo->temporaryUrl() : ($currentPhoto ? asset($currentPhoto) : asset('images/empty-user.jpg')) }}"
                                        alt="{{ __('Avatar') }}" class="h-36 rounded-lg object-cover">
                                </x-file>
                            </div>
                            @if ($currentPhoto)
                                <x-button :label="__('Delete photo')"
                                    class="m-2 text-xs btn-soft btn-ghost w-36"
                                    wire:click="$set('deleteModal', true)" />
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-span-6">
                    <x-menu-separator />
                </div>

                {{-- Documents --}}
                <div class="col-span-6 md:col-span-2">
                    <x-header :title="__('Documents')"
                        :subtitle="$this->isMinor ? __('Medical certificate & parental consent') : __('Medical certificate')" />
                </div>
                <div class="col-span-6 md:col-span-4 space-y-4">
                    <div>
                        <x-file :label="__('Medical certificate')" wire:model="medicalCertificate"
                            accept="image/png, image/jpeg, application/pdf"
                            :hint="__('JPG, PNG or PDF — max 4 MB')" />
                        @if ($user->medical_certificate_path)
                            <a href="{{ route('admin.user.documents.download', [$user, 'medical']) }}" target="_blank"
                                class="btn btn-ghost btn-xs gap-1 mt-1">
                                <x-icon name="o-arrow-down-tray" class="w-3 h-3" />
                                {{ __('View current') }}
                            </a>
                        @endif
                    </div>
                    {{-- Parental consent only applies to minors --}}
                    @if ($this->isMinor)
                        <div>
                            <x-file :label="__('Parental consent')" wire:model="parentalConsent"
                                accept="image/png, image/jpeg, application/pdf"
                                :hint="__('Required for minors — JPG, PNG or PDF, max 4 MB')" />
                            @if ($user->parental_consent_path)
                                <a href="{{ route('admin.user.documents.download', [$user, 'parental_consent']) }}" target="_blank"
                                    class="btn btn-ghost btn-xs gap-1 mt-1">
                                    <x-icon name="o-arrow-down-tray" class="w-3 h-3" />
                                    {{ __('View current') }}
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
            <x-slot:actions>
                <x-button :label="__('Reset')" />
                <x-button label="{{ $user ? __('Update') : __('Create') }}" class="btn-primary"
                    type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-drawer>

    {{-- ════════════════════════════════
         MODAL — Delete photo
    ════════════════════════════════ --}}
    <x-confirm-modal model="deleteModal" :title="__('Confirmation of deletion')" :subtitle="__('Warning!')"
        :confirmLabel="__('Delete')" confirmAction="deletePhoto">
        {{ __('Are you sure you want to delete this picture? This action is irreversible.') }}
    </x-confirm-modal>

    {{-- ════════════════════════════════
         ZONE DE DANGER (RGPD)
    ════════════════════════════════ --}}
    @if (Auth::user()->is($user))
        <div class="mt-8 border-t border-error/20 pt-6">
            <div>
                <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-error/60">
                    {{ __('Danger zone') }}
                </p>
                <x-card class="border border-error/20 bg-error/5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-medium text-error">{{ __('Request account deletion') }}</p>
                            <p class="mt-0.5 text-sm text-base-content/60">
                                {{ __('Send a deletion request to the administrator. Your data will be anonymized. Note: requests with pending payments may be delayed.') }}
                            </p>
                        </div>
                        @if ($user->gdpr_erasure_requested_at)
                            <x-badge
                                :value="__('Request sent on :date', ['date' => $user->gdpr_erasure_requested_at->format('d/m/Y')])"
                                class="badge-warning badge-soft shrink-0" />
                        @else
                            <x-button
                                class="btn-error btn-soft btn-sm shrink-0"
                                icon="o-trash"
                                :label="__('Request deletion')"
                                wire:click="requestErasure"
                                spinner="requestErasure" />
                        @endif
                    </div>
                </x-card>
            </div>
        </div>
    @endif

</div>