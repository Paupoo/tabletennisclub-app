<div x-data="{ mobileSearchOpen: false, mobileActionsOpen: false }">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Events')">
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

    {{-- ── Cartes stats ──────────────────────────────────────────────── --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @php
            $statCards = [
                ['label' => __('Total'),     'key' => 'total',     'icon' => 'o-calendar-days', 'bg' => 'bg-base-200',   'color' => 'text-base-content/60'],
                ['label' => __('Published'), 'key' => 'published', 'icon' => 'o-check-circle',  'bg' => 'bg-success/10', 'color' => 'text-success'],
                ['label' => __('Draft'),     'key' => 'draft',     'icon' => 'o-pencil-square',  'bg' => 'bg-warning/10', 'color' => 'text-warning-content'],
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
                    \App\Domains\Shared\Enums\EventPostStatusEnum::PUBLISHED => ['class' => 'badge-success badge-soft', 'label' => __('Published')],
                    \App\Domains\Shared\Enums\EventPostStatusEnum::DRAFT     => ['class' => 'badge-warning badge-soft', 'label' => __('Draft')],
                    \App\Domains\Shared\Enums\EventPostStatusEnum::ARCHIVED  => ['class' => 'badge-ghost',              'label' => __('Archived')],
                };
            @endphp
            <x-list-item :item="$event" class="bg-base-100 rounded-lg border"
                wire:key="mobile-event-{{ $event->id }}">
                <x-slot:avatar>
                    @if ($selectionModeActive)
                        <input type="checkbox"
                            class="checkbox checkbox-primary checkbox-sm"
                            value="{{ $event->id }}"
                            wire:model.live="selected" />
                    @endif
                </x-slot:avatar>
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
                    @if (! $selectionModeActive)
                        <x-admin.shared.row-actions>
                            @if ($event->status === \App\Domains\Shared\Enums\EventPostStatusEnum::DRAFT)
                                <x-button class="btn-ghost btn-sm btn-circle text-success" icon="o-check-circle"
                                    :tooltip="__('Publish')"
                                    wire:click="publish({{ $event->id }})" spinner />
                            @elseif ($event->status === \App\Domains\Shared\Enums\EventPostStatusEnum::PUBLISHED)
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
                    @endif
                </x-slot:actions>
            </x-list-item>
        @empty
            <x-admin.shared.list-empty-state
                icon="o-calendar-days"
                :filtered="filled($search) || count($filterChips) > 0"
                :heading="__('No events found')" />
        @endforelse
    </div>

    {{-- ── Vue desktop (table) ────────────────────────────────────────── --}}
    <div class="hidden lg:block">
        <x-card>
            @if ($events->isEmpty())
                <x-admin.shared.list-empty-state
                    icon="o-calendar-days"
                    :filtered="filled($search) || count($filterChips) > 0"
                    :heading="__('No events found')" />
            @else
                <x-table :headers="$headers" :rows="$events" :sort-by="$sortBy"
                    selectable wire:model.live="selected">
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
                                <x-icon name="o-star" class="ml-1 inline h-3.5 w-3.5 text-warning-content" />
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
                                \App\Domains\Shared\Enums\EventPostStatusEnum::PUBLISHED => ['class' => 'badge-success badge-soft', 'label' => __('Published')],
                                \App\Domains\Shared\Enums\EventPostStatusEnum::DRAFT     => ['class' => 'badge-warning badge-soft', 'label' => __('Draft')],
                                \App\Domains\Shared\Enums\EventPostStatusEnum::ARCHIVED  => ['class' => 'badge-ghost',              'label' => __('Archived')],
                            };
                        @endphp
                        <x-badge :value="$s['label']" class="{{ $s['class'] }}" />
                    @endscope

                    @scope('cell_featured', $event)
                        @if ($event->featured)
                            @if ($event->featured_until && $event->featured_until->isPast())
                                <span class="text-xs text-base-content/30 italic">{{ __('Expired') }}</span>
                            @else
                                <x-icon name="o-star" class="h-4 w-4 text-warning-content" />
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
                            @if ($event->status === \App\Domains\Shared\Enums\EventPostStatusEnum::DRAFT)
                                <x-button class="btn-ghost btn-sm btn-circle text-success" icon="o-check-circle"
                                    :tooltip="__('Publish')"
                                    wire:click="publish({{ $event->id }})" spinner />
                            @elseif ($event->status === \App\Domains\Shared\Enums\EventPostStatusEnum::PUBLISHED)
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

    {{-- ── Floating Pill — bulk actions ───────────────────────────────── --}}
    <x-admin.shared.selection-pill
        :selected="$selected"
        :total="$this->getTotalMatchingCount()"
        :selecting-all-results="$selectingAllResults"
        :select-all="$selectAll">
        <x-slot:actions>
            <x-button class="btn-ghost btn-sm" icon="o-check-circle" :label="__('Publish')"
                wire:click="bulkPublish" spinner="bulkPublish" />
            <span class="text-base-content/20">|</span>
            <x-button class="btn-ghost btn-sm text-warning-content" icon="o-archive-box" :label="__('Archive')"
                wire:click="confirmBulkArchive" />
        </x-slot:actions>
    </x-admin.shared.selection-pill>

    {{-- ── Filter drawer ────────────────────────────────────────────────────── --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
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
    </x-admin.shared.filter-drawer>

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

    {{-- ── Modal suppression unitaire ───────────────────────────────── --}}
    <x-confirm-modal model="deleteModal" :title="__('Delete this event?')"
        :confirmLabel="__('Delete')" confirmAction="delete">
        <p>{{ __('This action is irreversible.') }}</p>
    </x-confirm-modal>

    {{-- ── Modal archivage bulk ──────────────────────────────────────── --}}
    <x-confirm-modal model="confirmBulkArchiveModal" :title="__('Archive selected events?')"
        :confirmLabel="__('Archive')" confirmAction="bulkArchive">
        <p>
            {{ trans_choice('selectedCount', count($selected), ['count' => count($selected)]) }}
            {{ __('will be archived.') }}
        </p>
    </x-confirm-modal>

    {{-- ── Mobile action sheet ─────────────────────────────────────────── --}}
    <x-admin.shared.mobile-actions>
        <x-admin.shared.mobile-action-item
            icon="o-check-circle" color="base"
            :label="__('Select')"
            :description="__('Bulk actions on multiple events')"
            @click="mobileActionsOpen = false; $wire.call('toggleSelectionMode')" />
    </x-admin.shared.mobile-actions>
</div>
