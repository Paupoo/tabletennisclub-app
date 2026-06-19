<div x-data="{ mobileSearchOpen: false, mobileActionsOpen: false }">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Meetings')">
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
                    <x-button class="btn-primary btn-sm" icon="o-plus" :label="__('New meeting')"
                        link="{{ route('admin.meetings.create') }}" />
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
                    placeholder="{{ __('Search…') }}" />
            </div>
            <button @click="mobileSearchOpen = false" class="btn btn-ghost btn-circle btn-sm">
                <x-icon name="o-x-mark" class="h-5 w-5" />
            </button>
        </div>
    </div>

    {{-- ── Active filter chips ──────────────────────────────────────────────── --}}
    <x-admin.shared.filter-chips :chips="$filterChips" />

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
        $hasActiveFilters = count($filterChips) > 0 || filled($search);
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
                <x-slot:avatar>
                    @if ($selectionModeActive && $this->canManage)
                        <input type="checkbox"
                            class="checkbox checkbox-primary checkbox-sm"
                            value="{{ $meeting->id }}"
                            wire:model.live="selected" />
                    @endif
                </x-slot:avatar>
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
                    @if (! $selectionModeActive)
                        <x-admin.shared.row-actions>
                            <x-button class="btn-ghost btn-sm btn-circle" icon="o-eye"
                                :tooltip="__('View')"
                                link="{{ route('admin.meetings.show', $meeting) }}" />
                            @if ($this->canManage)
                                <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil-square"
                                    :tooltip="__('Edit')"
                                    link="{{ route('admin.meetings.edit', $meeting) }}" />
                                <livewire:admin.shared.event-post-button
                                    :model-class="\App\Domains\Meetings\Models\Meeting::class"
                                    :model-id="$meeting->id"
                                    event-type="MEETING"
                                    icon="📋"
                                    :event-date="$meeting->scheduled_at?->toDateString()"
                                    :start-time="$meeting->scheduled_at?->format('H:i:s')"
                                    :end-time="$meeting->ends_at?->format('H:i:s')"
                                    :default-title="$meeting->title"
                                    :can-publish="$meeting->scheduled_at !== null"
                                    :cannot-publish-reason="__('Set a confirmed date before publishing')"
                                    wire:key="ep-mob-meeting-{{ $meeting->id }}"
                                    @event-post-saved.window="$wire.refreshMeetings()" />
                            @endif
                        </x-admin.shared.row-actions>
                    @endif
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
                <x-table :headers="$headers" :rows="$meetings" :sort-by="$sortBy"
                    selectable wire:model.live="selected">
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

                    @scope('cell_event', $meeting)
                        @if ($this->canManage)
                            <livewire:admin.shared.event-post-button
                                :model-class="\App\Domains\Meetings\Models\Meeting::class"
                                :model-id="$meeting->id"
                                event-type="MEETING"
                                icon="📋"
                                :event-date="$meeting->scheduled_at?->toDateString()"
                                :start-time="$meeting->scheduled_at?->format('H:i:s')"
                                :end-time="$meeting->ends_at?->format('H:i:s')"
                                :default-title="$meeting->title"
                                :can-publish="$meeting->scheduled_at !== null"
                                :cannot-publish-reason="__('Set a confirmed date before publishing')"
                                wire:key="ep-desk-meeting-{{ $meeting->id }}"
                                @event-post-saved.window="$wire.refreshMeetings()" />
                        @endif
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

    {{-- ── Floating Pill — bulk actions ───────────────────────────────── --}}
    @if ($this->canManage)
        <x-admin.shared.selection-pill
            :selected="$selected"
            :total="$this->getTotalMatchingCount()"
            :selecting-all-results="$selectingAllResults"
            :select-all="$selectAll">
            <x-slot:actions>
                @if ($showArchived)
                    <x-button class="btn-ghost btn-sm text-success" icon="o-arrow-uturn-left"
                        :label="__('Restore')" wire:click="bulkUnarchive" />
                @else
                    <x-button class="btn-ghost btn-sm text-warning-content" icon="o-x-circle" :label="__('Cancel')"
                        wire:click="confirmBulkCancel" />
                    <x-button class="btn-ghost btn-sm" icon="o-archive-box"
                        :label="__('Archive')" wire:click="bulkArchive" />
                    <x-button class="btn-ghost btn-sm text-error" icon="o-trash"
                        :label="__('Delete')" wire:click="confirmBulkDelete" />
                @endif
            </x-slot:actions>
        </x-admin.shared.selection-pill>
    @endif

    {{-- ── Filter drawer ────────────────────────────────────────────────────── --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
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
            <div class="border-t border-base-200 pt-4">
                <x-toggle wire:model.live="showArchived" :label="__('Show archived meetings')" right />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- ── Modal annulation bulk ──────────────────────────────────────── --}}
    <x-confirm-modal model="confirmBulkCancelModal" :title="__('Cancel selected meetings?')"
        :confirmLabel="__('Confirm cancellation')" confirmAction="bulkCancel">
        <p>
            {{ trans_choice('selectedCount', count($selected), ['count' => count($selected)]) }}
            {{ __('will be marked as cancelled.') }}
        </p>
    </x-confirm-modal>

    {{-- ── Modal suppression bulk ─────────────────────────────────────── --}}
    <x-confirm-modal model="confirmBulkDeleteModal" :title="__('Delete selected meetings?')"
        :confirmLabel="__('Confirm deletion')" confirmAction="bulkDelete">
        <p>
            {{ __('Only meetings that have not taken place and have no invitations sent will be deleted. This action is irreversible.') }}
        </p>
    </x-confirm-modal>

    {{-- ── Mobile action sheet ─────────────────────────────────────────── --}}
    <x-admin.shared.mobile-actions>
        @if ($this->canManage)
            <x-admin.shared.mobile-action-item
                icon="o-plus" color="primary"
                :label="__('New meeting')"
                :description="__('Create a new meeting')"
                @click="mobileActionsOpen = false; window.location.href = '{{ route('admin.meetings.create') }}'" />
            <div class="my-1 h-px bg-base-200"></div>
            <x-admin.shared.mobile-action-item
                icon="o-check-circle" color="base"
                :label="__('Select')"
                :description="__('Bulk actions on multiple meetings')"
                @click="mobileActionsOpen = false; $wire.call('toggleSelectionMode')" />
        @endif
    </x-admin.shared.mobile-actions>
</div>
