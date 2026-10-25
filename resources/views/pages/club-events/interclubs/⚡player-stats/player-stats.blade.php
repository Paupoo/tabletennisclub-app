@php
    $signed = fn (?float $value, int $decimals = 2): string => $value === null
        ? '—'
        : ($value > 0 ? '+' : ($value < 0 ? '−' : '')) . \Illuminate\Support\Number::format(abs($value), precision: $decimals, locale: app()->getLocale());
@endphp

<div x-data="{ mobileSearchOpen: false, mobileActionsOpen: false }">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Player statistics')"
        :subtitle="__('Who wins beyond their ranking, and who plays: to decide selections, noyaux and entries.')">
        <x-slot:actions>
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" :show-search="false" :show-more="false" />
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
                @can('player_stats.configure')
                    <x-button class="btn-ghost btn-sm" icon="o-adjustments-horizontal" :label="__('Expected wins')"
                        link="{{ route('admin.interclubs.player-stats.expected-win-rates') }}" />
                @endcan
            </div>
            <x-button class="btn-ghost btn-sm lg:hidden" wire:click="toggleSelectionMode"
                icon="{{ $selectionModeActive ? 'o-x-mark' : 'o-check-circle' }}"
                :label="$selectionModeActive ? __('Cancel') : __('Compare')" />
        </x-slot:actions>
    </x-header>

    <x-admin.shared.filter-chips :chips="$filterChips" />

    {{-- La compétition et la période titrent tout ce qui suit : ce sont des
         navigations, pas des filtres, et elles valent toujours une valeur. --}}
    <div class="mb-4 flex flex-wrap items-end gap-x-3 gap-y-2">
        <div class="join">
            @foreach ($kinds as $key => $label)
                <button type="button" wire:click="$set('kind', '{{ $key }}')" wire:key="kind-{{ $key }}"
                    @class(['join-item btn btn-sm tap-min', 'btn-primary' => $kind === $key, 'btn-outline' => $kind !== $key])>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <x-select :label="__('Period')" wire:model.live="period" :options="$periods" class="select-sm" />

        @if (in_array($tab, ['best', 'worst'], true))
            <x-input :label="__('Minimum matches')" type="number" min="1" wire:model.live.debounce.400ms="minMatches" class="input-sm w-24" />
        @endif

        @if (in_array($tab, ['rising', 'falling'], true))
            <x-select :label="__('Compared with')" wire:model.live="trendBasis" class="select-sm" :options="[
                ['id' => 'season', 'name' => __('The previous season')],
                ['id' => 'form', 'name' => __('The 10 matches before')],
            ]" />
        @endif
    </div>

    <div role="tablist" class="tabs tabs-border mb-4 overflow-x-auto">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" wire:key="tab-{{ $key }}"
                @class(['tab whitespace-nowrap', 'tab-active' => $tab === $key])>{{ $label }}</button>
        @endforeach
    </div>

    <p class="text-subtle mb-3 text-xs">
        @switch($tab)
            @case('best')
            @case('worst')
                {{ __('Sorted by score per match: wins beyond what the ranking gap promised, divided by the matches played. Forfeits are left out.') }}
                @break
            @case('most')
            @case('least')
                {{ $kind === 'interclub' ? __('Sorted by ties played.') : ($kind === 'tournaments' ? __('Sorted by tournaments played.') : __('Sorted by matches played.')) }}
                @break
            @default
                {{ $trendBasis === 'form'
                    ? __('Score per match over the last 10 matches, against the 10 before.')
                    : __('Score per match this season, against the previous one.') }}
        @endswitch
    </p>

    @if ($rows->isEmpty())
        <x-card>
            <p class="text-muted py-6 text-center">{{ __('Nobody to show with these filters.') }}</p>
        </x-card>
    @else
        <x-card class="!p-2 lg:!p-5">
            <div class="overflow-x-auto">
                <table class="table-sm table">
                    <thead>
                        <tr>
                            <th @class(['w-8', 'hidden lg:table-cell' => ! $selectionModeActive])><span class="sr-only">{{ __('Compare') }}</span></th>
                            <th>#</th>
                            <th class="w-full">{{ __('Player') }}</th>
                            <th class="text-end">{{ __('Played') }}</th>
                            <th class="text-end">%</th>
                            <th class="text-end" title="{{ __('Wins beyond the expected ones, per match') }}">{{ __('Score / match') }}</th>
                            <th class="hidden text-end md:table-cell">{{ __('Won / expected') }}</th>
                            <th class="hidden text-end md:table-cell" title="{{ __('Wins over stronger players') }}">{{ __('Perfs') }}</th>
                            <th class="hidden text-end md:table-cell" title="{{ __('Losses to weaker players') }}">{{ __('Upsets') }}</th>
                            @if ($kind === 'interclub')
                                <th class="text-end">{{ __('Ties') }}</th>
                                <th class="hidden text-end lg:table-cell" title="{{ __('Ties played out of those of their team') }}">{{ __('Turnout') }}</th>
                                <th class="hidden text-end lg:table-cell" title="{{ __('Said they were available and were not lined up') }}">{{ __('Left out') }}</th>
                            @elseif ($kind === 'tournaments')
                                <th class="text-end">{{ __('Tournaments') }}</th>
                            @endif
                            <th class="hidden text-end sm:table-cell" title="{{ __('Score per match, against the previous season') }}">{{ __('Season trend') }}</th>
                            <th class="hidden text-end sm:table-cell" title="{{ __('Score per match, last 10 matches against the 10 before') }}">{{ __('Form') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $index => $row)
                            @php $figures = $row[$kind]; @endphp
                            <tr wire:key="player-{{ $row['user_id'] }}" @class(['text-muted' => $figures['played'] === 0])>
                                <td @class(['hidden lg:table-cell' => ! $selectionModeActive])>
                                    <input type="checkbox" class="checkbox checkbox-sm" wire:model.live="selected" value="{{ $row['user_id'] }}"
                                        aria-label="{{ __('Compare :name', ['name' => $row['name']]) }}" />
                                </td>
                                <td class="text-subtle tabular-nums">{{ $index + 1 }}</td>
                                <td class="max-w-0">
                                    <a class="link link-hover block truncate font-medium" wire:navigate
                                        href="{{ route('admin.interclubs.player-stats.show', ['member' => $row['user_id'], 'kind' => $kind, 'period' => $period]) }}">{{ $row['name'] }}</a>
                                    <span class="text-subtle text-xs">{{ $row['ranking'] ?? '—' }}</span>
                                </td>
                                <td class="text-end tabular-nums">
                                    {{ $figures['played'] }}
                                    @if ($figures['forfeits'] > 0)
                                        <span class="text-subtle text-xs" title="{{ __('Forfeits') }}">+{{ $figures['forfeits'] }} ff</span>
                                    @endif
                                </td>
                                <td class="text-end tabular-nums">{{ $figures['played'] > 0 ? $figures['rate'] : '—' }}</td>
                                <td @class([
                                    'text-end font-bold tabular-nums',
                                    'text-success' => ($figures['score_per_match'] ?? 0) > 0,
                                    'text-error' => ($figures['score_per_match'] ?? 0) < 0,
                                ])>{{ $signed($figures['score_per_match']) }}</td>
                                <td class="hidden text-end tabular-nums md:table-cell">
                                    {{ $figures['won'] }} / {{ \Illuminate\Support\Number::format($figures['expected'], maxPrecision: 1, locale: app()->getLocale()) }}
                                </td>
                                <td class="hidden text-end tabular-nums md:table-cell">{{ $figures['perfs'] }}</td>
                                <td class="hidden text-end tabular-nums md:table-cell">{{ $figures['contre_perfs'] }}</td>
                                @if ($kind === 'interclub')
                                    <td class="text-end tabular-nums">{{ $figures['ties'] }}</td>
                                    <td class="hidden text-end tabular-nums lg:table-cell">{{ $figures['participation'] === null ? '—' : $figures['participation'] . ' %' }}</td>
                                    <td class="hidden text-end tabular-nums lg:table-cell">{{ $figures['available_not_selected'] ?? '—' }}</td>
                                @elseif ($kind === 'tournaments')
                                    <td class="text-end tabular-nums">{{ $figures['tournaments'] }}</td>
                                @endif
                                @foreach (['season', 'form'] as $basis)
                                    @php $delta = $figures['trend'][$basis]; @endphp
                                    <td @class([
                                        'hidden whitespace-nowrap text-end tabular-nums sm:table-cell',
                                        'text-success' => $delta !== null && $delta > 0,
                                        'text-error' => $delta !== null && $delta < 0,
                                        'text-subtle' => $delta === null,
                                    ])>
                                        {{ $delta === null ? '—' : ($delta > 0 ? '↑ ' : ($delta < 0 ? '↓ ' : '= ')) . $signed($delta) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    <x-admin.shared.selection-pill :selected="$selected" :total="$this->getTotalMatchingCount()"
        :selecting-all-results="$selectingAllResults" :select-all="$selectAll">
        <x-slot:actions>
            @if (count($selected) >= 2 && count($selected) <= 4)
                <x-button wire:click="compare" icon="o-arrows-right-left" :label="__('Compare')" class="btn-primary btn-sm" />
            @else
                <span class="text-muted px-2 text-xs">{{ __('Tick 2 to 4 players to compare them.') }}</span>
            @endif
        </x-slot:actions>
    </x-admin.shared.selection-pill>

    <x-admin.shared.filter-drawer>
        <x-slot:filters>
            <x-choices-offline :label="__('Ranking')" wire:model.live="rankings" :options="$rankingOptions" searchable />
            <x-select :label="__('Gender')" wire:model.live="gender" :options="$genders" :placeholder="__('All')" />
            <div class="grid grid-cols-2 gap-3">
                <x-input :label="__('Born from')" type="number" wire:model.live.debounce.400ms="bornFrom" placeholder="2012" />
                <x-input :label="__('Born until')" type="number" wire:model.live.debounce.400ms="bornTo" placeholder="2015" />
            </div>
            <x-select :label="__('Team roster')" wire:model.live="teamId" :options="$teams" :placeholder="__('All')" />
            @if ($teamId !== '')
                <x-toggle :label="__('Matches with this team only')" wire:model.live="onlyTeamMatches"
                    :hint="__('Otherwise their whole record, standing in for other teams included.')" />
            @endif
            <x-toggle :label="__('Include former members')" wire:model.live="includeFormer" />
        </x-slot:filters>
    </x-admin.shared.filter-drawer>
</div>
