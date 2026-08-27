<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header progress-indicator separator :subtitle="__('Configure your club identity and management team')"
        :title="__('Club Info')" />

    <x-form wire:submit="save">
        {{-- Name & ID --}}
        <x-admin.shared.form-section :separator="true" :subtitle="__('Official name and federal affiliation')" :title="__('Club Identity')">
            <div class="grid gap-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-input icon="o-trophy" :label="__('Club Name')" placeholder="E.g. CTT Ottignies"
                        wire:model="name" required />
                    {{-- Read-only, not disabled: daisyUI strips a disabled field
                    of its border and its fill, which left a bare icon floating
                    under a label. The licence comes from the federation. --}}
                    <x-input icon="o-identification" :label="__('Club ID / Licence')"
                        :hint="__('Assigned by the federation — not editable here')"
                        wire:model="licence" readonly />
                </div>

            </div>
        </x-admin.shared.form-section>

        {{-- Location --}}
        <x-admin.shared.form-section :separator="true" :subtitle="__('Information to help members and visitors to find our club.')" :title="__('Location Details')">
            <x-input icon="o-map-pin" :label="__('Street')" wire:model="street" required/>
            <x-input icon="o-map-pin" :label="__('City Code')" wire:model="city_code" required/>
            <x-input icon="o-map-pin" :label="__('City Name')" wire:model="city_name" required/>
            <x-input icon="o-building-office" :label="__('Building Name (Optional)')" wire:model="building_name"/>
            <x-input icon="o-map-pin" :label="__('Latitude (Optional)')" wire:model="latitude" numeric/>
            <x-input icon="o-map-pin" :label="__('Longitude (Optional)')" wire:model="longitude" numeric/>

        </x-admin.shared.form-section>

        {{-- Contact --}}
        <x-admin.shared.form-section :separator="true" :subtitle="__('Information to facilitate people to contact us.')" :title="__('Contact Details')">
                    <x-input icon="o-phone" :label="__('Phone Contact (Optional)')" wire:model="phone_contact" />
                    <x-input icon="o-envelope-open" :label="__('Email Contact')" wire:model="email_contact" required/>
                    <x-input :label="__('Website URL')" prefix="https://" wire:model="website_url" />
        </x-admin.shared.form-section>

        {{-- Accounting --}}
        <x-admin.shared.form-section :separator="true" :subtitle="__('Banking and accounting data')" :title="__('Accounting')">
            <x-input icon="o-finger-print" :label="__('BIC Code')" wire:model="bic" required />
            <x-input icon="o-currency-euro" :label="__('Bank Account (IBAN)')" wire:model="bank_account" required/>
            <x-input icon="o-identification" :label="__('Enterprise Number (Optional)')" wire:model="enterprise_number" />
        </x-admin.shared.form-section>

        {{-- Committee --}}
        <x-admin.shared.form-section :separator="true" :subtitle="__('Manage board members and their roles')" :title="__('Committee')">
            <div>
                <div class="bg-base-200/50 border-base-300 mb-4 rounded-xl border p-4">
                    <div class="mb-4 flex items-center justify-between">
                        <span
                            class="text-xs font-bold uppercase tracking-widest opacity-60">{{ __('Board Members') }}</span>
                        @if($committeeMembers->count() > 0 && $this->canManageAccess)
                        <x-button @click="$dispatch('open-committee-modal')" class="btn-xs btn-outline"
                            icon="o-plus" :label="__('Add Member')" />
                        @endif
                    </div>

                    <div class="divide-base-300/50 divide-y">
                        @forelse($committeeMembers as $index => $member)
                            <div class="flex items-center justify-between py-3">
                                <div class="flex items-center gap-3">
                                    <x-avatar class="!w-8 !rounded-lg"
                                        placeholder="{{ mb_substr($member->first_name, 0, 1) }}{{ mb_substr($member->last_name, 0,1) }}" />
                                    <div>
                                        <div class="text-sm font-bold">{{ $member->first_name }} {{ $member->last_name }}</div>
                                            <div class="badge badge-outline text-xs opacity-70">
                                                {{ __($member->committee_role
                                                    ? $member->committee_role->label()
                                                    : 'Unknown role') }}
                                            </div>
                                    </div>
                                </div>
                                @can('manageAccess', $member)
                                    <x-button class="btn-circle btn-ghost btn-xs text-error" icon="o-trash"
                                        wire:click="removeMember({{ $member->id }})" />
                                @endcan
                            </div>
                        @empty
                            <x-admin.shared.empty
                                icon="o-users"
                                :title="__('No committee members defined yet.')"
                                :subtitle="$this->canManageAccess
                                    ? __('Add your first board member using the button above.')
                                    : __('Seats are handed out by whoever manages access rights.')"
                                :action="$this->canManageAccess ? __('Add Member') : null"
                                wireClick="$dispatch('open-committee-modal')"
                            />
                        @endforelse
                    </div>
                </div>

                <div class="text-info flex items-center gap-2 text-xs italic">
                    <x-icon class="h-4 w-4" name="o-information-circle" />
                    {{ __('Roles defined here will be visible on the "Contact" page.') }}
                </div>
            </div>
        </x-admin.shared.form-section>

        {{-- Interclub Schedule --}}
        <x-admin.shared.form-section :separator="true" :subtitle="__('Configure the interclub match entry displayed on the public schedule.')" :title="__('Interclub Schedule')">
            <x-toggle :label="__('Show interclub matches in the public schedule')" wire:model="interclubEnabled" />
            <x-select
                :label="__('Day of week')"
                icon="o-calendar"
                wire:model="interclubDay"
                :options="[
                    ['id' => 'Lundi',     'name' => __('Monday')],
                    ['id' => 'Mardi',     'name' => __('Tuesday')],
                    ['id' => 'Mercredi',  'name' => __('Wednesday')],
                    ['id' => 'Jeudi',     'name' => __('Thursday')],
                    ['id' => 'Vendredi',  'name' => __('Friday')],
                    ['id' => 'Samedi',    'name' => __('Saturday')],
                    ['id' => 'Dimanche',  'name' => __('Sunday')],
                ]"
            />
            <div class="lg:col-span-2 grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-input icon="o-clock" :label="__('Start time (HH:MM)')" wire:model="interclubTimeStart" placeholder="19:00" />
                <x-input icon="o-clock" :label="__('End time (HH:MM)')" wire:model="interclubTimeEnd" placeholder="23:30" />
            </div>
            <x-input icon="o-map-pin" :label="__('Location')" wire:model="interclubLocation" />
            <x-input icon="o-information-circle" :label="__('Description')" wire:model="interclubDescription" />
        </x-admin.shared.form-section>

        <x-admin.shared.form-section :separator="true" :subtitle="__('Members who hold club equipment')" :title="__('Equipment holders')">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                {{-- Key holders --}}
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-muted mb-3">
                        {{ __('Key holders') }}
                    </p>
                    @forelse($keyHolders as $holder)
                        <div class="flex items-center gap-2 py-1">
                            <x-icon name="o-key" class="w-4 h-4 text-base-content/40 shrink-0" />
                            <span class="text-sm">{{ $holder->first_name }} {{ $holder->last_name }}</span>
                        </div>
                    @empty
                        <p class="text-sm italic text-base-content/40">{{ __('None') }}</p>
                    @endforelse
                </div>

                {{-- Cash register holders --}}
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-muted mb-3">
                        {{ __('Cash register holders') }}
                    </p>
                    @forelse($cashRegisterHolders as $register)
                        <div class="flex items-center gap-2 py-1">
                            <x-icon name="o-banknotes" class="w-4 h-4 text-base-content/40 shrink-0" />
                            <span class="text-sm font-medium">{{ $register->name }}</span>
                            <span class="text-sm text-base-content/50">→</span>
                            @if($register->heldBy)
                                <span class="text-sm">{{ $register->heldBy->first_name }} {{ $register->heldBy->last_name }}</span>
                            @else
                                <span class="text-sm italic text-base-content/40">{{ __('None') }}</span>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm italic text-base-content/40">{{ __('None') }}</p>
                    @endforelse
                </div>
            </div>
        </x-admin.shared.form-section>

        <div class="mt-6 flex justify-end gap-3">
            <x-button :label="__('Cancel')" />
            <x-button class="btn-primary" :label="__('Save Changes')" spinner="save" type="submit" />
        </div>
    </x-form>

   <livewire:club-admin.committee-modal />
</div>
