@props([
    'id',
    'title',
    'description' => '',
    // list<array{label: string, long: string, value: float, played: int, won: int}>, oldest first
    'columns' => [],
    'palette' => null,
    'interactive' => true,
])

{{--
    One column per period — a month, a season — rising above zero when the
    player won more than the ranking gaps promised, falling below when fewer.
    Score per match, so a month of two matches and a month of twelve read on
    the same axis; the tooltip says how many matches each column stands on.

    Server-drawn SVG, same rules as {@see monthly-flows}.
--}}
@php
    use App\Support\Charts\ChartFormat;
    use App\Support\Charts\ChartPalette;
    use App\Support\Charts\ChartScale;

    $palette ??= ChartPalette::css();

    $width = 720;
    $height = 220;
    $left = 56;
    $right = 16;
    $top = 16;
    $bottom = 28;
    $plotRight = $width - $right;
    $baseline = $height - $bottom;

    $scale = ChartScale::covering(collect($columns)->pluck('value')->push(0.25)->push(-0.25));
    $y = fn (float $value): float => $scale->position($value, $baseline, $top);
    $zero = $y(0);

    $count = max(1, count($columns));
    $band = ($plotRight - $left) / $count;
    $barWidth = min(28, round($band - 8, 2));
    $centre = fn (int $index): float => round($left + $band * $index + $band / 2, 2);
    $format = fn (float $value): string => ($value > 0 ? '+' : ($value < 0 ? '−' : '')) . number_format(abs($value), 2, ',', ' ');
    // A label every few columns once they crowd: twelve month names fit, forty do not.
    $labelEvery = (int) ceil($count / 12);
@endphp

<x-charts.tooltip-frame :interactive="$interactive" {{ $attributes }}>
    <x-charts.legend class="mb-2" :print="! $interactive" :items="[
        ['label' => __('Above the odds'), 'colour' => $palette['justified']],
        ['label' => __('Below the odds'), 'colour' => $palette['expense']],
    ]" />

    <div style="overflow-x:auto">
    <svg viewBox="0 0 {{ $width }} {{ $height }}" width="100%" role="img" aria-labelledby="{{ $id }}-title {{ $id }}-desc"
        style="display:block;{{ $interactive ? 'min-width:480px;max-width:1080px;' : '' }}height:auto;overflow:visible" font-family="system-ui, sans-serif" font-size="12">
        <title id="{{ $id }}-title">{{ $title }}</title>
        <desc id="{{ $id }}-desc">{{ $description }}</desc>

        @foreach ($scale->ticks as $tick)
            <line x1="{{ $left }}" x2="{{ $plotRight }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" stroke-width="1"
                {!! ChartFormat::paint($tick == 0 ? $palette['axis'] : $palette['grid'], 'stroke') !!} />
            <text x="{{ $left - 8 }}" y="{{ $y($tick) + 4 }}" text-anchor="end" {!! ChartFormat::paint($palette['muted']) !!}>{{ $format($tick) }}</text>
        @endforeach

        @foreach ($columns as $index => $column)
            @php
                $x = $centre($index);
                $end = $y($column['value']);
                $tip = $column['long'] . ' — ' . __(':won won of :played', ['won' => $column['won'], 'played' => $column['played']])
                    . ' · ' . __('Score / match') . ' ' . $format($column['value']);
            @endphp
            <g data-chart-mark=""
                @if ($interactive) tabindex="0" aria-label="{{ $tip }}" @mouseenter="show($el, @js($tip))" @focus="show($el, @js($tip))" @blur="hide()" @endif>
                <title>{{ $tip }}</title>
                <rect x="{{ $x - $band / 2 }}" y="{{ $top }}" width="{{ $band }}" height="{{ $baseline - $top }}" fill="transparent" />
                <path class="chart-fill" d="{{ ChartFormat::bar($x - $barWidth / 2, min($end, $zero), $barWidth, max(1, abs($zero - $end))) }}"
                    {!! ChartFormat::paint($column['value'] >= 0 ? $palette['justified'] : $palette['expense']) !!} />
            </g>
            @if ($index % $labelEvery === 0)
                <text x="{{ $x }}" y="{{ $height - 8 }}" text-anchor="middle" {!! ChartFormat::paint($palette['muted']) !!}>{{ $column['label'] }}</text>
            @endif
        @endforeach
    </svg>
    </div>
</x-charts.tooltip-frame>
