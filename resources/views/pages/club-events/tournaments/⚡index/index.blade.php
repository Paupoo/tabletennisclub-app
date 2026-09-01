<div x-data="{ mobileSearchOpen: false, mobileActionsOpen: false }">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Tournaments')">
        <x-slot:middle>
            <div class="hidden w-full lg:block">
                <x-input class="w-full" clearable icon="o-magnifying-glass"
                    :placeholder="__('Search…')"
                    wire:model.live.debounce.300ms="search" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            {{-- Mobile: 🔍 · filter · ☰ --}}
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" />
            {{-- Desktop: full buttons --}}
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
                @if ($this->canManage)
                    <x-button class="btn-primary btn-sm" icon="o-plus" :label="__('Create')"
                        link="{{ route('admin.tournaments.wizard') }}" />
                @endif
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
                <x-icon name="o-magnifying-glass" class="h-4 w-4 shrink-0 text-base-content/40" />
                <input wire:model.live.debounce.300ms="search"
                    class="flex-1 bg-transparent text-sm outline-none placeholder:text-base-content/40"
                    placeholder="{{ __('Search…') }}" />
            </div>
            <button type="button" @click="mobileSearchOpen = false" class="btn btn-ghost btn-circle btn-sm"
                aria-label="{{ __('Close the search') }}">
                <x-icon name="o-x-mark" class="h-5 w-5" />
            </button>
        </div>
    </div>

    {{-- ── Active filter chips ──────────────────────────────────────────────── --}}
    <x-admin.shared.filter-chips :chips="$filterChips" />

    {{--
        Les quatre compteurs occupaient 110 px, répétaient le filtre du tiroir et
        n'étaient pas cliquables : on les lit une fois. Le même dénombrement tient
        en 36 px et filtre. Le tiroir garde le filtre par statut précis ; les deux
        se composent.
    --}}
    @php
        $phases = [
            ''         => ['label' => __('All'),      'count' => $stats['total']],
            'live'     => ['label' => __('Live'),     'count' => $stats['live']],
            'upcoming' => ['label' => __('Upcoming'), 'count' => $stats['upcoming']],
            'done'     => ['label' => __('Closed'),   'count' => $stats['closed']],
        ];
    @endphp
    <div class="mb-6 flex flex-wrap gap-1.5" role="group" aria-label="{{ __('Filter by phase') }}">
        @foreach ($phases as $value => $phase)
            <button type="button"
                wire:click="$set('phase', '{{ $value }}')"
                aria-pressed="{{ $value === $this->phase ? 'true' : 'false' }}"
                @class([
                    'flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors',
                    'border-primary bg-primary text-primary-content' => $value === $this->phase,
                    'border-base-300 text-base-content/70 hover:border-primary/40 hover:text-base-content' => $value !== $this->phase,
                ])>
                <span>{{ $phase['label'] }}</span>
                <span @class([
                    'rounded px-1.5 text-xs font-bold tabular-nums',
                    'bg-primary-content/20' => $value === $this->phase,
                    'bg-base-200' => $value !== $this->phase,
                ])>{{ $phase['count'] }}</span>
            </button>
        @endforeach
    </div>

    {{-- ── Vue mobile (list) ─────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 lg:hidden">
        @forelse ($tournaments as $tournament)
            {{-- L'identité prend la largeur, les actions passent dessous : sur une
            carte de 335 px, une action nommée et son menu en prenaient 156 et le
            titre était tranché. --}}
            <div class="rounded-lg border border-base-300 bg-base-100 p-3"
                wire:key="mobile-tournament-{{ $tournament->id }}">
                <div class="flex items-start gap-3">
                    @if ($selectionModeActive && $this->canManage)
                        <input type="checkbox"
                            class="checkbox checkbox-primary checkbox-sm"
                            value="{{ $tournament->id }}"
                            wire:model.live="selected" />
                    @endif

                    <div class="min-w-0 flex-1">
                        <div class="font-medium">{{ $tournament->name }}</div>
                        <div class="mt-0.5 flex flex-wrap items-center gap-2">
                            <x-badge :value="$tournament->status->getLabel()"
                                class="{{ $tournament->status->badgeClass() }} badge-sm" />
                            <span class="text-xs text-base-content/40">
                                {{ $tournament->start_date->translatedFormat('d M Y') }}
                            </span>
                            <span class="text-xs text-base-content/40">
                                {{ $tournament->match_type === 'double' ? __('Doubles') : __('Singles') }}
                            </span>
                            @php
                                $mobileActive = $tournament->active_registrations_count ?? 0;
                                $mobileMax = $tournament->max_users;
                            @endphp
                            <span class="text-xs tabular-nums text-base-content/40">
                                {{ $mobileActive }}@if ($mobileMax > 0)&nbsp;/&nbsp;{{ $mobileMax }}@endif
                            </span>
                        </div>

                        {{-- La même réponse que la colonne « Attend de vous » du tableau :
                             la carte mobile ne doit pas dire moins que la ligne desktop. --}}
                        @php $mobileNext = $this->nextActionFor($tournament); @endphp
                        @if ($mobileNext !== null)
                            <p @class([
                                'mt-1.5 flex items-center gap-1 text-xs',
                                'text-warning-content' => $mobileNext->urgent,
                                'text-base-content/60' => ! $mobileNext->urgent,
                            ])>
                                <x-icon name="o-arrow-right-circle" class="h-3.5 w-3.5 shrink-0" />
                                <span class="truncate">{{ $mobileNext->label }}</span>
                            </p>
                        @endif
                    </div>
                </div>

                <div class="mt-3">
                    @if (! $selectionModeActive)
                        <x-admin.shared.row-menu
                            :label="__('Settings')"
                            icon="o-cog-6-tooth"
                            {{-- ?step=1 : l'icône réglages doit ouvrir la configuration. Sans étape
                 explicite, mount() la déduit du statut et ouvre les invitations. --}}
                link="{{ route('admin.tournaments.wizard.edit', [$tournament, 'step' => 1]) }}">
                            @if ($tournament->status !== \App\Domains\Shared\Enums\TournamentStatusEnum::CLOSED)
                                <x-menu-item icon="o-rocket-launch" link="{{ route('admin.tournaments.live-center', $tournament->id) }}" :title="__('Live Center')" />
                            @endif
                            @if ($this->canManage)
                                <livewire:admin.shared.event-post-button
                                    :model-class="\App\Domains\Competitions\Tournament\Models\Tournament::class"
                                    :model-id="$tournament->id"
                                    event-type="TOURNAMENT"
                                    icon="🏆"
                                    :event-date="$tournament->start_date->toDateString()"
                                    :start-time="$tournament->start_time ? \Carbon\Carbon::parse($tournament->start_time)->format('H:i:s') : '00:00:00'"
                                    :end-time="null"
                                    :price="(string) $tournament->price"
                                    :max-participants="$tournament->max_users ?: null"
                                    :default-title="$tournament->name"
                                    :can-publish="true"
                                    wire:key="ep-mob-tournament-{{ $tournament->id }}"
                                    @event-post-saved.window="$wire.refreshTournaments()" />
                            @endif
                        </x-admin.shared.row-menu>
                    @endif
                </div>
            </div>
        @empty
            @php
                $hasActiveFilters = count($filterChips) > 0 || filled($search);
                $emptyHeading = $search
                    ? __('No tournament matches ":search"', ['search' => $search])
                    : ($hasActiveFilters ? __('No tournaments match your filters') : __('No tournaments yet'));
            @endphp
            <x-admin.shared.list-empty-state
                icon="o-trophy"
                :heading="$emptyHeading"
                :filtered="$hasActiveFilters"
                :create-label="__('Create a tournament')"
                :create-href="$this->canManage ? route('admin.tournaments.wizard') : null" />
        @endforelse

        @if ($tournaments->hasPages())
            <div class="mt-2">
                {{ $tournaments->links() }}
            </div>
        @endif
    </div>

    {{-- ── Vue desktop (table) ────────────────────────────────────────── --}}
    <div class="hidden lg:block">
        <x-card>
            @if ($tournaments->isEmpty())
                @php
                    $hasActiveFilters = count($filterChips) > 0 || filled($search);
                    $emptyHeading = $search
                        ? __('No tournament matches ":search"', ['search' => $search])
                        : ($hasActiveFilters ? __('No tournaments match your filters') : __('No tournaments yet'));
                @endphp
                <x-admin.shared.list-empty-state
                    icon="o-trophy"
                    :heading="$emptyHeading"
                    :filtered="$hasActiveFilters"
                    :create-label="__('Create a tournament')"
                    :create-href="$this->canManage ? route('admin.tournaments.wizard') : null" />
            @else
                <x-table :headers="$headers" :rows="$tournaments" :sort-by="$sortBy"
                    selectable wire:model.live="selected">
                    @scope('cell_name', $tournament)
                        <span class="font-medium">{{ $tournament->name }}</span>
                    @endscope

                    @scope('cell_start_date', $tournament)
                        <span class="text-sm text-base-content/60">
                            {{ $tournament->hasKnownStartTime()
                                ? $tournament->startsAt()->translatedFormat('d M Y · H\hi')
                                : $tournament->start_date->translatedFormat('d M Y') }}
                        </span>
                    @endscope

                    @scope('cell_match_type', $tournament)
                        <x-badge
                            :value="$tournament->match_type === 'double' ? __('Doubles') : __('Singles')"
                            class="badge-ghost badge-sm" />
                    @endscope

                    @scope('cell_spots', $tournament)
                        @php
                            $active  = $tournament->active_registrations_count ?? 0;
                            $max     = $tournament->max_users;
                            $waiting = $tournament->waiting_count ?? 0;
                            $isFull  = $max > 0 && $active >= $max;
                        @endphp
                        <div class="min-w-24">
                            <span @class(['text-sm tabular-nums', 'font-semibold text-error' => $isFull])>
                                {{ $active }}@if ($max > 0) / {{ $max }}@endif
                            </span>
                            @if ($waiting > 0)
                                <span class="ml-1 text-xs text-warning-content">+{{ $waiting }}</span>
                            @endif

                            @if ($max > 0)
                                <div class="mt-1 h-1 overflow-hidden rounded-full bg-base-300">
                                    <div @class(['h-full rounded-full', 'bg-error' => $isFull, 'bg-primary' => ! $isFull])
                                        style="width: {{ min(100, (int) round($active / $max * 100)) }}%"></div>
                                </div>
                            @endif
                        </div>
                    @endscope

                    @scope('cell_status', $tournament)
                        {{-- Le libellé et la classe viennent de l'enum : le filtre et la
                             colonne nommaient le même statut de deux façons. --}}
                        <x-badge :value="$tournament->status->getLabel()"
                            :class="$tournament->status->badgeClass()" />
                    @endscope

                    @scope('cell_next_action', $tournament)
                        {{-- La règle vit dans TournamentNextActionService : la vue affiche. --}}
                        @php $next = $this->nextActionFor($tournament); @endphp

                        @if ($next === null)
                            <span class="text-sm text-base-content/30">—</span>
                        @elseif ($this->canManage)
                            <x-button :label="$next->label" :link="$next->url"
                                :class="$next->urgent ? 'btn-outline btn-xs border-warning/60 text-warning-content' : 'btn-outline btn-xs'" />
                        @else
                            <span class="text-sm text-base-content/50">{{ $next->label }}</span>
                        @endif
                    @endscope

                    @scope('actions', $tournament)
                        {{-- ?step=1 : l'icône réglages doit ouvrir la configuration. Sans étape
                             explicite, mount() la déduit du statut et ouvre les invitations. --}}
                        <x-admin.shared.row-menu
                            :label="__('Settings')"
                            icon="o-cog-6-tooth"
                            link="{{ route('admin.tournaments.wizard.edit', [$tournament, 'step' => 1]) }}">

                            @if ($tournament->status !== \App\Domains\Shared\Enums\TournamentStatusEnum::CLOSED)
                                <x-menu-item icon="o-rocket-launch"
                                    link="{{ route('admin.tournaments.live-center', $tournament->id) }}"
                                    :title="__('Live Center')" />
                            @endif

                            {{-- L'article vivait dans une colonne « Site web » que la colonne
                                 « Attend de vous » remplace : son entrée rejoint le menu de
                                 ligne, où la vue mobile la plaçait déjà. --}}
                            @if ($this->canManage)
                                <livewire:admin.shared.event-post-button
                                    :model-class="\App\Domains\Competitions\Tournament\Models\Tournament::class"
                                    :model-id="$tournament->id"
                                    event-type="TOURNAMENT"
                                    icon="🏆"
                                    :event-date="$tournament->start_date->toDateString()"
                                    :start-time="$tournament->start_time ? \Carbon\Carbon::parse($tournament->start_time)->format('H:i:s') : '00:00:00'"
                                    :end-time="null"
                                    :price="(string) $tournament->price"
                                    :max-participants="$tournament->max_users ?: null"
                                    :default-title="$tournament->name"
                                    :can-publish="true"
                                    wire:key="ep-desk-tournament-{{ $tournament->id }}"
                                    @event-post-saved.window="$wire.refreshTournaments()" />
                            @endif
                        </x-admin.shared.row-menu>
                    @endscope
                </x-table>
                <div class="mt-4">
                    {{ $tournaments->links() }}
                </div>
            @endif
        </x-card>
    </div>

    {{-- ── Floating Pill — bulk actions ───────────────────────────────── --}}
    @if ($this->canManage)
        <x-admin.shared.selection-pill
            :selected="$selected"
            :total="$this->getTotalMatchingCount()"
            :selecting-all-results="$selectingAllResults"
            :select-all="$selectAll">
            <x-slot:actions>
                <x-button class="btn-ghost btn-sm text-warning-content" icon="o-x-circle" :label="__('Cancel')"
                    wire:click="confirmBulkCancel" />
            </x-slot:actions>
        </x-admin.shared.selection-pill>
    @endif

    {{-- ── Filter drawer ────────────────────────────────────────────────────── --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">
                    {{ __('Status') }}
                </p>
                <x-select :options="$statusOptions" :placeholder="__('All statuses')"
                    wire:model.live="status" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">
                    {{ __('Type') }}
                </p>
                <x-select :options="$matchTypeOptions" :placeholder="__('Singles & Doubles')"
                    wire:model.live="matchType" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">
                    {{ __('Spots') }}
                </p>
                <x-select :options="$isFullOptions" :placeholder="__('All')"
                    wire:model.live="isFull" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">
                    {{ __('Website') }}
                </p>
                <x-select :options="$hasEventOptions" :placeholder="__('All')"
                    wire:model.live="hasEvent" class="w-full" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- ── Modal annulation bulk ──────────────────────────────────────── --}}
    <x-confirm-modal model="confirmBulkCancelModal" :title="__('Cancel selected tournaments?')"
        :confirmLabel="__('Confirm cancellation')" confirmAction="bulkCancel" :open="$confirmBulkCancelModal">
        <p>
            {{ trans_choice('selectedCount', count($selected), ['count' => count($selected)]) }}
            {{ __('will be marked as cancelled.') }}
        </p>
    </x-confirm-modal>

    {{-- ── Mobile action sheet ─────────────────────────────────────────── --}}
    <x-admin.shared.mobile-actions>
        @if ($this->canManage)
            <x-admin.shared.mobile-action-item
                icon="o-plus" color="primary"
                :label="__('Create')"
                :description="__('Create a new tournament')"
                @click="mobileActionsOpen = false; window.location.href = '{{ route('admin.tournaments.wizard') }}'" />
            <div class="my-1 h-px bg-base-200"></div>
            <x-admin.shared.mobile-action-item
                icon="o-check-circle" color="base"
                :label="__('Select')"
                :description="__('Bulk actions on multiple tournaments')"
                @click="mobileActionsOpen = false; $wire.call('toggleSelectionMode')" />
        @endif
    </x-admin.shared.mobile-actions>
</div>
