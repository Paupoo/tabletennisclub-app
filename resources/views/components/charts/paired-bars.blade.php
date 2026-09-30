@props([
    'id',
    'title',
    'description' => '',
    // list<array{label: string, current: float, previous: float}>, largest first
    'rows' => [],
    'currentLabel',
    'previousLabel',
    'palette' => null,
    'interactive' => true,
])

{{--
    One poste per row, this year against the year before: two horizontal bars
    from a shared baseline, this year in colour, last year receding in grey.
    This year's amount is written at the tip of its bar; the change and last
    year's amount are in the tooltip and in the table under the chart.

    Rows arrive sorted by the caller (largest first). Server-drawn SVG, same
    rules as {@see monthly-flows}: CSS variables on screen, hex for mPDF.
--}}
@php
    use App\Support\Charts\ChartFormat;
    use App\Support\Charts\ChartPalette;
    use App\Support\Charts\ChartScale;

    $palette ??= ChartPalette::css();

    $width = 720;
    $labelWidth = 188;
    $right = 88;
    $rowHeight = 36;
    $top = 8;
    $barHeight = 12;
    $height = $top + max(1, count($rows)) * $rowHeight + 8;
    $plotLeft = $labelWidth;
    $plotRight = $width - $right;

    $scale = ChartScale::covering(collect($rows)->flatMap(fn (array $row): array => [max(0, $row['current']), max(0, $row['previous'])]));
    $x = fn (float $value): float => $scale->position(max(0, $value), $plotLeft, $plotRight);
@endphp

<x-charts.tooltip-frame :interactive="$interactive" {{ $attributes }}>
    <x-charts.legend class="mb-2" :items="[
        ['label' => $currentLabel, 'colour' => $palette['current']],
        ['label' => $previousLabel, 'colour' => $palette['previous']],
    ]" />

    {{-- Drawn at 12px for a 720px width: narrower, the chart scrolls rather than shrink its labels below the DS-B floor. --}}
    <div style="overflow-x:auto">
    <svg viewBox="0 0 {{ $width }} {{ $height }}" width="100%" role="img" aria-labelledby="{{ $id }}-title {{ $id }}-desc"
        style="display:block;min-width:720px;max-width:1080px;height:auto" font-family="system-ui, sans-serif" font-size="12">
        <title id="{{ $id }}-title">{{ $title }}</title>
        <desc id="{{ $id }}-desc">{{ $description }}</desc>

        <line x1="{{ $plotLeft }}" x2="{{ $plotLeft }}" y1="{{ $top }}" y2="{{ $height - 8 }}" stroke-width="1" {!! ChartFormat::paint($palette['axis'], 'stroke') !!} />

        @foreach ($rows as $index => $row)
            @php
                $rowTop = $top + $index * $rowHeight;
                $change = ChartFormat::change($row['current'], $row['previous']);
                $tip = $row['label'] . ' — ' . $currentLabel . ' : ' . ChartFormat::euros($row['current'], 2)
                    . ($change !== null ? ' (' . $change . ')' : '')
                    . ' · ' . $previousLabel . ' : ' . ChartFormat::euros($row['previous'], 2);
                $currentEnd = $x($row['current']);
            @endphp
            <g data-chart-mark
                @if ($interactive) tabindex="0" aria-label="{{ $tip }}" @mouseenter="show($el, @js($tip))" @focus="show($el, @js($tip))" @blur="hide()" @endif>
                <title>{{ $tip }}</title>
                <rect x="0" y="{{ $rowTop }}" width="{{ $width }}" height="{{ $rowHeight }}" fill="transparent" />
                <text x="{{ $plotLeft - 10 }}" y="{{ $rowTop + $rowHeight / 2 + 4 }}" text-anchor="end" {!! ChartFormat::paint($palette['ink']) !!}>{{ \Illuminate\Support\Str::limit($row['label'], 28) }}</text>
                <path class="chart-fill" d="{{ ChartFormat::bar($plotLeft, $rowTop + 5, $currentEnd - $plotLeft, $barHeight, horizontal: true) }}" {!! ChartFormat::paint($palette['current']) !!} />
                <path class="chart-fill" d="{{ ChartFormat::bar($plotLeft, $rowTop + 5 + $barHeight + 2, $x($row['previous']) - $plotLeft, $barHeight - 4, horizontal: true) }}" {!! ChartFormat::paint($palette['previous']) !!} />
                <text x="{{ $currentEnd + 6 }}" y="{{ $rowTop + 5 + $barHeight - 2 }}" font-weight="600" {!! ChartFormat::paint($palette['ink']) !!}>{{ ChartFormat::euros($row['current']) }}</text>
            </g>
        @endforeach
    </svg>
    </div>
</x-charts.tooltip-frame>
