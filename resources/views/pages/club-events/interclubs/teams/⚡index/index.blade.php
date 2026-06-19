<div x-data="{ mobileSearchOpen: false, mobileActionsOpen: false }">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>
    <x-header progress-indicator separator :title="__('Teams')">
        <x-slot:middle>
            <div class="hidden w-full lg:block">
                <x-input class="w-full" clearable icon="o-magnifying-glass"
                    :placeholder="__('Search...')"
                    wire:model.live.debounce.300ms="search" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            {{-- Mobile: 🔍 · filter · ☰ --}}
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" />
            {{-- Desktop: full buttons --}}
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
                @if ($isAdminOrCommittee)
                    <x-dropdown :label="__('More actions')" icon="o-ellipsis-vertical" right class="btn-ghost btn-sm">
                        <x-menu-item icon="o-squares-plus" :title="__('Build teams')"
                            link="{{ route('admin.interclubs.teams.builder') }}" />
                        @if ($teamsCount > 0)
                            <x-menu-item icon="o-trash" :title="__('Delete all teams')"
                                wire:click="$set('deleteAllModal', true)" />
                        @endif
                    </x-dropdown>
                    <x-button class="btn-primary btn-sm" icon="o-plus" :label="__('New team')"
                        wire:click="$set('createModal', true)" />
                @endif
            </div>
        </x-slot:actions>
    </x-header>

    {{-- Mobile search bar --}}
    <div class="border-b border-base-200 lg:hidden" x-show="mobileSearchOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        style="display:none">
        <div class="flex items-center gap-2 px-4 py-2.5">
            <div class="flex flex-1 items-center gap-2 rounded-xl bg-base-200 px-3 py-2">
                <x-icon name="o-magnifying-glass" class="h-4 w-4 shrink-0 text-base-content/40" />
                <input wire:model.live.debounce.300ms="search"
                    class="flex-1 bg-transparent text-sm outline-none placeholder:text-base-content/40"
                    placeholder="{{ __('Search...') }}" />
            </div>
            <button @click="mobileSearchOpen = false" class="btn btn-ghost btn-circle btn-sm">
                <x-icon name="o-x-mark" class="h-5 w-5" />
            </button>
        </div>
    </div>

    {{-- ── Active filter chips ──────────────────────────────────────────────── --}}
    <x-admin.shared.filter-chips :chips="$filterChips" />

    @if ($teams->isEmpty())
        <x-card class="border-none">
            <div class="py-16 text-center text-gray-500">
                @if ($season)
                    Aucune équipe pour la saison {{ $season->name }}.
                    <div class="mt-4">
                        <x-button class="btn-primary" link="{{ route('admin.interclubs.teams.builder') }}"
                            icon="o-squares-plus" :label="__('Build teams')" />
                    </div>
                @else
                    Aucune saison active. Activez une saison pour gérer les équipes.
                @endif
            </div>
        </x-card>
    @else
        @php
            $groupedTeams = $teams->groupBy('category');
        @endphp

        @php
            $catColor = ['Hommes' => 'blue', 'Vétérans' => 'amber', 'Dames' => 'pink'];
        @endphp
        <div class="space-y-10">
            @foreach ($groupedTeams as $category => $group)
                <x-section-accordion
                    :label="$category"
                    :count="$group->count() . ' équipe' . ($group->count() > 1 ? 's' : '')"
                    :color="$catColor[$category] ?? 'gray'">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 pb-2">
                        @foreach ($group as $team)
                            <x-admin.club-events.teams.team-card :team="$team" wire:key="team-{{ $team->id }}" />
                        @endforeach
                    </div>
                </x-section-accordion>
            @endforeach
        </div>
    @endif

    {{-- Modal création libre --}}
    <x-modal :title="__('New team')" wire:model="createModal">
        <div class="space-y-4">
            <x-select label="Lettre" :options="$teamNameOptions" wire:model="newTeamName"
                placeholder="Choisir A – Z" />
            <x-select :label="__('Category')" :options="$categoryOptions" wire:model="newCategory"
                placeholder="Sélectionner..." />
            <x-select label="Niveau" :options="$levelOptions" wire:model="newLevel"
                placeholder="Sélectionner..." />
            <x-input label="Division" wire:model="newDivision" placeholder="ex: 3B" />
        </div>
        <x-slot:actions>
            <x-button label="Annuler" wire:click="$set('createModal', false)" />
            <x-button class="btn-primary" :label="__('Create')" wire:click="createTeam" spinner />
        </x-slot:actions>
    </x-modal>

    <x-confirm-modal model="deleteModal" :title="__('Delete this team?')" :subtitle="__('Warning!')"
        :confirmLabel="__('Delete')" confirmAction="delete">
        <p>{{ __('Are you sure you want to delete this team? This action is irreversible.') }}</p>
    </x-confirm-modal>

    <x-confirm-modal model="deleteAllModal" :title="__('Delete all teams?')" :subtitle="__('Warning!')"
        :confirmLabel="__('Delete all')" confirmAction="deleteAll">
        <p>
            {{ __('Are you sure you want to delete') }} <strong>{{ __('all teams') }}</strong>
            {{ __('for the current season? This action is irreversible and will also remove all players from their teams.') }}
        </p>
    </x-confirm-modal>

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
                    wire:model.live="selectedSeasonId"
                    :placeholder="__('Select a season')"
                    class="w-full" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- ── Mobile action sheet ─────────────────────────────────────────── --}}
    @if ($isAdminOrCommittee)
        <x-admin.shared.mobile-actions>
            <x-admin.shared.mobile-action-item
                icon="o-plus" color="primary"
                :label="__('New team')"
                :description="__('Create a single team')"
                @click="mobileActionsOpen = false; $wire.set('createModal', true)" />
            <x-admin.shared.mobile-action-item
                icon="o-squares-plus" color="base"
                :label="__('Build teams')"
                :description="__('Auto-assign players to teams')"
                @click="mobileActionsOpen = false; window.location.href = '{{ route('admin.interclubs.teams.builder') }}'" />
            @if ($teamsCount > 0)
                <x-admin.shared.mobile-action-item
                    icon="o-trash" color="error"
                    :label="__('Delete all teams')"
                    :description="__('Remove every team for the current season')"
                    @click="mobileActionsOpen = false; $wire.set('deleteAllModal', true)" />
            @endif
        </x-admin.shared.mobile-actions>
    @endif
</div>
