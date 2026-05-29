<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Events')">
        <x-slot:middle>
            <x-input class="w-full" clearable icon="o-magnifying-glass"
                :placeholder="__('Search...')"
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
                <x-select :options="$typeOptions" :placeholder="__('All types')"
                    wire:model.live="type" class="w-full" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-bar>

    {{-- ── Cartes stats ──────────────────────────────────────────────── --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @php
            $statCards = [
                ['label' => __('Total'),     'key' => 'total',     'icon' => 'o-calendar-days', 'bg' => 'bg-base-200',   'color' => 'text-base-content/60'],
                ['label' => __('Published'), 'key' => 'published', 'icon' => 'o-check-circle',  'bg' => 'bg-success/10', 'color' => 'text-success'],
                ['label' => __('Draft'),     'key' => 'draft',     'icon' => 'o-pencil-square',  'bg' => 'bg-warning/10', 'color' => 'text-warning'],
                ['label' => __('Archived'),  'key' => 'archived',  'icon' => 'o-archive-box',   'bg' => 'bg-base-200',   'color' => 'text-base-content/30'],
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
        @forelse ($events as $event)
            @php
                $statusBadge = match ($event->status) {
                    \App\Enums\EventPostStatusEnum::PUBLISHED => ['class' => 'badge-success badge-soft', 'label' => __('Published')],
                    \App\Enums\EventPostStatusEnum::DRAFT     => ['class' => 'badge-warning badge-soft', 'label' => __('Draft')],
                    \App\Enums\EventPostStatusEnum::ARCHIVED  => ['class' => 'badge-ghost',              'label' => __('Archived')],
                };
            @endphp
            <x-list-item :item="$event" class="bg-base-100 rounded-lg border"
                wire:key="mobile-event-{{ $event->id }}">
                <x-slot:value>
                    <span class="font-medium">{{ $event->title }}</span>
                </x-slot:value>
                <x-slot:sub-value>
                    <div class="mt-0.5 flex flex-wrap items-center gap-2">
                        <x-badge :value="$statusBadge['label']" class="{{ $statusBadge['class'] }} badge-sm" />
                        <span class="text-xs text-base-content/40">
                            {{ $event->type->getIcon() }} {{ $event->type->getLabel() }}
                        </span>
                        <span class="text-xs text-base-content/40">
                            {{ $event->event_date->translatedFormat('d M Y') }}
                        </span>
                    </div>
                </x-slot:sub-value>
                <x-slot:actions>
                    <x-admin.shared.row-actions>
                        @if ($event->status === \App\Enums\EventPostStatusEnum::DRAFT)
                            <x-button class="btn-ghost btn-sm btn-circle text-success" icon="o-check-circle"
                                :tooltip="__('Publish')"
                                wire:click="publish({{ $event->id }})" spinner />
                        @elseif ($event->status === \App\Enums\EventPostStatusEnum::PUBLISHED)
                            <x-button class="btn-ghost btn-sm btn-circle text-base-content/40" icon="o-archive-box"
                                :tooltip="__('Archive')"
                                wire:click="archive({{ $event->id }})" spinner />
                        @endif
                        <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil"
                            :tooltip="__('Edit')"
                            wire:click="openEdit({{ $event->id }})" />
                        @if ($event->canBeDeleted())
                            <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-trash"
                                :tooltip="__('Delete')"
                                wire:click="confirmDelete({{ $event->id }})" />
                        @endif
                    </x-admin.shared.row-actions>
                </x-slot:actions>
            </x-list-item>
        @empty
            <x-empty-state
                icon="o-calendar-days"
                :heading="__('No events found')"
                :message="__('Try adjusting your search or filters.')" />
        @endforelse
    </div>

    {{-- ── Vue desktop (table) ────────────────────────────────────────── --}}
    <div class="hidden lg:block">
        <x-card>
            @if ($events->isEmpty())
                <x-empty-state
                    icon="o-calendar-days"
                    :heading="__('No events found')"
                    :message="__('Try adjusting your search or filters.')" />
            @else
                <x-table :headers="$headers" :rows="$events" :sort-by="$sortBy">
                    @scope('cell_type', $event)
                        <span class="inline-flex items-center gap-1.5 text-sm">
                            <span>{{ $event->type->getIcon() }}</span>
                            <span class="text-base-content/60">{{ $event->type->getLabel() }}</span>
                        </span>
                    @endscope

                    @scope('cell_title', $event)
                        <div>
                            <span class="font-medium">{{ $event->title }}</span>
                            @if ($event->featured)
                                <x-icon name="o-star" class="ml-1 inline h-3.5 w-3.5 text-warning" />
                            @endif
                        </div>
                    @endscope

                    @scope('cell_event_date', $event)
                        <div class="text-sm text-base-content/60">
                            <div>{{ $event->event_date->translatedFormat('d M Y') }}</div>
                            <div class="text-xs opacity-60">{{ $event->start_time?->format('H:i') }}</div>
                        </div>
                    @endscope

                    @scope('cell_eventable', $event)
                        <span class="text-sm text-base-content/60">
                            {{ $event->eventable?->name ?? '—' }}
                        </span>
                    @endscope

                    @scope('cell_status', $event)
                        @php
                            $s = match ($event->status) {
                                \App\Enums\EventPostStatusEnum::PUBLISHED => ['class' => 'badge-success badge-soft', 'label' => __('Published')],
                                \App\Enums\EventPostStatusEnum::DRAFT     => ['class' => 'badge-warning badge-soft', 'label' => __('Draft')],
                                \App\Enums\EventPostStatusEnum::ARCHIVED  => ['class' => 'badge-ghost',              'label' => __('Archived')],
                            };
                        @endphp
                        <x-badge :value="$s['label']" class="{{ $s['class'] }}" />
                    @endscope

                    @scope('cell_featured', $event)
                        @if ($event->featured)
                            @if ($event->featured_until && $event->featured_until->isPast())
                                <span class="text-xs text-base-content/30 italic">{{ __('Expired') }}</span>
                            @else
                                <x-icon name="o-star" class="h-4 w-4 text-warning" />
                                @if ($event->featured_until)
                                    <span class="ml-1 text-xs text-base-content/40">
                                        {{ __('until') }} {{ $event->featured_until->translatedFormat('d M') }}
                                    </span>
                                @endif
                            @endif
                        @else
                            <span class="text-base-content/20">—</span>
                        @endif
                    @endscope

                    @scope('actions', $event)
                        <x-admin.shared.row-actions>
                            @if ($event->status === \App\Enums\EventPostStatusEnum::DRAFT)
                                <x-button class="btn-ghost btn-sm btn-circle text-success" icon="o-check-circle"
                                    :tooltip="__('Publish')"
                                    wire:click="publish({{ $event->id }})" spinner />
                            @elseif ($event->status === \App\Enums\EventPostStatusEnum::PUBLISHED)
                                <x-button class="btn-ghost btn-sm btn-circle text-base-content/40" icon="o-archive-box"
                                    :tooltip="__('Archive')"
                                    wire:click="archive({{ $event->id }})" spinner />
                            @endif
                            <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil"
                                :tooltip="__('Edit')"
                                wire:click="openEdit({{ $event->id }})" />
                            @if ($event->canBeDeleted())
                                <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-trash"
                                    :tooltip="__('Delete')"
                                    wire:click="confirmDelete({{ $event->id }})" />
                            @endif
                        </x-admin.shared.row-actions>
                    @endscope
                </x-table>
                <div class="mt-4">
                    {{ $events->links() }}
                </div>
            @endif
        </x-card>
    </div>

    {{-- ── Drawer édition ───────────────────────────────────────────────── --}}
    <x-drawer wire:model="editDrawer" :title="__('Edit event')" right with-close-button class="w-full max-w-lg">
        @if ($selectedEvent)
            <div class="space-y-5 p-1">

                {{-- Entité liée (lecture seule) --}}
                <div class="flex items-center gap-2 rounded-lg bg-base-200 px-3 py-2 text-sm">
                    <span>{{ $selectedEvent->type->getIcon() }}</span>
                    <span class="font-medium">{{ $selectedEvent->type->getLabel() }}</span>
                    <span class="text-base-content/40">·</span>
                    <span class="text-base-content/60">{{ $selectedEvent->eventable?->name ?? '—' }}</span>
                </div>

                {{-- Statut --}}
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                        {{ __('Status') }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ([
                            ['DRAFT',     __('Draft'),     'btn-warning'],
                            ['PUBLISHED', __('Published'), 'btn-success'],
                            ['ARCHIVED',  __('Archived'),  'btn-ghost'],
                        ] as [$val, $label, $cls])
                            <x-button class="btn-sm btn-soft {{ $cls }} {{ $editStatus === $val ? 'opacity-100' : 'opacity-40' }}"
                                :label="$label"
                                wire:click="$set('editStatus', '{{ $val }}')" />
                        @endforeach
                    </div>
                </div>

                {{-- Featured --}}
                <div class="space-y-3">
                    <x-toggle wire:model.live="editFeatured" :label="__('Featured on website')" />
                    @if ($editFeatured)
                        <x-input wire:model="editFeaturedUntil"
                            :label="__('Featured until')"
                            type="date"
                            min="{{ now()->format('Y-m-d') }}"
                            :hint="__('Leave empty to feature indefinitely')" />
                    @endif
                </div>

                {{-- Contenu --}}
                <x-input wire:model="editTitle" :label="__('Title')" />
                <x-textarea wire:model="editDescription" :label="__('Description')" rows="4" />

                {{-- Logistique --}}
                <x-input wire:model="editLocation" :label="__('Location')" icon="o-map-pin" />

                <div class="grid grid-cols-2 gap-3">
                    <x-input wire:model="editEventDate" :label="__('Date')" type="date" />
                    <x-input wire:model="editStartTime" :label="__('Start time')" type="time" />
                </div>
                <x-input wire:model="editEndTime" :label="__('End time')" type="time"
                    :hint="__('Optional')" />
                <x-input wire:model="editPrice" :label="__('Price')"
                    :hint="__('Leave empty if free')" icon="o-banknotes" />

                {{-- Optionnel --}}
                <div class="grid grid-cols-3 gap-3">
                    <x-input wire:model="editIcon" :label="__('Icon')" :hint="__('Emoji')" />
                    <div class="col-span-2">
                        <x-textarea wire:model="editNotes" :label="__('Notes')" rows="2" />
                    </div>
                </div>

            </div>
        @endif

        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('editDrawer', false)" />
            <x-button class="btn-primary" :label="__('Save')" wire:click="saveEdit" spinner />
        </x-slot:actions>
    </x-drawer>

    {{-- ── Modal suppression ────────────────────────────────────────────── --}}
    <x-confirm-modal model="deleteModal" :title="__('Delete this event?')"
        :confirmLabel="__('Delete')" confirmAction="delete">
        <p>{{ __('This action is irreversible.') }}</p>
    </x-confirm-modal>
</div>
