@props([
    'id',
    'title',
    'description' => '',
    // list<array{key: string, label: string}> — the segments, in stacking order
    'segments' => [],
    // list<array{label: string, values: array<string, float>, format: 'count'|'euros'}>
    'rows' => [],
    'palette' => null,
    'interactive' => true,
])

{{--
    100 % stacked bars: how a whole splits into parts, one bar per way of
    counting it (the number of movements, their amount).

    Segments are separated by a 2px gap, never by a border. No figure is
    written inside a segment — the fills range from dark blue to amber and no
    single ink reads on all of them — so the caller shows every figure in a
    legend or a table next to the bars, and the tooltip repeats it.
--}}
@php
    use App\Support\Charts\ChartFormat;
    use App\Support\Charts\ChartPalette;

    $palette ??= ChartPalette::css();

    $width = 720;
    $labelWidth = 96;
    $right = 8;
    $rowHeight = 40;
    $barHeight = 24;
    $height = count($rows) * $rowHeight;
    $plotWidth = $width - $labelWidth - $right;

    $format = fn (float $value, string $kind): string => $kind === 'euros'
        ? ChartFormat::euros($value)
        : trans_choice(':count movement|:count movements', (int) $value);
@endphp

<x-charts.tooltip-frame :interactive="$interactive" {{ $attributes }}>
    <svg viewBox="0 0 {{ $width }} {{ max(1, $height) }}" width="100%" role="img" aria-labelledby="{{ $id }}-title {{ $id }}-desc"
        style="display:block;max-width:100%;height:auto" font-family="{{ $interactive ? 'system-ui, sans-serif' : 'dejavusans, sans-serif' }}" font-size="12">
        <title id="{{ $id }}-title">{{ $title }}</title>
        <desc id="{{ $id }}-desc">{{ $description }}</desc>

        @foreach ($rows as $rowIndex => $row)
            @php
                $total = array_sum($row['values']);
                $cursor = $labelWidth;
                $rowTop = $rowIndex * $rowHeight + ($rowHeight - $barHeight) / 2;
                $visible = array_values(array_filter($segments, fn (array $segment): bool => ($row['values'][$segment['key']] ?? 0) > 0));
            @endphp
            <text x="{{ $labelWidth - 10 }}" y="{{ $rowTop + $barHeight / 2 + 4 }}" text-anchor="end" {!! ChartFormat::paint($palette['ink']) !!}>{{ $row['label'] }}</text>

            @if ($total <= 0)
                <rect x="{{ $labelWidth }}" y="{{ $rowTop }}" width="{{ $plotWidth }}" height="{{ $barHeight }}" rx="4" {!! ChartFormat::paint($palette['grid']) !!} />
            @endif

            @foreach ($visible as $segmentIndex => $segment)
                @php
                    $value = $row['values'][$segment['key']];
                    $share = $value / $total;
                    $segmentWidth = max(1, $share * $plotWidth - ($segmentIndex < count($visible) - 1 ? 2 : 0));
                    $percent = (int) round($share * 100);
                    $tip = $segment['label'] . ' — ' . $format($value, $row['format']) . ' (' . $percent . ' %)';
                    $colour = $palette[$segment['key']] ?? $palette['internal'];
                @endphp
                <g data-chart-mark=""
                    @if ($interactive) tabindex="0" aria-label="{{ $row['label'] }} — {{ $tip }}" @mouseenter="show($el, @js($tip))" @focus="show($el, @js($tip))" @blur="hide()" @endif>
                    <title>{{ $row['label'] }} — {{ $tip }}</title>
                    <rect class="chart-fill" x="{{ round($cursor, 2) }}" y="{{ $rowTop }}" width="{{ round($segmentWidth, 2) }}" height="{{ $barHeight }}"
                        rx="{{ count($visible) === 1 ? 4 : 0 }}" {!! ChartFormat::paint($colour) !!} />
                </g>
                @php $cursor += $share * $plotWidth; @endphp
            @endforeach
        @endforeach
    </svg>
</x-charts.tooltip-frame>
