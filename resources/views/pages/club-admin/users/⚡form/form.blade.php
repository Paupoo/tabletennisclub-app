<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <!-- HEADER -->
    @php
        $quote = collect($quotes)->random();
    @endphp
    <x-header progress-indicator separator subtitle="{!! $quote['text'] !!} — {!! $quote['author'] !!}"
        title="{{ $user?->exists ? __('Update ') . $user->first_name . ' ' . $user->last_name : __('Create new user') }}">

        <x-slot:actions>
            {{-- <x-button label="Users" link="{{ route('admin.users.index') }}" responsive icon="o-arrow-uturn-left" /> --}}
        </x-slot:actions>
    </x-header>

    <!-- TABLE  -->
    <x-form wire:submit="save">
        <div class="grid grid-cols-6 gap-4 md:gap-6">

            <!-- Section Personal -->
            <div class="col-span-6 md:col-span-2">
                <x-header :subtitle="__('Personal information')" :title="__('Personal')" />
            </div>

            <div class="col-span-6 grid gap-2 md:col-span-4">
                <div class="grid gap-6 lg:grid-cols-2">
                    <x-input :label="__('First Name')" wire:model="first_name" />
                    <x-input :label="__('Last Name')" wire:model="last_name" />
                    <x-input :label="__('Email')" wire:model="email" />
                    <x-group :options="$genders" class="btn-soft" inline :label="__('Gender')"
                        wire:model="gender" />
                    <x-input :label="__('Street')" wire:model="street" />
                    <x-input autocomplete="city_code" inputmode="numeric" :label="__('Postal Code')" max="9999"
                        min="1000" pattern="[0-9]*" type="number" wire:model.live.debounce.500ms="city_code" />
                    <x-input :label="__('City')" wire:model="city_name" />
                    <x-input :label="__('Phone Number')" wire:model="phone_number" />
                    <x-input :label="__('Birthdate')" type="date" wire:model.live="birthdate" />
                    <x-input :label="__('IBAN')" wire:model="iban"
                        placeholder="BE00 0000 0000 0000"
                        :hint="__('Used for refunds. Leave empty if unknown.')" />
                    <div wire:key="photo-container-{{ $imageKey }}">
                        <x-avatar-cropper :label="__('Photo')"
                            :preview="($photo && $photo->isPreviewable() ? $photo->temporaryUrl() : null) ?? ($currentPhoto ? asset($currentPhoto) : null)">
                            <x-slot:delete>
                                @if ($currentPhoto)
                                    <x-button class="btn-soft btn-ghost btn-sm" icon="o-trash"
                                        :label="__('Delete photo')" wire:click="$set('deleteModal', true)" />
                                @endif
                            </x-slot:delete>
                        </x-avatar-cropper>
                    </div>
                </div>

            </div>

            <div class="col-span-6">
                <x-menu-separator />
            </div>

            <!-- Section Guardian / Legal representatives -->
            <div class="col-span-6 md:col-span-2">
                <x-header :subtitle="__('Required for minors (under 18)')" :title="__('Legal guardians')" />
            </div>
            <div class="col-span-6 md:col-span-4 space-y-4">

                @if ($this->isMinor && count($guardianIds) === 0)
                    <x-alert icon="o-exclamation-triangle" class="alert-warning">
                        <span class="text-sm">
                            {{ __('This member is a minor without a legal guardian. Add one below — it is required before they can be set as an active (affiliated) member.') }}
                        </span>
                    </x-alert>
                @endif

                {{-- Linked guardians --}}
                @if ($this->linkedGuardians->isNotEmpty())
                    <div class="space-y-2">
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
                                <div class="px-3 pt-1 text-[10px] font-black uppercase tracking-wider text-base-content/40">
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
                                <div class="px-3 pt-1 text-[10px] font-black uppercase tracking-wider text-base-content/40">
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
                    <x-button class="btn-soft btn-sm" icon="o-plus" :label="__('Create a new guardian')"
                        wire:click="$set('showGuardianForm', true)" />
                @else
                    <div class="space-y-3 rounded-lg border border-base-200 p-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-input :label="__('First name')" wire:model.live.blur="guardianFirstName" required />
                            <x-input :label="__('Last name')" wire:model.live.blur="guardianLastName" required />
                            <x-input :label="__('Phone')" wire:model.live.blur="guardianPhone"
                                placeholder="0470 00 00 00" required />
                            <x-input :label="__('Email')" type="email" wire:model.live.blur="guardianEmail" />
                            <x-input :label="__('IBAN')" wire:model.live.blur="guardianIban"
                                placeholder="BE00 0000 0000 0000"
                                :hint="__('Optional — used for refunds.')" class="sm:col-span-2" />
                        </div>

                        @if ($this->duplicateGuardian)
                            <x-admin.users.guardian-duplicate-notice :guardian="$this->duplicateGuardian"
                                :already-linked="$this->duplicateGuardianAlreadyLinked" />
                        @endif

                        <div class="flex gap-2">
                            <x-button class="btn-primary btn-sm" icon="o-check" :label="__('Add guardian')"
                                wire:click="createGuardian" spinner="createGuardian" />
                            <x-button class="btn-ghost btn-sm" :label="__('Cancel')"
                                wire:click="$set('showGuardianForm', false)" />
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-span-6">
                <x-menu-separator />
            </div>

            <!-- Section Family -->
            <div class="col-span-6 md:col-span-2">
                <x-header :subtitle="__('Members who can see and register each other')" :title="__('Family')" />
            </div>
            <div class="col-span-6 md:col-span-4 space-y-4">

                {{-- Linked family members --}}
                @if ($this->familyMembers->isNotEmpty())
                    <div class="space-y-2">
                        @foreach ($this->familyMembers as $member)
                            <div wire:key="family-member-{{ $member->id }}"
                                class="flex items-center gap-3 p-3 rounded-lg border border-base-200 bg-base-100">
                                <x-icon name="o-user" class="w-5 h-5 text-primary shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold truncate">
                                        {{ $member->first_name }} {{ $member->last_name }}
                                    </div>
                                </div>
                                <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-x-mark"
                                    :tooltip="__('Unlink')" wire:click="detachFamilyMember({{ $member->id }})" />
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Search club members --}}
                <div>
                    <x-input :label="__('Find a club member')" icon="o-magnifying-glass"
                        :placeholder="__('Search by name or email…')" wire:model.live.debounce.300ms="familySearch" />

                    @php
                        $familyResults = $this->familySearchResults;
                    @endphp

                    @if ($familyResults->isNotEmpty())
                        <div class="mt-2 space-y-1 rounded-lg border border-base-200 p-1">
                            <div class="px-3 pt-1 text-[10px] font-black uppercase tracking-wider text-base-content/40">
                                {{ __('Club members') }}
                            </div>
                            @foreach ($familyResults as $result)
                                <button type="button" wire:key="family-result-{{ $result->id }}"
                                    wire:click="attachFamilyMember({{ $result->id }})"
                                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm hover:bg-base-200">
                                    <x-icon name="o-user-plus" class="w-4 h-4 text-primary shrink-0" />
                                    <span class="flex-1 truncate">
                                        {{ $result->first_name }} {{ $result->last_name }}
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    @elseif (strlen(trim($familySearch)) >= 2)
                        <p class="mt-2 text-xs text-base-content/50">
                            {{ __('No member found.') }}
                        </p>
                    @endif
                </div>
            </div>

            <div class="col-span-6">
                <x-menu-separator />
            </div>

            <!-- Section Security -->
            <div class="col-span-6 md:col-span-2">
                <x-header :subtitle="__('Secure your account')" :title="__('Security')" />
            </div>
            <div class="col-span-6 md:col-span-4 space-y-4">
                <div>
                    <x-password
                        :hint="__('Minimum 8 charachters, with at least 1 letter, 1 number and 1 special character')"
                        label="Password" wire:model.live.debounce="password" />
                    <x-password label="Password Confirmation" wire:model.live.debounce="password_confirmation" />
                </div>

                @if ($user)
                    <div class="flex flex-wrap gap-2 border-t border-base-200 pt-4">
                        <x-button class="btn-soft btn-sm" icon="o-key" :label="__('Send password reset link')"
                            wire:click="sendPasswordResetLink" spinner="sendPasswordResetLink" />
                        @can('sendEmail', \App\Domains\ClubAdmin\Users\Models\User::class)
                            <x-button class="btn-soft btn-sm" icon="o-envelope" :label="__('Resend invitation')"
                                wire:click="resendInvitation" spinner="resendInvitation" />
                        @endcan
                    </div>
                @endif
            </div>
            <div class="col-span-6">
                <x-menu-separator />
            </div>

            <!-- Section Registration -->
            <div class="col-span-6 md:col-span-2">
                <x-header :subtitle="__('Affiliation info')" :title="__(key: 'Registration')" />
            </div>

            <div class="col-span-6 md:col-span-4">
                <x-input :label="__('Licence')" numeric wire:model.live.debounce="licence" :hint="__('6 digits')" />
                <x-select :options="$rankings" icon="o-scale" :label="__('Ranking')" wire:model.live="ranking" />
                <x-alert icon="o-information-circle" class="alert-info mt-2">
                    <span class="text-sm">
                        {{ __('Whether a member is competitive or recreative comes from their affiliation for the season, and is decided when it is accepted on the Affiliations page.') }}
                    </span>
                </x-alert>
            </div>
            <div class="col-span-6">
                <x-menu-separator />
            </div>

            {{--
                Section Statut — the statutory title. It is displayed (profile badge,
                committee list) and never decides an access: what someone may do is
                carried by the délégations below.
            --}}
            <div class="col-span-6 md:col-span-2">
                <x-header :subtitle="__('Seat on the committee — a title, not an access right')"
                    :title="__('Status')" />
            </div>
            <div class="col-span-6 md:col-span-4">
                <x-checkbox
                    :hint="__('Grants baseline back-office access, and allows a statutory title to be held.')"
                    :label="__('Is a committee member')" wire:model.live="is_committee_member" />
                @if ($is_committee_member)
                    <div class="w-full sm:w-72 mb-4 sm:ml-10">
                        <x-select :label="__('Committee Role')" icon="o-briefcase"
                            :placeholder="__('Select a role')" :options="$this->CommitteeRoleOptions"
                            wire:model.live="committee_role" />
                        <p class="mt-1 text-xs text-base-content/60">
                            {{ __('Picking a title pre-checks its usual duties below. You can change them.') }}
                        </p>
                    </div>
                @endif
                <x-checkbox :hint="__('With great power comes great responsibility...')"
                    :label="__('Is an administrator')" wire:model="is_admin" />
            </div>

            <div class="col-span-6">
                <x-menu-separator />
            </div>

            {{--
                Section Délégations — what the member may actually do. Assignable to
                anyone, committee member or not, and cumulative.
            --}}
            <div class="col-span-6 md:col-span-2">
                <x-header :subtitle="__('Operational duties. Anyone can hold them, and they stack.')"
                    :title="__('Delegations')" />
            </div>
            <div class="col-span-6 md:col-span-4">
                {{-- Cards stack on mobile and pair up from md: 16 duties never fit a
                     single readable column on a phone, nor a wide row on a laptop. --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    @foreach ($this->delegationOptions as $delegation)
                        @php $isHeld = in_array($delegation['value'], $delegations, true); @endphp
                        <label
                            for="delegation-{{ $delegation['value'] }}"
                            @class([
                                'flex gap-3 items-start p-3 rounded-xl border cursor-pointer transition-colors duration-150',
                                'border-primary/60 bg-primary/5' => $isHeld,
                                'border-base-300 hover:border-primary/40' => ! $isHeld,
                            ])
                        >
                            <input
                                type="checkbox"
                                id="delegation-{{ $delegation['value'] }}"
                                value="{{ $delegation['value'] }}"
                                wire:model.live="delegations"
                                class="checkbox checkbox-sm checkbox-primary mt-0.5 shrink-0"
                            />
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-base-content">
                                    {{ $delegation['label'] }}
                                </span>
                                <span class="block text-xs text-base-content/60 leading-snug">
                                    {{ $delegation['description'] }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>

                @if ($delegations === [])
                    <p class="mt-3 text-xs text-base-content/60">
                        {{ __('No delegation: this member holds no management right.') }}
                    </p>
                @endif
            </div>

            <div class="col-span-6">
                <x-menu-separator />
            </div>

            {{--
                Section Équipements confiés — the third family. An object handed over
                and given back; it records a fact and grants nothing by itself.
            --}}
            <div class="col-span-6 md:col-span-2">
                <x-header :subtitle="__('Physical items entrusted to this member. Grants no access by itself.')"
                    :title="__('Entrusted equipment')" />
            </div>
            <div class="col-span-6 md:col-span-4">
                <x-checkbox :hint="__('Member holds a key to open and close the venue')"
                    :label="__('Has a key')" wire:model="has_key" />
                @if ($user && $user->heldCashRegisters->isNotEmpty())
                    <div class="mt-4">
                        <p class="text-sm font-semibold mb-2">{{ __('Held cash registers') }}</p>
                        <ul class="space-y-1">
                            @foreach ($user->heldCashRegisters as $register)
                                <li class="flex items-center gap-2 text-sm">
                                    <x-icon name="o-banknotes" class="w-4 h-4 text-base-content/40" />
                                    {{ $register->name }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            @if ($user && ($user->medical_certificate_path || $user->parental_consent_path))
                <div class="col-span-6">
                    <x-menu-separator />
                </div>

                <!-- Section Documents -->
                <div class="col-span-6 md:col-span-2">
                    <x-header :subtitle="__('Uploaded by the member')" :title="__('Documents')" />
                </div>
                <div class="col-span-6 md:col-span-4 space-y-3">
                    @if ($user->medical_certificate_path)
                    <div class="flex items-center gap-3 p-3 rounded-lg border border-base-200 bg-base-100">
                        <x-icon name="o-document-check" class="w-5 h-5 text-success shrink-0" />
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold">{{ __('Medical Certificate') }}</div>
                        </div>
                        <a href="{{ route('admin.user.documents.download', [$user, 'medical']) }}" target="_blank" class="btn btn-ghost btn-sm gap-1">
                            <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                            {{ __('Download') }}
                        </a>
                    </div>
                    @endif
                    @if ($user->parental_consent_path)
                    <div class="flex items-center gap-3 p-3 rounded-lg border border-base-200 bg-base-100">
                        <x-icon name="o-document-check" class="w-5 h-5 text-success shrink-0" />
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold">{{ __('Parental Consent') }}</div>
                        </div>
                        <a href="{{ route('admin.user.documents.download', [$user, 'parental_consent']) }}" target="_blank" class="btn btn-ghost btn-sm gap-1">
                            <x-icon name="o-arrow-down-tray" class="w-4 h-4" />
                            {{ __('Download') }}
                        </a>
                    </div>
                    @endif
                </div>
            @endif

            @if ($user)
                <div class="col-span-6">
                    <x-menu-separator />
                </div>

                {{-- Zone de danger (RGPD) — admin uniquement, pas sur son propre compte --}}
                @if ($user && Auth::user()->can('anonymize', $user))
                    <div class="col-span-6 mt-4 border-t border-error/20 pt-6">
                        <x-header
                            :title="__('Danger zone')"
                            :subtitle="__('Irreversible actions. Act with caution.')"
                            class="!mb-0 text-error" />
                    </div>
                    @if ($user->gdpr_erasure_requested_at)
                        <div class="col-span-6">
                            <x-alert icon="o-shield-exclamation" class="alert-warning">
                                <span class="text-sm">
                                    {{ __('This member requested erasure of their personal data on :date. Review and anonymize below.', ['date' => $user->gdpr_erasure_requested_at->format('d/m/Y')]) }}
                                </span>
                            </x-alert>
                        </div>
                        @if ($user->hasPendingPayments())
                            <div class="col-span-6">
                                <x-alert icon="o-banknotes" class="alert-error">
                                    <span class="text-sm">
                                        {{ __('This member still has a subscription awaiting payment — reconcile the finances before anonymizing.') }}
                                    </span>
                                </x-alert>
                            </div>
                        @endif
                    @endif
                    <div class="col-span-6">
                        <x-card class="border border-error/20 bg-error/5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-medium text-error">{{ __('GDPR Anonymization') }}</p>
                                    <p class="mt-0.5 text-sm text-base-content/60">
                                        {{ __('Permanently erases all personal data (name, email, phone, address, IBAN, documents). Tournament and payment history is preserved. Cannot be undone.') }}
                                    </p>
                                </div>
                                <x-button
                                    class="btn-error btn-soft btn-sm shrink-0"
                                    icon="o-no-symbol"
                                    :label="__('Anonymize (GDPR)')"
                                    wire:click="$set('anonymizeModal', true)" />
                            </div>
                        </x-card>
                    </div>
                @endif
            @endif

        </div>
        <x-slot:actions>
            <x-button class="btn-primary" :label="$user ? __('Update') : __('Create')" spinner="save"
                type="submit" />
        </x-slot:actions>
    </x-form>

    @if ($errors->any())
        <div class="p-3 bg-red-100 text-red-700 rounded">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── Modal anonymisation RGPD ─────────────────────────────────── --}}
    <x-modal wire:model="anonymizeModal" :title="__('GDPR Anonymization — Irreversible')">
        <div class="space-y-4">
            <x-alert icon="o-exclamation-triangle" class="alert-error">
                <p class="text-sm font-semibold">{{ __('This action permanently erases all personal data and cannot be undone.') }}</p>
                <p class="mt-1 text-sm">{{ __('Name, email, phone, address, IBAN and documents will be replaced with anonymous placeholders. Tournament and payment history is preserved.') }}</p>
            </x-alert>
            <x-input
                :label="__('Type ANONYMIZE to confirm')"
                wire:model.live="anonymizeConfirmText"
                :placeholder="__('ANONYMIZE')"
                class="font-mono" />
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('anonymizeModal', false)" />
            <x-button
                class="btn-error"
                icon="o-no-symbol"
                :label="__('Anonymize')"
                wire:click="confirmAnonymize"
                spinner="confirmAnonymize"
                :disabled="strtoupper($anonymizeConfirmText) !== 'ANONYMIZE'" />
        </x-slot:actions>
    </x-modal>
</div>
