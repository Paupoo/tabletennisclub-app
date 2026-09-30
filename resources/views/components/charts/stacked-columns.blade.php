@props([
    'id',
    'title',
    'description' => '',
    // list<array{key: string, label: string, role: string}> — bottom of the stack first
    'series' => [],
    // list<array{label: string, long: string, values: list<array{balance: float|null, as_of: \Carbon\CarbonInterface|null}>, total: float, day: \Carbon\CarbonInterface}>
    'columns' => [],
    'palette' => null,
    'interactive' => true,
])

{{--
    One column per date, stacked by series: the money held at each month end,
    account by account and the tills on top. A 2px gap of surface between
    segments; only the top of a column is rounded. The last column's total
    is written above it; every figure is in the tooltip and in the table
    under the chart.

    A negative or unknown balance draws nothing (it is not money held); the
    tooltip and the table still say it. Server-drawn SVG, same rules as
    {@see monthly-flows}: CSS variables on screen, hex and no script for mPDF.
--}}
@php
    use App\Support\Charts\ChartFormat;
    use App\Support\Charts\ChartPalette;
    use App\Support\Charts\ChartScale;

    $palette ??= ChartPalette::css();

    $width = 720;
    $height = 240;
    $left = 72;
    $right = 24;
    $top = 24;
    $bottom = 28;
    $plotRight = $width - $right;
    $baseline = $height - $bottom;

    $stackOf = fn (array $column): float => array_sum(array_map(fn (array $value): float => max(0, (float) ($value['balance'] ?? 0)), $column['values']));
    $scale = ChartScale::covering(collect($columns)->map($stackOf)->push(0.0));
    $y = fn (float $value): float => $scale->position($value, $baseline, $top);

    // Twelve slots whatever the number of columns: a year in progress keeps
    // its months where they will stay.
    $slots = max(12, count($columns));
    $band = ($plotRight - $left) / $slots;
    $barWidth = min(28, round($band * 0.55, 2));
    $centre = fn (int $index): float => round($left + $band * $index + $band / 2, 2);
@endphp

<x-charts.tooltip-frame :interactive="$interactive" {{ $attributes }}>
    <x-charts.legend class="mb-2" :print="! $interactive"
        :items="array_map(fn (array $serie): array => ['label' => $serie['label'], 'colour' => $palette[$serie['role']]], $series)" />

    {{-- Drawn at 12px for a 720px width: narrower, the chart scrolls rather than shrink its labels below the DS-B floor. --}}
    <div style="overflow-x:auto">
    <svg viewBox="0 0 {{ $width }} {{ $height }}" width="100%" role="img" aria-labelledby="{{ $id }}-title {{ $id }}-desc"
        style="display:block;{{ $interactive ? 'min-width:720px;max-width:1080px;' : '' }}height:auto" font-family="{{ $interactive ? 'system-ui, sans-serif' : 'dejavusans, sans-serif' }}" font-size="12">
        <title id="{{ $id }}-title">{{ $title }}</title>
        <desc id="{{ $id }}-desc">{{ $description }}</desc>

        @foreach ($scale->ticks as $tick)
            <line x1="{{ $left }}" x2="{{ $plotRight }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" stroke-width="1"
                {!! ChartFormat::paint($tick == 0 ? $palette['axis'] : $palette['grid'], 'stroke') !!} />
            <text x="{{ $left - 8 }}" y="{{ $y($tick) + 4 }}" text-anchor="end" {!! ChartFormat::paint($palette['muted']) !!}>{{ ChartFormat::euros($tick) }}</text>
        @endforeach

        @foreach ($columns as $index => $column)
            @php
                $x = $centre($index);
                $parts = [];
                foreach ($series as $position => $serie) {
                    $value = $column['values'][$position] ?? ['balance' => null, 'as_of' => null];
                    $parts[] = $serie['label'] . ' : '
                        . ($value['balance'] === null ? __('no balance known') : ChartFormat::euros((float) $value['balance'], 2))
                        . ($value['as_of'] !== null && ! $value['as_of']->isSameDay($column['day']) ? ' (' . __('balance of :date', ['date' => $value['as_of']->format('d/m/Y')]) . ')' : '');
                }
                $tip = $column['long'] . ' — ' . implode(' · ', $parts) . ' · ' . __('Total') . ' : ' . ChartFormat::euros($column['total'], 2);
                $drawn = [];
                $running = 0.0;
                foreach ($series as $position => $serie) {
                    $amount = max(0, (float) ($column['values'][$position]['balance'] ?? 0));
                    if ($amount > 0) {
                        $drawn[] = ['role' => $serie['role'], 'from' => $running, 'to' => $running + $amount];
                        $running += $amount;
                    }
                }
            @endphp
            <g data-chart-mark=""
                @if ($interactive) tabindex="0" aria-label="{{ $tip }}" @mouseenter="show($el, @js($tip))" @focus="show($el, @js($tip))" @blur="hide()" @endif>
                <title>{{ $tip }}</title>
                <rect x="{{ $x - $band / 2 }}" y="{{ $top }}" width="{{ $band }}" height="{{ $baseline - $top }}" fill="transparent" />
                @foreach ($drawn as $segment)
                    @php
                        $segmentTop = $y($segment['to']);
                        // The surface gap sits under every segment but the first.
                        $segmentBottom = $y($segment['from']) - ($loop->first ? 0 : 2);
                    @endphp
                    @if ($segmentBottom - $segmentTop > 0)
                        @if ($loop->last)
                            <path class="chart-fill" d="{{ ChartFormat::bar($x - $barWidth / 2, $segmentTop, $barWidth, $segmentBottom - $segmentTop) }}" {!! ChartFormat::paint($palette[$segment['role']]) !!} />
                        @else
                            <rect class="chart-fill" x="{{ $x - $barWidth / 2 }}" y="{{ $segmentTop }}" width="{{ $barWidth }}" height="{{ $segmentBottom - $segmentTop }}" {!! ChartFormat::paint($palette[$segment['role']]) !!} />
                        @endif
                    @endif
                @endforeach
            </g>
            <text x="{{ $x }}" y="{{ $height - 8 }}" text-anchor="middle" {!! ChartFormat::paint($palette['muted']) !!}>{{ $column['label'] }}</text>
            @if ($loop->last)
                <text x="{{ $x }}" y="{{ $y($stackOf($column)) - 6 }}" text-anchor="middle" font-weight="600" {!! ChartFormat::paint($palette['ink']) !!}>{{ ChartFormat::euros($column['total']) }}</text>
            @endif
        @endforeach
    </svg>
    </div>
</x-charts.tooltip-frame>
