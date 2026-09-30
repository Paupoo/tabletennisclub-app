@props([
    'id',
    'title',
    'description' => '',
    // list<array{label: string, long: string, income: float, expenses: float, cumulative: float, future?: bool}>
    'months' => [],
    // array{income: string, expenses: string, cumulative: string}
    'labels' => [],
    'palette' => null,
    'interactive' => true,
])

{{--
    Income and expenses month by month — grouped columns — and the result as
    it builds up over the year, a line on the same euro axis (one axis only:
    the three are euros).

    Server-drawn SVG, no library: the same markup prints, and renders in mPDF
    when given `:palette="ChartPalette::print()"` and `:interactive="false"`.
    Each month is one hit target, focusable, whose tooltip lists all three
    figures; the line's last value is labelled on the chart itself.
--}}
@php
    use App\Support\Charts\ChartFormat;
    use App\Support\Charts\ChartPalette;
    use App\Support\Charts\ChartScale;

    $palette ??= ChartPalette::css();

    $width = 720;
    $height = 264;
    $left = 64;
    $right = 72;
    $top = 16;
    $bottom = 28;
    $plotRight = $width - $right;
    $baseline = $height - $bottom;

    $scale = ChartScale::covering(collect($months)->flatMap(fn (array $m): array => [$m['income'], $m['expenses'], $m['cumulative']]));
    $y = fn (float $value): float => $scale->position($value, $baseline, $top);
    $zero = $y(0);

    $count = max(1, count($months));
    $band = ($plotRight - $left) / $count;
    $barWidth = min(16, round(($band - 10) / 2, 2));
    $centre = fn (int $index): float => round($left + $band * $index + $band / 2, 2);

    // The line stops at the last month reached: a year in progress has no
    // result yet for the months to come.
    $reached = collect($months)->values()->reject(fn (array $m): bool => $m['future'] ?? false);
    $points = $reached->map(fn (array $m, int $i): string => $centre($i) . ',' . $y($m['cumulative']))->implode(' ');
    $last = $reached->last();
    $lastIndex = $reached->count() - 1;
@endphp

<x-charts.tooltip-frame :interactive="$interactive" {{ $attributes }}>
    <x-charts.legend class="mb-2" :items="[
        ['label' => $labels['income'] ?? __('Income'), 'colour' => $palette['income']],
        ['label' => $labels['expenses'] ?? __('Expenses'), 'colour' => $palette['expense']],
        ['label' => $labels['cumulative'] ?? __('Cumulative result'), 'colour' => $palette['result'], 'shape' => 'line'],
    ]" />

    {{-- Drawn at 12px for a 720px width: narrower, the chart scrolls rather than shrink its labels below the DS-B floor. --}}
    <div style="overflow-x:auto">
    <svg viewBox="0 0 {{ $width }} {{ $height }}" width="100%" role="img" aria-labelledby="{{ $id }}-title {{ $id }}-desc"
        style="display:block;min-width:720px;max-width:1080px;height:auto;overflow:visible" font-family="system-ui, sans-serif" font-size="12">
        <title id="{{ $id }}-title">{{ $title }}</title>
        <desc id="{{ $id }}-desc">{{ $description }}</desc>

        {{-- Grid and value axis --}}
        @foreach ($scale->ticks as $tick)
            <line x1="{{ $left }}" x2="{{ $plotRight }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" stroke-width="1"
                {!! ChartFormat::paint($tick == 0 ? $palette['axis'] : $palette['grid'], 'stroke') !!} />
            <text x="{{ $left - 8 }}" y="{{ $y($tick) + 4 }}" text-anchor="end" {!! ChartFormat::paint($palette['muted']) !!}>{{ ChartFormat::euros($tick) }}</text>
        @endforeach

        @foreach ($months as $index => $month)
            @php
                $x = $centre($index);
                $incomeTop = $y(max(0, $month['income']));
                $expenseTop = $y(max(0, $month['expenses']));
                $tip = $month['long'] . ' — ' . ($labels['income'] ?? __('Income')) . ' ' . ChartFormat::euros($month['income'], 2)
                    . ' · ' . ($labels['expenses'] ?? __('Expenses')) . ' ' . ChartFormat::euros($month['expenses'], 2)
                    . ' · ' . ($labels['cumulative'] ?? __('Cumulative result')) . ' ' . ChartFormat::euros($month['cumulative'], 2);
            @endphp
            <g data-chart-mark
                @if ($interactive) tabindex="0" aria-label="{{ $tip }}" @mouseenter="show($el, @js($tip))" @focus="show($el, @js($tip))" @blur="hide()" @endif>
                <title>{{ $tip }}</title>
                {{-- The whole month is the hit target, wider than its two columns. --}}
                <rect x="{{ $x - $band / 2 }}" y="{{ $top }}" width="{{ $band }}" height="{{ $baseline - $top }}" fill="transparent" />
                <path class="chart-fill" d="{{ ChartFormat::bar($x - $barWidth - 1, $incomeTop, $barWidth, $zero - $incomeTop) }}" {!! ChartFormat::paint($palette['income']) !!} />
                <path class="chart-fill" d="{{ ChartFormat::bar($x + 1, $expenseTop, $barWidth, $zero - $expenseTop) }}" {!! ChartFormat::paint($palette['expense']) !!} />
            </g>
            <text x="{{ $x }}" y="{{ $height - 8 }}" text-anchor="middle" {!! ChartFormat::paint($palette['muted']) !!}>{{ $month['label'] }}</text>
        @endforeach

        @if ($last !== null)
            <polyline points="{{ $points }}" fill="none" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" {!! ChartFormat::paint($palette['result'], 'stroke') !!} />
            <circle cx="{{ $centre($lastIndex) }}" cy="{{ $y($last['cumulative']) }}" r="6" {!! ChartFormat::paint($palette['surface']) !!} />
            <circle cx="{{ $centre($lastIndex) }}" cy="{{ $y($last['cumulative']) }}" r="4" {!! ChartFormat::paint($palette['result']) !!} />
            <text x="{{ $centre($lastIndex) + 10 }}" y="{{ $y($last['cumulative']) + 4 }}" font-weight="600" {!! ChartFormat::paint($palette['ink']) !!}>{{ ChartFormat::euros($last['cumulative']) }}</text>
        @endif
    </svg>
    </div>
</x-charts.tooltip-frame>
