<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Opponent Clubs')">
        <x-slot:subtitle>{{ __('Manage opponent clubs once, reuse them across seasons when setting up divisions.') }}</x-slot:subtitle>
        <x-slot:middle class="!justify-end">
            <x-input
                :placeholder="__('Search…')"
                wire:model.live.debounce="search"
                icon="o-magnifying-glass"
                clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button
                class="btn-primary btn-sm"
                icon="o-plus"
                :label="__('New club')"
                wire:click="openCreateModal" />
        </x-slot:actions>
    </x-header>

    @if ($clubs->isEmpty())
        <x-card class="mt-4 border-none">
            <div class="py-16 text-center text-gray-500">
                @if ($search)
                    <p class="text-sm">{{ __('No clubs match your search.') }}</p>
                @else
                    <p class="text-sm">{{ __('No opponent clubs yet.') }}</p>
                    <x-button class="btn-primary btn-sm mt-4" icon="o-plus" :label="__('Add first club')" wire:click="openCreateModal" />
                @endif
            </div>
        </x-card>
    @else
        <x-card class="overflow-hidden p-0">
            <div class="divide-base-200 divide-y">
                @foreach ($clubs as $club)
                    <div class="hover:bg-base-50 flex items-center gap-4 px-5 py-4 transition-colors" wire:key="club-{{ $club->id }}">

                        {{-- Avatar initial --}}
                        <div class="bg-base-200 text-base-content/60 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold uppercase">
                            {{ mb_substr($club->name, 0, 1) }}
                        </div>

                        {{-- Info --}}
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ $club->name }}</p>
                            <div class="text-base-content/50 mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs">
                                @if ($club->street || $club->city_name)
                                    <span class="flex items-center gap-1">
                                        <x-icon name="o-map-pin" class="h-3 w-3" />
                                        {{ implode(', ', array_filter([$club->street, trim($club->city_code . ' ' . $club->city_name)])) }}
                                    </span>
                                @endif
                                <span class="flex items-center gap-1 opacity-50">
                                    <x-icon name="o-identification" class="h-3 w-3" />
                                    {{ $club->licence }}
                                </span>
                            </div>
                        </div>

                        {{-- Team count badge --}}
                        @if ($club->teams_count > 0)
                            <x-badge
                                class="badge-ghost border-base-300 border text-xs"
                                value="{{ $club->teams_count }} {{ Str::plural(__('team'), $club->teams_count) }}" />
                        @endif

                        {{-- Actions --}}
                        <div class="flex shrink-0 gap-1">
                            <x-button
                                class="btn-ghost btn-sm btn-circle"
                                icon="o-pencil"
                                :tooltip="__('Edit')"
                                wire:click="openEditModal({{ $club->id }})" />
                            <x-button
                                class="btn-ghost btn-sm btn-circle text-error"
                                icon="o-trash"
                                :tooltip="__('Delete')"
                                wire:click="confirmDelete({{ $club->id }})" />
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    {{-- Modal create / edit --}}
    <x-modal wire:model="editModal" :title="$editingClubId ? __('Edit club') : __('New club')" separator>
        <div class="space-y-4">
            <x-input
                :label="__('Club name')"
                wire:model="formName"
                placeholder="ex: TT Wavre"
                icon="o-building-office"
                required />
            <x-input
                :label="__('Licence')"
                wire:model="formLicence"
                :placeholder="__('Auto-generated if empty')"
                icon="o-identification"
                :hint="__('Official federation licence number. Leave empty to auto-generate.')" />
            <x-input
                :label="__('Street address')"
                wire:model="formStreet"
                placeholder="ex: Rue de la Gare 10"
                icon="o-map-pin" />
            <div class="grid grid-cols-3 gap-3">
                <x-input
                    :label="__('Postal code')"
                    wire:model="formCityCode"
                    placeholder="1300"
                    class="col-span-1" />
                <x-input
                    :label="__('City')"
                    wire:model="formCityName"
                    placeholder="ex: Wavre"
                    class="col-span-2" />
            </div>
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('editModal', false)" />
            <x-button class="btn-primary" :label="__('Save')" wire:click="save" spinner />
        </x-slot:actions>
    </x-modal>

    <x-confirm-modal model="deleteModal" :title="__('Delete club?')" :subtitle="__('Warning!')"
        :confirmLabel="__('Delete')" confirmAction="delete">
        <p>{{ __('Are you sure you want to delete this club? This action cannot be undone.') }}</p>
    </x-confirm-modal>
</div>
