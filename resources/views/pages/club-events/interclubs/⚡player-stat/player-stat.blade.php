@php
    $number = fn (float|int $value, int $decimals = 0): string => \Illuminate\Support\Number::format($value, precision: $decimals, locale: app()->getLocale());
    $signed = fn (?float $value): string => $value === null
        ? '—'
        : ($value > 0 ? '+' : ($value < 0 ? '−' : '')) . $number(abs($value), 2);
    $gapLabel = function (int $gap): string {
        if ($gap === 0) {
            return __('Same ranking');
        }

        $count = abs($gap);
        $plus = $count === 4 ? '+' : '';

        return $gap > 0
            ? trans_choice('{1} :count cran stronger|[2,*] :count crans stronger', $count, ['count' => $count . $plus])
            : trans_choice('{1} :count cran weaker|[2,*] :count crans weaker', $count, ['count' => $count . $plus]);
    };
@endphp

<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="$member->first_name . ' ' . $member->last_name"
        :subtitle="__('Ranking today: :ranking', ['ranking' => $member->ranking?->value ?? '—'])" />

    <div class="mb-4 flex flex-wrap items-end gap-x-3 gap-y-2">
        <div class="join">
            @foreach ($kinds as $key => $label)
                <button type="button" wire:click="$set('kind', '{{ $key }}')" wire:key="kind-{{ $key }}"
                    @class(['join-item btn btn-sm tap-min', 'btn-primary' => $kindName === $key, 'btn-outline' => $kindName !== $key])>
                    {{ $label }}
                </button>
            @endforeach
        </div>
        <x-select :label="__('Period')" wire:model.live="period" :options="$periods" class="select-sm" />
    </div>

    @if ($figures === null || ($figures['played'] === 0 && $figures['forfeits'] === 0))
        <x-card>
            <p class="text-muted py-6 text-center">{{ __('No match recorded over this period.') }}</p>
        </x-card>
    @else
        <section class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-admin.shared.stat-card :label="__('Played')" :value="$figures['played']" icon="o-play"
                :hint="$figures['forfeits'] > 0 ? trans_choice('{1} plus :count forfeit|[2,*] plus :count forfeits', $figures['forfeits'], ['count' => $figures['forfeits']]) : null" />
            <x-admin.shared.stat-card :label="__('Win rate')" :value="$figures['rate'] . ' %'" icon="o-trophy"
                :hint="__(':won won', ['won' => $figures['won']])" />
            <x-admin.shared.stat-card :label="__('Score / match')" :value="$signed($figures['score_per_match'])" icon="o-scale"
                :color="($figures['score_per_match'] ?? 0) >= 0 ? 'success' : 'error'"
                :hint="__(':won won for :expected expected', ['won' => $figures['won'], 'expected' => $number($figures['expected'], 1)])" />
            <x-admin.shared.stat-card :label="__('Perfs / upsets')" :value="$figures['perfs'] . ' / ' . $figures['contre_perfs']" icon="o-bolt"
                :hint="__('Wins over stronger, losses to weaker')" />
            @if ($kindName === 'interclub')
                <x-admin.shared.stat-card :label="__('Ties')" :value="$figures['ties']" icon="o-user-group"
                    :hint="$figures['participation'] === null ? null : __(':rate % of their team\'s ties', ['rate' => $figures['participation']])" />
                <x-admin.shared.stat-card :label="__('Left out')" :value="$figures['available_not_selected'] ?? '—'" icon="o-hand-raised"
                    :hint="__('Said they were available and were not lined up')" />
            @elseif ($kindName === 'tournaments')
                <x-admin.shared.stat-card :label="__('Tournaments')" :value="$figures['tournaments']" icon="o-flag" />
            @endif
            <x-admin.shared.stat-card :label="__('Season trend')" :value="$signed($figures['trend']['season'])" icon="o-arrow-trending-up"
                :hint="__('Against the previous season')" />
            <x-admin.shared.stat-card :label="__('Form')" :value="$signed($figures['trend']['form'])" icon="o-fire"
                :hint="__('Last 10 matches against the 10 before')" />
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <x-card :title="__('By ranking gap')" class="!p-2 lg:!p-5">
                <table class="table-sm table">
                    <thead>
                        <tr>
                            <th>{{ __('Opponent') }}</th>
                            <th class="text-end">{{ __('Played') }}</th>
                            <th class="text-end">{{ __('Won') }}</th>
                            <th class="text-end">{{ __('Expected') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_reverse($detail['by_gap'], true) as $gap => $cell)
                            <tr wire:key="gap-{{ $gap }}" @class(['text-subtle' => $cell['played'] === 0])>
                                <td>{{ $gapLabel((int) $gap) }}</td>
                                <td class="text-end tabular-nums">{{ $cell['played'] }}</td>
                                <td @class([
                                    'text-end tabular-nums font-medium',
                                    'text-success' => $cell['played'] > 0 && $cell['won'] > $cell['expected'],
                                    'text-error' => $cell['played'] > 0 && $cell['won'] < $cell['expected'],
                                ])>{{ $cell['won'] }}</td>
                                <td class="text-end tabular-nums">{{ $cell['played'] > 0 ? $number($cell['expected'], 1) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>

            <x-card :title="__('Month by month')" class="!p-2 lg:!p-5">
                @if (count($monthly) === 0)
                    <p class="text-muted text-sm">{{ __('No rated match over this period.') }}</p>
                @else
                    <x-charts.score-columns id="player-monthly" :columns="$monthly"
                        :title="__('Score per match, month by month')"
                        :description="__('Above zero, the player won more than the ranking gaps promised.')" />
                @endif

                @if (count($detail['ranking_history']) > 0)
                    <h3 class="text-muted mb-2 mt-4 text-xs font-bold uppercase tracking-widest">{{ __('Ranking on the sheets') }}</h3>
                    <div class="flex flex-wrap items-center gap-1 text-sm">
                        @foreach ($detail['ranking_history'] as $step)
                            @if (! $loop->first)
                                <x-icon name="o-arrow-long-right" class="text-subtle h-4 w-4" />
                            @endif
                            <span class="badge badge-ghost" title="{{ \Illuminate\Support\Carbon::parse($step['date'])->isoFormat('LL') }}">{{ $step['ranking'] }}</span>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            @foreach (['perfs' => __('Perfs: wins over stronger players'), 'contre_perfs' => __('Upsets: losses to weaker players')] as $key => $title)
                <x-card :title="$title" class="!p-2 lg:!p-5" wire:key="list-{{ $key }}">
                    @forelse ($detail[$key] as $entry)
                        <div class="border-base-200 flex items-baseline justify-between gap-2 border-b py-1.5 text-sm last:border-0">
                            <div class="min-w-0">
                                <span class="block truncate font-medium">{{ $entry['opponent'] }}</span>
                                <span class="text-subtle text-xs">
                                    {{ \Illuminate\Support\Carbon::parse($entry['date'])->isoFormat('ll') }}
                                    · {{ $entry['kind'] === 'interclub' ? __('Interclub') : $entry['context'] }}
                                </span>
                            </div>
                            <span class="whitespace-nowrap tabular-nums">{{ $entry['our_ranking'] }} → {{ $entry['opponent_ranking'] }}</span>
                        </div>
                    @empty
                        <p class="text-muted text-sm">{{ __('None over this period.') }}</p>
                    @endforelse
                </x-card>
            @endforeach
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <x-card :title="__('Opponents met most')" class="!p-2 lg:!p-5">
                @if (count($detail['opponents']) === 0)
                    <p class="text-muted text-sm">{{ __('None over this period.') }}</p>
                @else
                    <table class="table-sm table">
                        <thead>
                            <tr>
                                <th class="w-full">{{ __('Opponent') }}</th>
                                <th class="text-end">{{ __('Ranking') }}</th>
                                <th class="text-end">{{ __('Record') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detail['opponents'] as $index => $opponent)
                                <tr wire:key="opponent-{{ $index }}">
                                    <td class="max-w-0"><span class="block truncate">{{ $opponent['name'] }}</span></td>
                                    <td class="text-end">{{ $opponent['ranking'] ?? '—' }}</td>
                                    <td class="whitespace-nowrap text-end tabular-nums">
                                        {{ __(':won W – :lost L', ['won' => $opponent['won'], 'lost' => $opponent['played'] - $opponent['won']]) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>

            <div class="space-y-4">
                @if ($kindName !== 'tournaments' && count($detail['by_team']) > 0)
                    <x-card :title="__('By team')" class="!p-2 lg:!p-5">
                        <table class="table-sm table">
                            <thead>
                                <tr>
                                    <th class="w-full">{{ __('Team') }}</th>
                                    <th class="text-end">{{ __('Played') }}</th>
                                    <th class="text-end">%</th>
                                    <th class="text-end">{{ __('Score / match') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($detail['by_team'] as $index => $team)
                                    <tr wire:key="team-{{ $index }}">
                                        <td class="max-w-0"><span class="block truncate">{{ $team['team'] }}</span></td>
                                        <td class="text-end tabular-nums">{{ $team['played'] }}</td>
                                        <td class="text-end tabular-nums">{{ $team['rate'] }}</td>
                                        <td class="text-end tabular-nums">{{ $signed($team['score_per_match']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-card>
                @endif

                <x-card :title="__('Season by season')" class="!p-2 lg:!p-5">
                    <table class="table-sm table">
                        <thead>
                            <tr>
                                <th class="w-full">{{ __('Season') }}</th>
                                <th class="text-end">{{ __('Played') }}</th>
                                <th class="text-end">%</th>
                                <th class="text-end">{{ __('Score / match') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_reverse($detail['by_season']) as $index => $season)
                                <tr wire:key="season-{{ $index }}">
                                    <td>{{ $season['label'] }}</td>
                                    <td class="text-end tabular-nums">{{ $season['played'] }}</td>
                                    <td class="text-end tabular-nums">{{ $season['rate'] }}</td>
                                    <td class="text-end tabular-nums">{{ $signed($season['score_per_match']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            </div>
        </div>
    @endif
</div>
