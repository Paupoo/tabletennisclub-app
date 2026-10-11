<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header progress-indicator separator :title="__('Coaching staff')"
        :subtitle="$this->viewSeason?->name ?? __('Select a season')">
        <x-slot:actions>
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" :show-search="false" :show-more="false" />
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
            </div>
            @if ($this->viewSeason)
                <x-dropdown :id="'staff-export'" right>
                    <x-slot:trigger>
                        <x-button class="btn-ghost btn-sm" icon="o-arrow-down-tray" :label="__('Export')" />
                    </x-slot:trigger>
                    <x-menu-item :title="__('Excel (.xlsx)')" wire:click="export('xlsx')" />
                    <x-menu-item :title="__('CSV')" wire:click="export('csv')" />
                </x-dropdown>
            @endif
        </x-slot:actions>
    </x-header>

    {{-- ── Season: navigation, not a filter (DS-A) ──────────────────────────── --}}
    <x-admin.shared.season-nav model="viewSeasonId" :options="$this->seasonOptions" class="mt-4" />

    <x-admin.shared.filter-chips :chips="$filterChips" />

    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <x-select wire:model.live="monthFilter" :label="__('Month')" :placeholder="__('All months')"
                :options="$this->monthOptions" />
            <x-select wire:model.live="packFilter" :label="__('Pack')" :placeholder="__('All packs')"
                placeholder-value="0" :options="$this->packOptions" />
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    @if ($this->rows === [])
        <x-admin.shared.list-empty-state icon="o-user-group" :heading="__('Nobody coached or sparred over this period.')"
            :filtered="count($filterChips) > 0">
            {{ __('Coaches and sparrings appear here as soon as they are expected at a session.') }}
        </x-admin.shared.list-empty-state>
    @else
        <x-card class="!p-0">
            <div class="divide-y divide-base-200">
                @foreach ($this->rows as $row)
                    <button type="button" wire:key="staff-{{ $row['user_id'] }}" wire:click="showDetail({{ $row['user_id'] }})"
                        class="flex w-full cursor-pointer flex-col gap-2 p-4 text-left hover:bg-base-200/50 sm:flex-row sm:items-center sm:justify-between">
                        <span class="truncate font-semibold">{{ $row['name'] }}</span>
                        <span class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                            <span class="tabular-nums">{{ trans_choice(':n session coached|:n sessions coached', $row['coached'], ['n' => $row['coached']]) }}</span>
                            <span class="tabular-nums">{{ trans_choice(':n sparring presence|:n sparring presences', $row['sparred'], ['n' => $row['sparred']]) }}</span>
                            @if ($row['pending'] > 0)
                                <x-badge class="badge-warning badge-soft badge-sm"
                                    :value="trans_choice(':n to record|:n to record', $row['pending'], ['n' => $row['pending']])" />
                            @endif
                            @if ($row['upcoming'] > 0)
                                <span class="tabular-nums text-base-content/60">{{ trans_choice(':n to come|:n to come', $row['upcoming'], ['n' => $row['upcoming']]) }}</span>
                            @endif
                        </span>
                    </button>
                @endforeach
            </div>
        </x-card>
    @endif

    {{-- Read-only: the detail of the line clicked, over the same season, month and pack. --}}
    <x-drawer wire:model="detailDrawer" :title="$this->detailUser?->full_name ?? __('Detail')" :subtitle="$this->scopeLabel()"
        right separator with-close-button class="w-full max-w-md">
        <ul class="space-y-2 text-sm">
            @forelse ($this->detail as $line)
                @php
                    [$label, $class] = match ($line['status']) {
                        'counted' => [__('Recorded'), 'badge-success'],
                        'present' => [__('Present'), 'badge-success'],
                        'absent' => [__('Absent'), 'badge-error'],
                        'planned' => [__('Planned'), 'badge-neutral'],
                        default => [__('To record'), 'badge-warning'],
                    };
                @endphp
                <li class="flex items-center justify-between gap-3 border-b border-base-300 pb-2 last:border-0">
                    <div>
                        <p class="font-medium">{{ \Carbon\Carbon::parse($line['date'])->format('d/m/Y') }}</p>
                        <p class="text-xs text-base-content/60">
                            {{ $line['pack'] }} · {{ $line['role'] === 'coach' ? __('Coach') : __('Sparring') }}
                        </p>
                    </div>
                    <x-badge :value="$label" class="{{ $class }} badge-soft" />
                </li>
            @empty
                <li class="text-base-content/60">{{ __('No session over this period.') }}</li>
            @endforelse
        </ul>

        <x-slot:actions>
            <x-button :label="__('Close')" wire:click="$set('detailDrawer', false)" />
        </x-slot:actions>
    </x-drawer>
</div>
