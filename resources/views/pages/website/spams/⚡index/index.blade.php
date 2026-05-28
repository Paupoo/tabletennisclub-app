<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator title="Spam">
        <x-slot:middle>
            <x-input class="w-full" clearable icon="o-magnifying-glass"
                :placeholder="__('Search by IP or user agent…')"
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
                    {{ __('Period') }}
                </p>
                <x-select :options="$periodOptions" :placeholder="__('All periods')"
                    wire:model.live="period" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Type') }}
                </p>
                <x-select :options="$userAgentOptions" :placeholder="__('All types')"
                    wire:model.live="userAgentType" class="w-full" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-bar>

    {{-- ── Cartes stats ──────────────────────────────────────────────── --}}
    <div class="mb-6 grid grid-cols-3 gap-4">
        <x-card class="shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-error/10">
                    <x-icon name="o-shield-exclamation" class="h-5 w-5 text-error" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-error">{{ $stats['total'] }}</p>
                    <p class="text-xs text-base-content/40">Total</p>
                </div>
            </div>
        </x-card>
        <x-card class="shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-warning/10">
                    <x-icon name="o-calendar-days" class="h-5 w-5 text-warning" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-warning">{{ $stats['today'] }}</p>
                    <p class="text-xs text-base-content/40">{{ __("Today") }}</p>
                </div>
            </div>
        </x-card>
        <x-card class="shadow-sm">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-base-200">
                    <x-icon name="o-globe-alt" class="h-5 w-5 text-base-content/60" />
                </div>
                <div>
                    <p class="text-2xl font-bold">{{ $stats['uniqueIps'] }}</p>
                    <p class="text-xs text-base-content/40">{{ __('Unique IPs') }}</p>
                </div>
            </div>
        </x-card>
    </div>

    {{-- ── Bulk bar ───────────────────────────────────────────────────── --}}
    @if (count($selected) > 0)
        <x-admin.shared.bulk-bar :selected="$selected">
            <x-slot:actions>
                <x-button class="btn-error btn-sm" icon="o-trash"
                    :label="__('Delete selection')"
                    wire:click="$set('bulkDeleteModal', true)" />
            </x-slot:actions>
        </x-admin.shared.bulk-bar>
    @endif

    {{-- ── Vue mobile (cards) ─────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 lg:hidden">
        @forelse ($spams as $spam)
            @php
                $ua = $spam->user_agent ?? '';
                [$uaClass, $uaLabel] = str_contains(strtolower($ua), 'bot')
                    ? ['badge-error badge-soft', 'Bot']
                    : (str_contains(strtolower($ua), 'curl')
                        ? ['badge-warning badge-soft', 'cURL']
                        : ['badge-ghost', __('Browser')]);
            @endphp
            <x-list-item :item="$spam" class="bg-base-100 rounded-lg border"
                wire:key="mobile-spam-{{ $spam->id }}">
                <x-slot:value>
                    <span class="font-mono text-sm">{{ $spam->ip }}</span>
                </x-slot:value>
                <x-slot:sub-value>
                    <div class="mt-0.5 flex items-center gap-2">
                        <x-badge :value="$uaLabel" class="{{ $uaClass }} badge-sm" />
                        <span class="text-xs text-base-content/40">
                            {{ $spam->created_at->translatedFormat('d M · H:i') }}
                        </span>
                    </div>
                </x-slot:sub-value>
                <x-slot:actions>
                    <x-admin.shared.row-actions>
                        <x-button class="btn-ghost btn-sm btn-circle" icon="o-eye"
                            :tooltip="__('View')"
                            wire:click="openDetail({{ $spam->id }})" />
                        <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-trash"
                            :tooltip="__('Delete')"
                            wire:click="confirmDelete({{ $spam->id }})" />
                    </x-admin.shared.row-actions>
                </x-slot:actions>
            </x-list-item>
        @empty
            <x-empty-state
                icon="o-shield-check"
                :heading="__('No spam recorded')"
                :message="__('Try adjusting your search or filters.')" />
        @endforelse
    </div>

    {{-- ── Vue desktop (table) ────────────────────────────────────────── --}}
    <div class="hidden lg:block">
        <x-card>
            @if ($spams->isEmpty())
                <x-empty-state
                    icon="o-shield-check"
                    :heading="__('No spam recorded')"
                    :message="__('Try adjusting your search or filters.')" />
            @else
                <x-table :headers="$headers" :rows="$spams" :sort-by="$sortBy"
                    selectable wire:model.live="selected">
                    @scope('cell_created_at', $spam)
                        <span class="text-xs text-base-content/40">
                            {{ $spam->created_at->translatedFormat('d M · H:i') }}
                        </span>
                    @endscope
                    @scope('cell_ip', $spam)
                        <span class="font-mono text-xs">{{ $spam->ip }}</span>
                    @endscope
                    @scope('cell_user_agent', $spam)
                        @php
                            $ua = $spam->user_agent ?? '';
                            [$uaClass, $uaLabel] = str_contains(strtolower($ua), 'bot')
                                ? ['badge-error badge-soft', 'Bot']
                                : (str_contains(strtolower($ua), 'curl')
                                    ? ['badge-warning badge-soft', 'cURL']
                                    : ['badge-ghost', __('Browser')]);
                        @endphp
                        <div class="flex items-center gap-2">
                            <x-badge :value="$uaLabel" class="{{ $uaClass }} badge-sm" />
                            <span class="text-xs text-base-content/40">{{ Str::limit($ua, 50) }}</span>
                        </div>
                    @endscope
                    @scope('cell_data', $spam)
                        <span class="text-xs text-base-content/40">
                            {{ Str::limit(collect($spam->inputs ?? [])->map(fn ($v, $k) => "$k: $v")->implode(' | '), 60) }}
                        </span>
                    @endscope
                    @scope('actions', $spam)
                        <x-admin.shared.row-actions>
                            <x-button class="btn-ghost btn-sm btn-circle" icon="o-eye"
                                :tooltip="__('View')"
                                wire:click="openDetail({{ $spam->id }})" />
                            <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-trash"
                                :tooltip="__('Delete')"
                                wire:click="confirmDelete({{ $spam->id }})" />
                        </x-admin.shared.row-actions>
                    @endscope
                </x-table>
                <div class="mt-4">
                    {{ $spams->links() }}
                </div>
            @endif
        </x-card>
    </div>

    {{-- ── Modal détail spam ─────────────────────────────────────────── --}}
    <x-modal wire:model="detailModal" :title="__('Spam detail')">
        @if ($detailSpam)
            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase tracking-widest opacity-50">IP</p>
                        <p class="font-mono">{{ $detailSpam->ip }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase tracking-widest opacity-50">
                            {{ __('Date') }}
                        </p>
                        <p>{{ $detailSpam->created_at->translatedFormat('d M Y à H:i') }}</p>
                    </div>
                </div>
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-widest opacity-50">User Agent</p>
                    <p class="break-all text-xs text-base-content/60">{{ $detailSpam->user_agent }}</p>
                </div>
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-widest opacity-50">
                        {{ __('Submitted data') }}
                    </p>
                    <pre class="bg-base-200 max-h-48 overflow-auto rounded-lg p-3 text-xs">{{ json_encode($detailSpam->inputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
        @endif
        <x-slot:actions>
            <x-button :label="__('Close')" wire:click="$set('detailModal', false)" />
        </x-slot:actions>
    </x-modal>

    {{-- ── Modal suppression ─────────────────────────────────────────── --}}
    <x-confirm-modal model="deleteModal" :title="__('Delete this spam?')"
        :confirmLabel="__('Delete')" confirmAction="delete">
        <p>{{ __('This action is irreversible.') }}</p>
    </x-confirm-modal>

    {{-- ── Modal bulk delete ─────────────────────────────────────────── --}}
    <x-confirm-modal model="bulkDeleteModal" :title="__('Delete selection?')"
        :confirmLabel="__('Delete')" confirmAction="bulkDelete">
        <p>
            {{ trans_choice('selectedCount', count($selected), ['count' => count($selected)]) }}
            {{ __('will be permanently deleted.') }}
        </p>
    </x-confirm-modal>
</div>
