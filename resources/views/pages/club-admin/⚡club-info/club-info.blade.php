<x-slot:breadcrumbs>
    <x-breadcrumbs :items="[['label' => __('Admin')], ['label' => __('Club Settings')]]" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header separator subtitle="{{ __('Configure your club identity and management team') }}"
        title="{{ __('Club Info') }}" />

    <x-form wire:submit="save">
        {{-- Name & ID --}}
        <x-admin.shared.form-section :separator="true" :subtitle="__('Official name and federal affiliation')" :title="__('Club Identity')">
            <div class="col-span-6 grid gap-4 md:col-span-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-input icon="o-trophy" label="{{ __('Club Name') }}" placeholder="E.g. CTT Ottignies"
                        wire:model="name" required />
                    <x-input icon="o-identification" label="{{ __('Club ID / Licence') }}" placeholder="E.g. BBW042"
                        wire:model="licence" required />
                </div>

            </div>
        </x-admin.shared.form-section>
        
        {{-- Location --}}
        <x-admin.shared.form-section :separator="true" :subtitle="__('Information to help members and visitors to find our club.')" :title="__('Location Details')">
            <x-input icon="o-map-pin" label="{{ __('Street') }}" wire:model="street" required/>
            <x-input icon="o-map-pin" label="{{ __('City Code') }}" wire:model="city_code" required/>
            <x-input icon="o-map-pin" label="{{ __('City Name') }}" wire:model="city_name" required/>
            <x-input icon="o-building-office" label="{{ __('Building Name (Optional)') }}" wire:model="building_name"/>
            <x-input icon="o-map-pin" label="{{ __('Latitude (Optional)') }}" wire:model="latitude" numeric/>
            <x-input icon="o-map-pin" label="{{ __('Longitude (Optional)') }}" wire:model="longitude" numeric/>
            
        </x-admin.shared.form-section>
        
        {{-- Contact --}}
        <x-admin.shared.form-section :separator="true" :subtitle="__('Information to facilitate people to contact us.')" :title="__('Contact Details')">
                    <x-input icon="o-phone" label="{{ __('Phone Contact') }}" wire:model="phone_contact" />
                    <x-input icon="o-envelope-open" label="{{ __('Email Contact') }}" wire:model="email_contact" />
                    <x-input label="{{ __('Website URL') }}" prefix="https://" wire:model="website_url" />
        </x-admin.shared.form-section>

        {{-- Accounting --}}
        <x-admin.shared.form-section :separator="true" :subtitle="__('Banking and accounting data')" :title="__('Accounting')">
            <x-input icon="o-currency-euro" label="{{ __('Bank Account') }}" wire:model="bank_account" />
            <x-input icon="o-identification" label="{{ __('Enterprise Number (Optional)') }}" wire:model="enterprise_number" />
        </x-admin.shared.form-section>
                
        {{-- Committee --}}
        <x-admin.shared.form-section :separator="true" :subtitle="__('Manage board members and their roles')" :title="__('Committee')">
            <div class="col-span-6 md:col-span-4">
                <div class="bg-base-200/50 border-base-300 mb-4 rounded-xl border p-4">
                    <div class="mb-4 flex items-center justify-between">
                        <span
                            class="text-xs font-bold uppercase tracking-widest opacity-60">{{ __('Board Members') }}</span>
                        @if($committeeMembers->count() > 0)
                        <x-button @click="$dispatch('open-committee-modal')" class="btn-xs btn-outline"
                            icon="o-plus" label="{{ __('Add Member') }}" />
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
                                            <div class="badge badge-outline text-[10px] opacity-70">
                                                {{ __($member->committee_role
                                                    ? $member->committee_role->label() 
                                                    : 'Unknown role') }}
                                            </div>
                                    </div>
                                </div>
                                <x-button class="btn-circle btn-ghost btn-xs text-error" icon="o-trash"
                                    wire:click="removeMember({{ $member->id }})" />
                            </div>
                        @empty
                            <x-admin.shared.empty
                                icon="o-users"
                                title="{{ __('No committee members defined yet.') }}"
                                subtitle="{{ __('Add your first board member using the button above.') }}"
                                action="{{ __('Add Member') }}"
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

        <div class="col-span-6 mt-6 flex justify-end gap-3">
            <x-button label="{{ __('Cancel') }}" />
            <x-button class="btn-primary" label="{{ __('Save Changes') }}" spinner="save" type="submit" />
        </div>
    </x-form>

   <livewire:club-admin.committee-modal />
</div>