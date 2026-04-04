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
                <x-header subtitle="{{ __('Personal information') }}" title="{{ __('Personal') }}" />
            </div>

            <div class="col-span-6 grid gap-2 md:col-span-4">
                <div class="grid gap-6 lg:grid-cols-2">
                    <x-input label="{{ __('First Name') }}" wire:model="first_name" />
                    <x-input label="{{ __('Last Name') }}" wire:model="last_name" />
                    <x-input label="{{ __('Email') }}" wire:model="email" />
                    <x-group :options="$genders" class="btn-soft" inline label="{{ __('Gender') }}"
                        wire:model="gender" />
                    <x-input label="{{ __('Street') }}" wire:model="street" />
                    <x-input autocomplete="city_code" inputmode="numeric" label="{{ __('Postal Code') }}"
                        max="9999" min="1000" pattern="[0-9]*" type="number"
                        wire:model.live.debounce.500ms="city_code" />
                    <x-input label="{{ __('City') }}" wire:model="city_name" />
                    <x-input label="{{ __('Phone Number') }}" wire:model="phone_number" />
                    <x-datetime label="{{ __('Birthdate') }}" wire:model="birthdate" />
                    <x-input label="{{ __('Parent or tutor phone number') }}" wire:model="parent_phone_number" />
                    <div>
                        <div wire:key="photo-container-{{ $imageKey }}">
                            <x-file accept="image/png, image/jpeg, image/webp" crop-after-change
                                label="{{ __('Photo') }}" wire:model="photo">
                                <img alt="{{ __('Avatar') }}" class="h-36 rounded-lg object-cover"
                                    src="{{ $photo ? $photo->temporaryUrl() : ($currentPhoto ? asset($currentPhoto) : asset('images/empty-user.jpg')) }}">
                            </x-file>
                        </div>
                        @if ($currentPhoto)
                            <x-button class="btn-soft btn-ghost m-2 w-36 text-xs" label="{{ __('Delete photo') }}"
                                wire:click="$set('deleteModal', true)" />
                        @endif
                    </div>
                </div>

            </div>

            <div class="col-span-6">
                <x-menu-separator />
            </div>

            <!-- Section Security -->
            <div class="col-span-6 md:col-span-2">
                <x-header subtitle="{{ __('Secure your account') }}" title="{{ __('Security') }}" />
            </div>
            <div class="col-span-6 md:col-span-4">
                <x-password
                    hint="{{ __('Minimum 8 charachters, with at least 1 letter, 1 number and 1 special character') }}"
                    label="Password" wire:model.live.debounce="password" />
                <x-password label="Password Confirmation" wire:model.live.debounce="password_confirmation" />
            </div>
            <div class="col-span-6">
                <x-menu-separator />
            </div>

            <!-- Section Registration -->
            <div class="col-span-6 md:col-span-2">
                <x-header subtitle="{{ __('Registration info') }}" title="{{ __(key: 'Registration') }}" />
            </div>

            <div class="col-span-6 md:col-span-4">
                <x-group :options="$licence_types" class="btn-soft" inline label="{{ __('Licence Type') }}"
                    wire:model.live="licence_type" />
                @if ($licence_type == 'competitive')
                    <x-input label="{{ __('Licence *') }}" mandatory numeric wire:model.live.debounce="licence" />
                    <x-select :options="$rankings" icon="o-scale" label="{{ __('Ranking') }}" wire:model.live="ranking" />
                @endif
                <x-choices :options="$trainings"
                    hint="{{ __('Select the trainings you wish to attend to (available sessions only)') }}"
                    icon="o-calendar" label="{{ __('Trainings') }}" wire:model="trainings_ids">
                    @scope('item', $training)
                        <x-list-item :item="$training" sub-value="group" value="day">
                            <x-slot:actions>
                                @if (isset($training['availablePlaces']) && $training['availablePlaces'] !== null)
                                    <x-badge :value="$training['availablePlaces'] . __(' slots remaining')" class="badge-soft badge-primary badge-sm" />
                                @else
                                    <x-badge :value="__('Free')" class="badge-soft badge-primary badge-sm" />
                                @endif
                            </x-slot:actions>
                        </x-list-item>
                    @endscope

                    {{-- Selection slot --}}
                    @scope('selection', $training)
                        {{ $training['day'] }} ({{ $training['group'] }})
                    @endscope

                </x-choices>
            </div>
            <div class="col-span-6">
                <x-menu-separator />
            </div>

            <!-- Section Permissions -->
            <div class="col-span-6 md:col-span-2">
                <x-header subtitle="{{ __('Define the roles and permissions here') }}"
                    title="{{ __('Permissions') }}" />
            </div>
            <div class="col-span-6 md:col-span-4">
                <x-checkbox
                    hint="{{ __('An active member is up to date with his or her membership fees and is authorized to participate in all club activities') }}"
                    label="{{ __('Is Active') }}" wire:model="is_active" />
                <x-checkbox
                    hint="{{ __('Committee Members are granted most accesses like creating, updating and deleting objects (Users, teams, tournaments...)') }}"
                    label="{{ __('Is a committee member') }}" wire:model="is_committee_member" />
                <x-checkbox hint="{{ __('With great power comes great responsibility...') }}"
                    label="{{ __('Is an administrator') }}" wire:model="is_admin" />
            </div>

            @if ($user)
                <div class="col-span-6">
                    <x-menu-separator />
                </div>

                <!-- Section Danger/Delete -->
                <div class="col-span-6 md:col-span-2">
                    <x-header class="text-danger" subtitle="{{ __('Watch out! Be careful and act wisely...') }}"
                        title="{{ __('Danger zone') }}" />
                </div>
                <div class="col-span-6 md:col-span-4">
                    <x-input
                        hint="{{ __('Type DELETE before pressing the red button. This will permanently delete your data and club history.') }}"
                        label="{{ __('Delete Confirmation') }}" placeholder="{{ __('Confirm here') }}" />
                    <x-button class="btn-error btn-md mt-6" label="{{ __('Request Deletion') }}" />
                </div>
            @endif

        </div>
        <x-slot:actions>
            <x-button label=" {{ __('Reset') }}" />
            <x-button class="btn-primary" label="{{ $user ? __('Update') : __('Create') }}" spinner="save"
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
    <!-- FILTER DRAWER -->
    {{-- <x-drawer class="lg:w-1/3" right separator title="Filters" wire:model="drawer" with-close-button>
        <x-input @keydown.enter="$wire.drawer = false" icon="o-magnifying-glass" placeholder="Search..."
            wire:model.live.debounce="search" />

        <x-slot:actions>
            <x-button icon="o-x-mark" label="Reset" spinner wire:click="clear" />
            <x-button @click="$wire.drawer = false" class="btn-primary" icon="o-check" label="Done" />
        </x-slot:actions>
    </x-drawer>

    <x-modal subtitle="{{ __('Warning!') }}" title="{{ __('Confirmation of deletion') }}" wire:model="deleteModal">
        <x-slot>
            {{ __('Are you sure you want to delete this picture? This action is irreversible.') }}
        </x-slot>

        <x-slot:actions>
            <x-button @click="$wire.deleteModal = false" label="{{ __('Cancel') }}" />
            <x-button class="btn-error" label="{{ __('Delete') }}" spinner wire:click="deletePhoto" />
        </x-slot:actions>
    </x-modal> --}}
</div>