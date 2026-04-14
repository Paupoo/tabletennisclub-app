<x-slot:breadcrumbs>
    <x-breadcrumbs :items="[['label' => __('Admin')], ['label' => __('Club Settings')]]" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header separator subtitle="{{ __('Configure your club identity and management team') }}"
        title="{{ __('Club Settings') }}" />

    <x-form wire:submit="save">
        <x-admin.shared.form-section :separator="true" :subtitle="__('Official name and federal affiliation')" :title="__('Club Identity')">
            <div class="col-span-6 grid gap-4 md:col-span-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <x-input icon="o-trophy" label="{{ __('Club Name') }}" placeholder="E.g. CTT Ottignies"
                        wire:model="club_name" />
                    <x-input icon="o-identification" label="{{ __('Club ID / Licence') }}" placeholder="E.g. BBW042"
                        wire:model="club_id" />
                </div>

                <x-textarea icon="o-map-pin" label="{{ __('Address') }}"
                    placeholder="{{ __('Street, Number, Postal Code, City') }}" rows="3" wire:model="address" />
            </div>
        </x-admin.shared.form-section>

        <x-admin.shared.form-section :separator="true" :subtitle="__('Public information for members and visitors')" :title="__('Contact Details')">
            <div class="col-span-6 grid gap-4 md:col-span-4">
                <div class="grid grid-cols-1 gap-4 md:col-span-2">
                    <x-input icon="o-envelope" label="{{ __('General Email') }}" wire:model="contact_email" />
                    <x-input icon="o-phone" label="{{ __('Phone Contact') }}" wire:model="contact_phone" />
                </div>
                <x-input label="{{ __('Website URL') }}" prefix="https://" wire:model="website_url" />
            </div>
        </x-admin.shared.form-section>

        <x-admin.shared.form-section :separator="true" :subtitle="__('Manage board members and their roles')" :title="__('Committee')">
            <div class="col-span-6 md:col-span-4">
                <div class="bg-base-200/50 border-base-300 mb-4 rounded-xl border p-4">
                    <div class="mb-4 flex items-center justify-between">
                        <span
                            class="text-xs font-bold uppercase tracking-widest opacity-60">{{ __('Board Members') }}</span>
                        @if($committeeMembers->count() > 0)
                        <x-button @click="$wire.addCommitteeMemberModal = true" class="btn-xs btn-outline"
                            icon="o-plus" label="{{ __('Add Member') }}" />
                        @endif
                    </div>

                    <div class="divide-base-300/50 divide-y">
                        @forelse($committeeMembers as $index => $member)
                            <div class="flex items-center justify-between py-3">
                                <div class="flex items-center gap-3">
                                    <x-avatar class="!w-8 !rounded-lg"
                                        placeholder="{{ substr($member->first_name, 0, 1) }}{{ substr($member->last_name, 0,1) }}" />
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
                                wireClick="$wire.addCommitteeMemberModal = true"
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

        <x-admin.shared.form-section :separator="false" :subtitle="__('System behavior and registration rules')" :title="__('Internal Settings')">
            <div class="col-span-6 space-y-4 md:col-span-4">
                <x-toggle class="toggle-primary" label="{{ __('Allow online membership renewal') }}"
                    wire:model="allow_online_renewal" disabled/>
                <x-toggle class="toggle-primary" label="{{ __('Show training spots availability publicly') }}"
                    wire:model="public_trainings" disabled/>

            </div>
        </x-admin.shared.form-section>

        <div class="col-span-6 mt-6 flex justify-end gap-3">
            <x-button label="{{ __('Cancel') }}" />
            <x-button class="btn-primary" label="{{ __('Save Changes') }}" spinner="save" type="submit" />
        </div>
    </x-form>

    <x-modal wire:model="addCommitteeMemberModal" title="{{ __('Add Committee Member') }}" separator>
    <div class="grid gap-4">
        {{-- Recherche de membre --}}
        <x-choices
            label="{{ __('Search Member') }}"
            wire:model="selectedMemberId"
            :options="$membersSearchList"
            search-function="searchMembers"
            debounce="300ms"
            min-chars="2"
            icon="o-magnifying-glass"
            hint="{{ __('Search by name or license number') }}"
            single
            searchable
            clearable>
            @scope('item', $user)
                    <x-list-item :item="$user" sub-value="  description">
        </x-list-item>

            @endscope
        </x-choices>

        {{-- Sélection du rôle --}}
        <x-select 
            label="{{ __('Committee Role') }}" 
            icon="o-briefcase" 
            placeholder="{{ __('Select a role') }}"
            :options="$this->roleOptions" 
            wire:model="selectedRoleId" 
        />
    </div>

    <x-slot:actions>
        <x-button label="{{ __('Cancel') }}" @click="$wire.addCommitteeMemberModal = false" />
        <x-button 
            label="{{ __('Add to Committee') }}" 
            class="btn-primary" 
            icon="o-check" 
            wire:click="addMember" 
            spinner="addMember" 
        />
    </x-slot:actions>
</x-modal>
</div>