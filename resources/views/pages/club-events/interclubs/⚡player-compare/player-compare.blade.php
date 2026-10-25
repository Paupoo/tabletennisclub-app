@php
    $number = fn (float|int $value, int $decimals = 0): string => \Illuminate\Support\Number::format($value, precision: $decimals, locale: app()->getLocale());
    $signed = fn (?float $value): string => $value === null
        ? '—'
        : ($value > 0 ? '+' : ($value < 0 ? '−' : '')) . $number(abs($value), 2);

    // [label, value reader, higher is better?] — the best value of a line is
    // set in bold, so the eye goes to it without a colour to learn.
    $lines = [
        [__('Played'), fn (array $f): ?float => $f['played'], true],
        [__('Win rate'), fn (array $f): ?float => $f['played'] > 0 ? $f['rate'] : null, true],
        [__('Score / match'), fn (array $f): ?float => $f['score_per_match'], true],
        [__('Won'), fn (array $f): ?float => $f['won'], true],
        [__('Expected'), fn (array $f): ?float => $f['expected'], null],
        [__('Perfs'), fn (array $f): ?float => $f['perfs'], true],
        [__('Upsets'), fn (array $f): ?float => $f['contre_perfs'], false],
        [__('Forfeits'), fn (array $f): ?float => $f['forfeits'], false],
    ];

    if ($kindName === 'interclub') {
        $lines[] = [__('Ties'), fn (array $f): ?float => $f['ties'], true];
        $lines[] = [__('Turnout'), fn (array $f): ?float => $f['participation'], true];
        $lines[] = [__('Left out'), fn (array $f): ?float => $f['available_not_selected'], null];
    } elseif ($kindName === 'tournaments') {
        $lines[] = [__('Tournaments'), fn (array $f): ?float => $f['tournaments'], true];
    }

    $lines[] = [__('Season trend'), fn (array $f): ?float => $f['trend']['season'], true];
    $lines[] = [__('Form'), fn (array $f): ?float => $f['trend']['form'], true];

    $signedLines = [__('Score / match'), __('Season trend'), __('Form')];
    $decimalLines = [__('Expected')];
@endphp

<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Compare players')" />

    <div class="join mb-4">
        @foreach ($kinds as $key => $label)
            <button type="button" wire:click="$set('kind', '{{ $key }}')" wire:key="kind-{{ $key }}"
                @class(['join-item btn btn-sm tap-min', 'btn-primary' => $kindName === $key, 'btn-outline' => $kindName !== $key])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if (count($players) < 2)
        <x-card>
            <p class="text-muted py-6 text-center">{{ __('Tick 2 to 4 players to compare them.') }}</p>
        </x-card>
    @else
        <x-card class="!p-2 lg:!p-5">
            <div class="overflow-x-auto">
                <table class="table-sm table">
                    <thead>
                        <tr>
                            <th></th>
                            @foreach ($players as $player)
                                <th class="text-end" wire:key="head-{{ $player['user_id'] }}">
                                    <a class="link link-hover" wire:navigate
                                        href="{{ route('admin.interclubs.player-stats.show', ['member' => $player['user_id'], 'kind' => $kindName, 'period' => $period]) }}">{{ $player['name'] }}</a>
                                    <span class="text-subtle block text-xs font-normal">{{ $player['ranking'] ?? '—' }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as [$label, $read, $higherIsBetter])
                            @php
                                $values = array_map(fn (array $player): ?float => $read($player[$kindName]) === null ? null : (float) $read($player[$kindName]), $players);
                                $known = array_filter($values, fn (?float $value): bool => $value !== null);
                                $best = $higherIsBetter === null || count($known) < 2 ? null : ($higherIsBetter ? max($known) : min($known));
                            @endphp
                            <tr wire:key="line-{{ $loop->index }}">
                                <th class="font-normal">{{ $label }}</th>
                                @foreach ($values as $value)
                                    <td @class(['text-end tabular-nums', 'font-bold' => $best !== null && $value === $best])>
                                        @if ($value === null)
                                            <span class="text-subtle">—</span>
                                        @elseif (in_array($label, $signedLines, true))
                                            {{ $signed($value) }}
                                        @elseif (in_array($label, $decimalLines, true))
                                            {{ $number($value, 1) }}
                                        @else
                                            {{ $number($value) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach

                        <tr><th colspan="{{ count($players) + 1 }}" class="text-muted pt-4 text-xs uppercase tracking-widest">{{ __('By ranking gap — won / played') }}</th></tr>
                        @foreach (array_reverse(array_keys($players[0]['by_gap'])) as $gap)
                            <tr wire:key="gap-{{ $gap }}">
                                <th class="font-normal">
                                    {{ $gap === 0 ? __('Same ranking') : ($gap > 0 ? '+' . $gap . ($gap === 4 ? '+' : '') : '−' . abs($gap) . ($gap === -4 ? '+' : '')) }}
                                </th>
                                @foreach ($players as $player)
                                    @php $cell = $player['by_gap'][$gap]; @endphp
                                    <td class="text-end tabular-nums">
                                        @if ($cell['played'] === 0)
                                            <span class="text-subtle">—</span>
                                        @else
                                            {{ $cell['won'] }} / {{ $cell['played'] }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</div>
