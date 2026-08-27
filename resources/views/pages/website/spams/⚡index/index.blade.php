<div x-data="{ mobileSearchOpen: false, mobileActionsOpen: false }">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator title="Spam">
        <x-slot:middle>
            <div class="hidden w-full lg:block">
                <x-input class="w-full" clearable icon="o-magnifying-glass"
                    :placeholder="__('Search by IP or user agent…')"
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
                    placeholder="{{ __('Search by IP or user agent…') }}" />
            </div>
            <button type="button" @click="mobileSearchOpen = false" class="btn btn-ghost btn-circle btn-sm"
                aria-label="{{ __('Close the search') }}">
                <x-icon name="o-x-mark" class="h-5 w-5" />
            </button>
        </div>
    </div>

    {{-- ── Active filter chips ──────────────────────────────────────────────── --}}
    <x-admin.shared.filter-chips :chips="$filterChips" />

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
                    <x-icon name="o-calendar-days" class="h-5 w-5 text-warning-content" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-warning-content">{{ $stats['today'] }}</p>
                    <p class="text-xs text-base-content/40">{{ __('Today') }}</p>
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

    @php $hasActiveFilters = count($filterChips) > 0 || filled($search); @endphp

    {{-- ── Vue mobile ───────────────────────────────────────────────── --}}
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
                <x-slot:avatar>
                    @if ($selectionModeActive)
                        <input type="checkbox"
                            class="checkbox checkbox-primary checkbox-sm"
                            value="{{ $spam->id }}"
                            wire:model.live="selected" />
                    @endif
                </x-slot:avatar>
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
                    @if (! $selectionModeActive)
                        <x-admin.shared.row-menu
                            :label="__('View')"
                            icon="o-eye"
                            wire-click="openDetail({{ $spam->id }})">
                            <x-menu-item icon="o-trash" class="text-error" :title="__('Delete')"
                                wire:click="confirmDelete({{ $spam->id }})" />
                        </x-admin.shared.row-menu>
                    @endif
                </x-slot:actions>
            </x-list-item>
        @empty
            <x-admin.shared.list-empty-state
                icon="o-shield-check"
                :heading="__('No spam recorded')"
                :filtered="$hasActiveFilters" />
        @endforelse

        @if ($spams->hasPages())
            <div class="mt-2">
                {{ $spams->links() }}
            </div>
        @endif
    </div>

    {{-- ── Vue desktop ────────────────────────────────────────────────── --}}
    <div class="hidden lg:block">
        <x-card>
            @if ($spams->isEmpty())
                <x-admin.shared.list-empty-state
                    icon="o-shield-check"
                    :heading="__('No spam recorded')"
                    :filtered="$hasActiveFilters" />
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
                        <x-admin.shared.row-menu
                            :label="__('View')"
                            icon="o-eye"
                            wire-click="openDetail({{ $spam->id }})">
                            <x-menu-item icon="o-trash" class="text-error" :title="__('Delete')"
                                wire:click="confirmDelete({{ $spam->id }})" />
                        </x-admin.shared.row-menu>
                    @endscope
                </x-table>
                <div class="mt-4">
                    {{ $spams->links() }}
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
            <x-button class="btn-ghost btn-sm text-error" icon="o-trash" :label="__('Delete')"
                wire:click="confirmBulkDelete" />
        </x-slot:actions>
    </x-admin.shared.selection-pill>

    {{-- ── Filter drawer ────────────────────────────────────────────────────── --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">
                    {{ __('Period') }}
                </p>
                <x-select :options="$periodOptions" :placeholder="__('All periods')"
                    wire:model.live="period" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">
                    {{ __('Type') }}
                </p>
                <x-select :options="$userAgentOptions" :placeholder="__('All types')"
                    wire:model.live="userAgentType" class="w-full" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- ── Modal détail spam ─────────────────────────────────────────── --}}
    <x-app-modal wire:model="detailModal" :title="__('Spam detail')" :open="$detailModal">
        @if ($detailSpam)
            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-muted">IP</p>
                        <p class="font-mono">{{ $detailSpam->ip }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-muted">
                            {{ __('Date') }}
                        </p>
                        <p>{{ $detailSpam->created_at->translatedFormat('d M Y à H:i') }}</p>
                    </div>
                </div>
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-muted">User Agent</p>
                    <p class="break-all text-xs text-base-content/60">{{ $detailSpam->user_agent }}</p>
                </div>
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-muted">
                        {{ __('Submitted data') }}
                    </p>
                    <pre class="bg-base-200 max-h-48 overflow-auto rounded-lg p-3 text-xs">{{ json_encode($detailSpam->inputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
        @endif
        <x-slot:actions>
            <x-button :label="__('Close')" wire:click="$set('detailModal', false)" />
        </x-slot:actions>
    </x-app-modal>

    {{-- ── Modal suppression unitaire ───────────────────────────────── --}}
    <x-confirm-modal model="deleteModal" :title="__('Delete this spam?')"
        :confirmLabel="__('Delete')" confirmAction="delete" :open="$deleteModal">
        <p>{{ __('This action is irreversible.') }}</p>
    </x-confirm-modal>

    {{-- ── Modal suppression bulk ───────────────────────────────────── --}}
    <x-confirm-modal model="bulkDeleteModal" :title="__('Delete selection?')"
        :confirmLabel="__('Delete')" confirmAction="bulkDelete" :open="$bulkDeleteModal">
        <p>
            {{ trans_choice('selectedCount', count($selected), ['count' => count($selected)]) }}
            {{ __('will be permanently deleted.') }}
        </p>
    </x-confirm-modal>

    {{-- ── Mobile action sheet ─────────────────────────────────────────── --}}
    <x-admin.shared.mobile-actions>
        <x-admin.shared.mobile-action-item
            icon="o-check-circle" color="base"
            :label="__('Select')"
            :description="__('Bulk actions on multiple entries')"
            @click="mobileActionsOpen = false; $wire.call('toggleSelectionMode')" />
    </x-admin.shared.mobile-actions>
</div>
