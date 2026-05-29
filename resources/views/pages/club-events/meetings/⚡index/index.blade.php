<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Meetings')">
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
                <x-button class="btn-primary" icon="o-plus" :label="__('New meeting')"
                    link="{{ route('admin.meetings.create') }}" responsive />
            @endif
        </x-slot:actions>
    </x-header>

    {{-- ── Filtres ────────────────────────────────────────────────────── --}}
    <x-admin.shared.filter-bar :active-filters-count="$this->activeFiltersCount" :show="$showFilters">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">{{ __('Type') }}</p>
                <x-select :options="$typeOptions" :placeholder="__('All types')"
                    wire:model.live="type" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">{{ __('Status') }}</p>
                <x-select :options="$statusOptions" :placeholder="__('All statuses')"
                    wire:model.live="status" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">{{ __('Format') }}</p>
                <x-select :options="$formatOptions" :placeholder="__('All formats')"
                    wire:model.live="format" class="w-full" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-bar>

    {{-- ── Stat cards ────────────────────────────────────────────────── --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @php
            $statCards = [
                ['label' => __('Total'),     'key' => 'total',     'icon' => 'o-calendar-days',  'bg' => 'bg-base-200',   'color' => 'text-base-content/60'],
                ['label' => __('Upcoming'),  'key' => 'upcoming',  'icon' => 'o-clock',          'bg' => 'bg-success/10', 'color' => 'text-success'],
                ['label' => __('Planning'),  'key' => 'planning',  'icon' => 'o-pencil-square',  'bg' => 'bg-info/10',    'color' => 'text-info'],
                ['label' => __('Completed'), 'key' => 'completed', 'icon' => 'o-check-circle',   'bg' => 'bg-base-200',   'color' => 'text-base-content/40'],
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

    {{-- ── Mobile list ───────────────────────────────────────────────── --}}
    @php
        $hasActiveFilters = $this->activeFiltersCount > 0 || filled($search);
        $emptyHeading = $search
            ? __('No meeting matches ":search"', ['search' => $search])
            : ($hasActiveFilters ? __('No meetings match your filters') : __('No meetings yet'));
        $emptyMessage = $hasActiveFilters
            ? __('Try adjusting your search or filters.')
            : ($this->canManage ? '' : __('No meetings have been created yet.'));
    @endphp
    <div class="grid grid-cols-1 gap-3 lg:hidden">
        @forelse ($meetings as $meeting)
            <x-list-item :item="$meeting" class="bg-base-100 rounded-lg border"
                wire:key="mob-meeting-{{ $meeting->id }}">
                <x-slot:value>
                    <span class="font-medium">{{ $meeting->title }}</span>
                </x-slot:value>
                <x-slot:sub-value>
                    <div class="mt-0.5 flex flex-wrap items-center gap-2">
                        <x-badge :value="$meeting->type->getLabel()" class="badge-ghost badge-sm" />
                        <x-badge :value="$meeting->status->getLabel()"
                            class="{{ $meeting->status->getBadgeClass() }} badge-sm" />
                        @if ($meeting->scheduled_at)
                            <span class="text-xs text-base-content/40">
                                {{ $meeting->scheduled_at->translatedFormat('d M Y · H\hi') }}
                            </span>
                        @else
                            <span class="text-xs text-base-content/30">{{ __('Date TBD') }}</span>
                        @endif
                    </div>
                </x-slot:sub-value>
                <x-slot:actions>
                    <x-admin.shared.row-actions>
                        <x-button class="btn-ghost btn-sm btn-circle" icon="o-eye"
                            :tooltip="__('View')"
                            link="{{ route('admin.meetings.show', $meeting) }}" />
                        @if ($this->canManage)
                            <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil-square"
                                :tooltip="__('Edit')"
                                link="{{ route('admin.meetings.edit', $meeting) }}" />
                        @endif
                    </x-admin.shared.row-actions>
                </x-slot:actions>
            </x-list-item>
        @empty
            <x-empty-state icon="o-calendar-days"
                :heading="$emptyHeading"
                :message="$emptyMessage" />
        @endforelse
    </div>

    {{-- ── Desktop table ─────────────────────────────────────────────── --}}
    <div class="hidden lg:block">
        <x-card>
            @if ($meetings->isEmpty())
                <x-empty-state icon="o-calendar-days"
                    :heading="$emptyHeading"
                    :message="$emptyMessage" />
            @else
                <x-table :headers="$headers" :rows="$meetings" :sort-by="$sortBy">
                    @scope('cell_title', $meeting)
                        <div class="flex items-center gap-2">
                            <x-icon name="{{ $meeting->type->getIcon() }}" class="h-4 w-4 text-base-content/40 shrink-0" />
                            <span class="font-medium">{{ $meeting->title }}</span>
                        </div>
                    @endscope

                    @scope('cell_type', $meeting)
                        <x-badge :value="$meeting->type->getLabel()" class="badge-ghost badge-sm" />
                    @endscope

                    @scope('cell_scheduled_at', $meeting)
                        @if ($meeting->scheduled_at)
                            <span class="text-sm text-base-content/60">
                                {{ $meeting->scheduled_at->translatedFormat('d M Y · H\hi') }}
                            </span>
                        @else
                            <span class="text-sm text-base-content/30 italic">{{ __('Date TBD') }}</span>
                        @endif
                    @endscope

                    @scope('cell_format', $meeting)
                        <div class="flex items-center gap-1.5 text-sm text-base-content/60">
                            <x-icon name="{{ $meeting->format->getIcon() }}" class="h-4 w-4" />
                            {{ $meeting->format->getLabel() }}
                        </div>
                    @endscope

                    @scope('cell_participants', $meeting)
                        @php
                            $count = $meeting->confirmed_count ?? 0;
                            $quorum = $meeting->quorum;
                        @endphp
                        <span class="text-sm {{ $quorum && $count >= $quorum ? 'text-success font-semibold' : '' }}">
                            {{ $count }}
                            @if ($quorum)
                                <span class="text-base-content/40">/ {{ $quorum }}</span>
                            @endif
                        </span>
                    @endscope

                    @scope('cell_status', $meeting)
                        <x-badge :value="$meeting->status->getLabel()"
                            class="{{ $meeting->status->getBadgeClass() }}" />
                    @endscope

                    @scope('actions', $meeting)
                        <x-admin.shared.row-actions>
                            <x-button class="btn-ghost btn-sm btn-circle" icon="o-eye"
                                :tooltip="__('View')"
                                link="{{ route('admin.meetings.show', $meeting) }}" />
                            @if ($this->canManage)
                                <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil-square"
                                    :tooltip="__('Edit')"
                                    link="{{ route('admin.meetings.edit', $meeting) }}" />
                            @endif
                        </x-admin.shared.row-actions>
                    @endscope
                </x-table>
                <div class="mt-4">{{ $meetings->links() }}</div>
            @endif
        </x-card>
    </div>
</div>
