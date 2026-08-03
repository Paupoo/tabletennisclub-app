<div x-data="{ mobileActionsOpen: false }">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Interclubs Schedule')">
        <x-slot:actions>
            {{-- Mobile: filter · ☰ --}}
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" :show-search="false" :show-more="false" />
            <button class="btn btn-primary btn-circle btn-sm lg:hidden" @click="mobileActionsOpen = true">
                <x-icon name="o-bars-3" class="h-5 w-5" />
            </button>
            {{-- Desktop: Filters + New match --}}
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
                <x-button class="btn-primary btn-sm" icon="o-plus" :label="__('New match')"
                    wire:click="openCreateModal" />
            </div>
        </x-slot:actions>
    </x-header>

    {{-- ── Active filter chips ──────────────────────────────────────────────── --}}
    <x-admin.shared.filter-chips :chips="$filterChips" />

    @if (! $seasonId)
        <x-card class="mt-4 border-none">
            <p class="py-12 text-center text-sm text-gray-500">{{ __('Select a season to manage the schedule.') }}</p>
        </x-card>
    @elseif ($grouped->isEmpty())
        <x-card class="mt-4 border-none">
            <div class="py-16 text-center text-gray-500">
                {{ __('No matches scheduled for this season.') }}
                <div class="mt-4">
                    <x-button class="btn-primary btn-sm" icon="o-plus" :label="__('Add first match')"
                        wire:click="openCreateModal" />
                </div>
            </div>
        </x-card>
    @else
        @php
            $catColor = [
                'Hommes'   => 'blue',
                'Vétérans' => 'amber',
                'Dames'    => 'pink',
            ];
        @endphp

        <div class="space-y-10">
            @foreach ($grouped as $category => $teams)
                <x-section-accordion
                    :label="$category"
                    :count="$teams->flatten(1)->count() . ' match' . ($teams->flatten(1)->count() > 1 ? 's' : '')"
                    :color="$catColor[$category] ?? 'gray'">
                    <div class="space-y-6">
                            @foreach ($teams->sortKeys() as $teamName => $matches)
                                <section x-data="{ open: false }">
                                    <div
                                        class="mb-3 flex w-full cursor-pointer items-center gap-3"
                                        @click="open = !open"
                                    >
                                        <span class="bg-base-200 rounded-lg px-3 py-1.5 text-sm font-bold">{{ $teamName }}</span>
                                        <span class="text-base-content/40 text-xs">{{ $matches->count() }} match{{ $matches->count() > 1 ? 's' : '' }}</span>
                                        <div class="border-base-200 flex-1 border-t"></div>
                                        <x-button
                                            class="btn-ghost btn-sm btn-circle"
                                            icon="o-plus"
                                            :tooltip="__('Add match for this team')"
                                            wire:click.stop="openCreateModal({{ $matches->first()['our_team_id'] }})" />
                                        <x-icon name="o-chevron-down" class="h-4 w-4 opacity-40 transition-transform duration-200" ::class="open ? '' : '-rotate-90'" />
                                    </div>

                                    <div x-show="open" x-collapse>
                                        <x-card class="overflow-hidden p-0">
                                            <div class="divide-base-200 divide-y">
                                                @foreach ($matches as $match)
                                                    <div class="hover:bg-base-50 flex items-center gap-4 px-4 py-3 transition-colors">
                                                        {{-- Week badge --}}
                                                        <div class="bg-base-200 flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-xl">
                                                            <div class="text-[8px] font-black uppercase opacity-40">S</div>
                                                            <div class="text-sm font-black leading-none">{{ isset($match['week']) ? ($matchDayMap[$match['week']] ?? $match['week']) : '—' }}</div>
                                                        </div>

                                                        {{-- Match info --}}
                                                        <div class="min-w-0 flex-1">
                                                            <div class="flex flex-wrap items-center gap-2">
                                                                @if ($match['is_home'])
                                                                    <x-badge class="badge-neutral badge-xs font-bold" value="{{ __('Home') }}" />
                                                                @else
                                                                    <x-badge class="badge-ghost badge-xs border border-base-300 font-bold" value="{{ __('Away') }}" />
                                                                @endif
                                                                <span class="font-bold">{{ $match['opponent'] }}</span>
                                                                @if ($match['division'])
                                                                    <span class="text-base-content/40 text-xs">{{ $match['division'] }}</span>
                                                                @endif
                                                            </div>
                                                            <div class="text-base-content/50 mt-0.5 flex items-center gap-2 text-xs">
                                                                <x-icon name="o-calendar" class="h-3 w-3" />
                                                                {{ $match['date'] }} {{ __('at') }} {{ $match['time'] }}
                                                                @if ($match['address'] !== '—')
                                                                    <span class="opacity-50">·</span>
                                                                    <x-icon name="o-map-pin" class="h-3 w-3" />
                                                                    <span class="truncate">{{ $match['address'] }}</span>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        {{-- Actions --}}
                                                        <div class="flex shrink-0 gap-1">
                                                            <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil"
                                                                :tooltip="__('Edit')"
                                                                wire:click="openEditModal({{ $match['id'] }})" />
                                                            <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-trash"
                                                                :tooltip="__('Delete')"
                                                                wire:click="confirmDelete({{ $match['id'] }})" />
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </x-card>
                                    </div>
                                </section>
                            @endforeach
                        </div>
                </x-section-accordion>
            @endforeach
        </div>
    @endif

    {{-- Modal create / edit --}}
    <x-app-modal wire:model="editModal" :title="$editingInterclubId ? __('Edit match') : __('New match')" separator>
        <div class="space-y-4">
            <x-select
                :label="__('Our team')"
                :options="$ourTeamOptions"
                option-label="name"
                option-value="id"
                wire:model.live="formOurTeamId"
                :placeholder="__('Select a team')" />

            <x-select
                :label="__('Opponent')"
                :options="$opponentTeams"
                option-label="name"
                option-value="id"
                wire:model.live="formOpponentTeamId"
                :placeholder="$formOurTeamId ? __('Select an opponent') : __('Select your team first')" />

            <div class="grid grid-cols-2 gap-4">
                <x-datepicker
                    :label="__('Date')"
                    wire:model="formDate"
                    icon="o-calendar" />
                <x-input
                    :label="__('Time')"
                    wire:model="formTime"
                    placeholder="09:00"
                    icon="o-clock" />
            </div>

            <x-toggle :label="__('Home match')" wire:model.live="formIsHome" />

            <x-input
                :label="__('Address')"
                wire:model="formAddress"
                :placeholder="__('Venue address')"
                icon="o-map-pin" />
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('editModal', false)" />
            <x-button class="btn-primary" :label="__('Save')" wire:click="save" spinner />
        </x-slot:actions>
    </x-app-modal>

    <x-confirm-modal model="deleteModal" :title="__('Delete match?')" :subtitle="__('Warning!')"
        :confirmLabel="__('Delete')" confirmAction="delete">
        <p>{{ __('Are you sure you want to delete this match? This action is irreversible.') }}</p>
    </x-confirm-modal>

    {{-- ── Mobile action sheet ─────────────────────────────────────────── --}}
    <x-admin.shared.mobile-actions>
        <x-admin.shared.mobile-action-item
            icon="o-plus" color="primary"
            :label="__('New match')"
            :description="__('Add a match to the schedule')"
            @click="mobileActionsOpen = false; $wire.call('openCreateModal')" />
    </x-admin.shared.mobile-actions>

    {{-- ── Filter drawer ────────────────────────────────────────────────────── --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Season') }}
                </p>
                <x-select
                    :options="$seasons"
                    option-label="name"
                    option-value="id"
                    wire:model.live="seasonId"
                    :placeholder="__('Select a season')"
                    class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Team') }}
                </p>
                <x-select
                    :options="[['id' => null, 'name' => __('All teams')], ...$ourTeamOptions]"
                    option-label="name"
                    option-value="id"
                    wire:model.live="selectedTeamId"
                    :placeholder="__('All teams')"
                    class="w-full" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>
</div>
