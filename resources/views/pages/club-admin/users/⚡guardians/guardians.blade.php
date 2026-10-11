<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

@php
    $guardians = $this->guardians;
@endphp

<div x-data="{ mobileSearchOpen: false }">
    <x-header :title="__('Responsible adults')"
        :subtitle="__('Every adult on file for a child, and where each one stands')"
        separator progress-indicator>
        <x-slot:middle>
            <div class="hidden w-full lg:block">
                <x-input class="w-full" clearable icon="o-magnifying-glass"
                    :placeholder="__('Adult or child name, email, phone...')"
                    wire:model.live.debounce.300ms="search" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" :show-more="false" />
            @can('users.update')
                <x-button class="btn-ghost btn-sm lg:hidden" wire:click="toggleSelectionMode"
                    :icon="$selectionModeActive ? 'o-x-mark' : 'o-check-circle'"
                    :label="$selectionModeActive ? __('Cancel') : __('Select')" />
            @endcan
            @can('create', \App\Domains\ClubAdmin\Users\Models\Guardian::class)
                <x-button class="btn-primary btn-sm btn-circle lg:hidden" icon="o-plus" :aria-label="__('Create')"
                    wire:click="$dispatch('create-guardian')" />
            @endcan
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
                @can('create', \App\Domains\ClubAdmin\Users\Models\Guardian::class)
                    <x-button class="btn-primary btn-sm" icon="o-plus" :label="__('Create')"
                        wire:click="$dispatch('create-guardian')" />
                @endcan
            </div>
        </x-slot:actions>
    </x-header>

    {{-- Mobile search bar --}}
    <div class="border-b border-base-300 lg:hidden" x-show="mobileSearchOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        style="display:none">
        <div class="flex items-center gap-2 px-4 py-2.5">
            <div class="flex flex-1 items-center gap-2 rounded-xl bg-base-200 px-3 py-2">
                <x-icon name="o-magnifying-glass" class="h-4 w-4 shrink-0 text-base-content/60" />
                <input wire:model.live.debounce.300ms="search"
                    class="flex-1 bg-transparent text-sm outline-none placeholder:text-base-content/60"
                    placeholder="{{ __('Adult or child name, email, phone...') }}" />
            </div>
            <button type="button" @click="mobileSearchOpen = false" class="btn btn-ghost btn-circle btn-sm"
                aria-label="{{ __('Close the search') }}">
                <x-icon name="o-x-mark" class="h-5 w-5" />
            </button>
        </div>
    </div>

    <x-admin.shared.filter-chips :chips="$filterChips" />

    @if ($guardians->isEmpty())
        <x-admin.shared.list-empty-state icon="o-users" :filtered="count($filterChips) > 0"
            :heading="__('No responsible adult found')" />
    @endif

    {{-- ── Vue mobile ───────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 lg:hidden">
        @foreach ($guardians as $guardian)
            <div class="rounded-lg border border-base-300 bg-base-100 p-3" wire:key="guardian-card-{{ $guardian->id }}">
                <div class="flex items-center gap-3">
                    @if ($selectionModeActive)
                        <input type="checkbox" class="checkbox checkbox-primary checkbox-sm shrink-0"
                            value="{{ $guardian->id }}" wire:model.live="selected"
                            aria-label="{{ __('Select :name', ['name' => $guardian->full_name]) }}" />
                    @endif
                    <div class="font-medium">{{ $guardian->full_name }}</div>
                </div>
                <div class="truncate text-xs text-muted">
                    {{ $guardian->phone }}{{ $guardian->email ? ' · ' . $guardian->email : '' }}
                </div>
                <div class="text-xs text-muted">
                    {{ __('On file since :date', ['date' => $guardian->created_at?->format('d/m/Y')]) }}
                </div>
                <x-admin.users.guardian-counterpart :counterpart="$this->counterpartOf($guardian)" class="mt-1" />
                <div class="mt-2 flex flex-wrap items-start justify-between gap-2 text-sm">
                    <x-admin.users.guardian-children :guardian="$guardian" />
                    <x-admin.users.guardian-account-badge :guardian="$guardian" />
                </div>
                @if ($guardian->is_member_this_season)
                    <div class="mt-1 text-sm">
                        <x-admin.users.guardian-membership :guardian="$guardian" />
                    </div>
                @endif
                <div class="mt-3">
                    <x-admin.users.guardian-row-actions :guardian="$guardian" />
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── Vue bureau ───────────────────────────────────────────────── --}}
    <div @class(['hidden', 'lg:block' => $guardians->isNotEmpty()])>
        <x-card>
            <div class="overflow-x-auto lg:overflow-x-visible">
                <table class="table">
                    <thead>
                        <tr>
                            @can('users.update')
                                <th class="w-1">
                                    <input type="checkbox" class="checkbox checkbox-sm" wire:model.live="selectAll"
                                        aria-label="{{ __('Select the whole page') }}" />
                                </th>
                            @endcan
                            <th>
                                <button type="button" class="inline-flex cursor-pointer items-center gap-1" wire:click="$set('sortBy', 'name')">
                                    {{ __('Name') }}
                                    @if ($sortBy === 'name')
                                        <x-icon name="o-arrow-small-up" class="h-4 w-4" />
                                    @endif
                                </button>
                            </th>
                            <th>
                                <button type="button" class="inline-flex cursor-pointer items-center gap-1" wire:click="$set('sortBy', 'created_at')">
                                    {{ __('On file since') }}
                                    @if ($sortBy === 'created_at')
                                        <x-icon name="o-arrow-small-down" class="h-4 w-4" />
                                    @endif
                                </button>
                            </th>
                            <th>{{ __('Children') }}</th>
                            <th>{{ __('Account') }}</th>
                            <th>{{ __('Club member') }}</th>
                            <th><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($guardians as $guardian)
                            <tr wire:key="guardian-row-{{ $guardian->id }}">
                                @can('users.update')
                                    <td>
                                        <input type="checkbox" class="checkbox checkbox-sm" value="{{ $guardian->id }}"
                                            wire:model.live="selected"
                                            aria-label="{{ __('Select :name', ['name' => $guardian->full_name]) }}" />
                                    </td>
                                @endcan
                                <td>
                                    <div class="font-medium whitespace-nowrap">{{ $guardian->full_name }}</div>
                                    <div class="text-xs text-muted">
                                        {{ $guardian->phone }}{{ $guardian->email ? ' · ' . $guardian->email : '' }}
                                    </div>
                                    <x-admin.users.guardian-counterpart :counterpart="$this->counterpartOf($guardian)" class="mt-1" />
                                </td>
                                <td class="whitespace-nowrap">{{ $guardian->created_at?->format('d/m/Y') }}</td>
                                <td>
                                    <x-admin.users.guardian-children :guardian="$guardian" />
                                </td>
                                <td><x-admin.users.guardian-account-badge :guardian="$guardian" /></td>
                                <td><x-admin.users.guardian-membership :guardian="$guardian" /></td>
                                <td><x-admin.users.guardian-row-actions :guardian="$guardian" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    <div class="mt-4">
        {{ $guardians->links() }}
    </div>

    @can('users.update')
        <x-admin.shared.selection-pill :selected="$selected" :total="$this->getTotalMatchingCount()"
            :selecting-all-results="$selectingAllResults" :select-all="$selectAll">
            <x-slot:actions>
                @can('sendEmail', \App\Domains\ClubAdmin\Users\Models\User::class)
                    <x-button class="btn-ghost btn-sm" icon="o-envelope" :label="__('Invite')"
                        wire:click="bulkInvite" spinner="bulkInvite" />
                @endcan
                <x-button class="btn-ghost btn-sm text-error" icon="o-trash" :label="__('Delete')"
                    wire:click="confirmBulkDelete" />
            </x-slot:actions>
        </x-admin.shared.selection-pill>

        <x-confirm-modal model="bulkDeleteModal" :open="$bulkDeleteModal" :title="__('Delete the selected responsible adults?')"
            :subtitle="__('Sheets a child still at the club relies on are kept, and named.')"
            confirm-label="Delete" confirm-action="bulkDelete" />
    @endcan

    <x-confirm-modal model="deleteModal" :open="$deleteModal" :title="__('Delete this responsible adult?')"
        :subtitle="__('The sheet is deleted for good.')"
        confirm-label="Delete" confirm-action="delete" />

    <livewire:admin.users.guardian-editor />

    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <x-toggle :label="__('On file twice')" wire:model.live="onFileTwice" />
            <x-toggle :label="__('No child')" wire:model.live="withoutChildren" />
            <x-select :label="__('Account')" wire:model.live="account"
                :options="collect($this->accountOptions())->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values()->all()"
                :placeholder="__('All')" />
            <x-select :label="__('Sort by')" wire:model.live="sortBy"
                :options="[['id' => 'name', 'name' => __('Name')], ['id' => 'created_at', 'name' => __('Last on file first')]]" />
        </x-slot:filters>
    </x-admin.shared.filter-drawer>
</div>
