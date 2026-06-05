<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Tournaments')">
        <x-slot:middle>
            <x-input class="w-full" clearable icon="o-magnifying-glass"
                :placeholder="__('Search…')"
                wire:model.live.debounce.300ms="search" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button class="btn-ghost {{ $this->activeFiltersCount > 0 ? 'btn-active' : '' }}"
                wire:click="$toggle('showFilters')">
                <x-icon name="o-funnel" class="h-5 w-5" />
                {{ __('Filters') }}
                @if ($this->activeFiltersCount > 0)
                    <x-badge class="badge-sm badge-primary" value="{{ $this->activeFiltersCount }}" />
                @endif
            </x-button>
            @if ($this->canManage)
                <x-button class="btn-primary" icon="o-plus" :label="__('Create')"
                    link="{{ route('admin.tournaments.wizard') }}" responsive />
            @endif
        </x-slot:actions>
    </x-header>

    {{-- ── Filtres ────────────────────────────────────────────────────── --}}
    <x-admin.shared.filter-bar :active-filters-count="$this->activeFiltersCount" :show="$showFilters">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Status') }}
                </p>
                <x-select :options="$statusOptions" :placeholder="__('All statuses')"
                    wire:model.live="status" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Type') }}
                </p>
                <x-select :options="$matchTypeOptions" :placeholder="__('Singles & Doubles')"
                    wire:model.live="matchType" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Spots') }}
                </p>
                <x-select :options="$isFullOptions" :placeholder="__('All')"
                    wire:model.live="isFull" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Website') }}
                </p>
                <x-select :options="$hasEventOptions" :placeholder="__('All')"
                    wire:model.live="hasEvent" class="w-full" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-bar>

    {{-- ── Cartes stats ──────────────────────────────────────────────── --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @php
            $statCards = [
                ['label' => __('Total'),    'key' => 'total',    'icon' => 'o-trophy',        'bg' => 'bg-base-200',   'color' => 'text-base-content/60'],
                ['label' => __('Live'),     'key' => 'live',     'icon' => 'o-rocket-launch', 'bg' => 'bg-primary/10', 'color' => 'text-primary'],
                ['label' => __('Upcoming'), 'key' => 'upcoming', 'icon' => 'o-calendar',      'bg' => 'bg-info/10',    'color' => 'text-info'],
                ['label' => __('Closed'),   'key' => 'closed',   'icon' => 'o-check-circle',  'bg' => 'bg-base-200',   'color' => 'text-base-content/40'],
            ];
        @endphp
        @foreach ($statCards as $card)
            <x-card class="shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $card['bg'] }}">
                        <x-icon name="{{ $card['icon'] }}" class="h-5 w-5 {{ $card['color'] }}" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold {{ $card['color'] }}">{{ $stats[$card['key']] ?? 0 }}</p>
                        <p class="text-xs text-base-content/40">{{ $card['label'] }}</p>
                    </div>
                </div>
            </x-card>
        @endforeach
    </div>

    {{-- ── Vue mobile (list) ─────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 lg:hidden">
        @forelse ($tournaments as $tournament)
            @php
                $statusBadge = match ($tournament->status->value) {
                    'pending'   => ['class' => 'badge-primary badge-soft',  'label' => __('Live')],
                    'published' => ['class' => 'badge-success badge-soft',  'label' => __('Published')],
                    'setup'     => ['class' => 'badge-info badge-soft',     'label' => __('Setup')],
                    'locked'    => ['class' => 'badge-warning badge-soft',  'label' => __('Locked')],
                    'closed'    => ['class' => 'badge-ghost',               'label' => __('Closed')],
                    'cancelled' => ['class' => 'badge-error badge-soft',    'label' => __('Cancelled')],
                    default     => ['class' => 'badge-outline',             'label' => __('Draft')],
                };
            @endphp
            <x-list-item :item="$tournament" class="bg-base-100 rounded-lg border"
                wire:key="mobile-tournament-{{ $tournament->id }}">
                <x-slot:value>
                    <span class="font-medium">{{ $tournament->name }}</span>
                </x-slot:value>
                <x-slot:sub-value>
                    <div class="mt-0.5 flex flex-wrap items-center gap-2">
                        <x-badge :value="$statusBadge['label']" class="{{ $statusBadge['class'] }} badge-sm" />
                        <span class="text-xs text-base-content/40">
                            {{ $tournament->start_date->translatedFormat('d M Y') }}
                        </span>
                        <span class="text-xs text-base-content/40">
                            {{ $tournament->match_type === 'double' ? __('Doubles') : __('Singles') }}
                        </span>
                    </div>
                </x-slot:sub-value>
                <x-slot:actions>
                    <x-admin.shared.row-actions>
                        @if ($this->canManage)
                            <x-button class="btn-ghost btn-sm btn-circle" icon="o-cog-6-tooth"
                                :tooltip="__('Settings')"
                                link="{{ route('admin.tournaments.wizard.edit', $tournament) }}" />
                        @endif
                        @if ($tournament->status !== \App\Domains\Shared\Enums\TournamentStatusEnum::CLOSED)
                            <x-button class="btn-ghost btn-sm btn-circle" icon="o-rocket-launch"
                                :tooltip="__('Live Center')"
                                link="{{ route('admin.tournaments.live-center', $tournament->id) }}" />
                        @endif
                    </x-admin.shared.row-actions>
                </x-slot:actions>
            </x-list-item>
        @empty
            @php
                $emptyHeading = $search
                    ? __('No tournament matches ":search"', ['search' => $search])
                    : __('No tournaments yet');
                $emptyMessage = ! $search && $this->canManage
                    ? ''
                    : __('Try adjusting your search or filters.');
            @endphp
            <x-empty-state icon="o-trophy" :heading="$emptyHeading" :message="$emptyMessage" />
        @endforelse
    </div>

    {{-- ── Vue desktop (table) ────────────────────────────────────────── --}}
    <div class="hidden lg:block">
        <x-card>
            @if ($tournaments->isEmpty())
                @php
                    $emptyHeading = $search
                        ? __('No tournament matches ":search"', ['search' => $search])
                        : __('No tournaments yet');
                    $emptyMessage = ! $search && $this->canManage
                        ? ''
                        : __('Try adjusting your search or filters.');
                @endphp
                <x-empty-state icon="o-trophy" :heading="$emptyHeading" :message="$emptyMessage" />
            @else
                <x-table :headers="$headers" :rows="$tournaments" :sort-by="$sortBy">
                    @scope('cell_name', $tournament)
                        <span class="font-medium">{{ $tournament->name }}</span>
                    @endscope

                    @scope('cell_start_date', $tournament)
                        <span class="text-sm text-base-content/60">
                            {{ $tournament->start_date->translatedFormat('d M Y · H\hi') }}
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
                        <span @class(['text-sm', 'font-semibold text-error' => $isFull])>
                            {{ $active }}@if ($max > 0) / {{ $max }}@endif
                        </span>
                        @if ($waiting > 0)
                            <span class="ml-1 text-xs text-warning">(+{{ $waiting }})</span>
                        @endif
                    @endscope

                    @scope('cell_status', $tournament)
                        @php
                            $s = match ($tournament->status->value) {
                                'pending'   => ['class' => 'badge-primary badge-soft',  'label' => __('Live')],
                                'published' => ['class' => 'badge-success badge-soft',  'label' => __('Published')],
                                'setup'     => ['class' => 'badge-info badge-soft',     'label' => __('Setup')],
                                'locked'    => ['class' => 'badge-warning badge-soft',  'label' => __('Locked')],
                                'closed'    => ['class' => 'badge-ghost',               'label' => __('Closed')],
                                'cancelled' => ['class' => 'badge-error badge-soft',    'label' => __('Cancelled')],
                                default     => ['class' => 'badge-outline',             'label' => __('Draft')],
                            };
                        @endphp
                        <x-badge :value="$s['label']" class="{{ $s['class'] }}" />
                    @endscope

                    @scope('cell_event', $tournament)
                        @if ($tournament->eventPost?->status === \App\Domains\Shared\Enums\EventPostStatusEnum::PUBLISHED)
                            <x-icon name="o-check-circle" class="h-4 w-4 text-success" />
                        @else
                            <span class="text-base-content/30">—</span>
                        @endif
                    @endscope

                    @scope('actions', $tournament)
                        <x-admin.shared.row-actions>
                            @if ($this->canManage)
                                <x-button class="btn-ghost btn-sm btn-circle" icon="o-cog-6-tooth"
                                    :tooltip="__('Settings')"
                                    link="{{ route('admin.tournaments.wizard.edit', $tournament) }}" />
                            @endif
                            @if ($tournament->status !== \App\Domains\Shared\Enums\TournamentStatusEnum::CLOSED)
                                <x-button class="btn-ghost btn-sm btn-circle" icon="o-rocket-launch"
                                    :tooltip="__('Live Center')"
                                    link="{{ route('admin.tournaments.live-center', $tournament->id) }}" />
                            @endif
                        </x-admin.shared.row-actions>
                    @endscope
                </x-table>
                <div class="mt-4">
                    {{ $tournaments->links() }}
                </div>
            @endif
        </x-card>
    </div>
</div>
