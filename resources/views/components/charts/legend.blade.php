@props([
    // list<array{label: string, colour: string, shape?: 'box'|'line', value?: string|null}>
    'items' => [],
    // A PDF: mPDF lays out neither flex nor inline list items, so the keys go in a table.
    'print' => false,
])

{{--
    A chart's legend: identity is never carried by colour alone. The key
    mirrors the mark — a box for a bar, a stroke for a line — and the text
    stays in text colours, never in the series colour. Inline styles rather
    than classes so a PDF renderer draws the keys too.
--}}
@if ($print)
    <table style="border-collapse:collapse;margin-bottom:2mm;font-size:8pt;color:#5f5e5a">
        <tr>
            @foreach ($items as $item)
                @if (($item['shape'] ?? 'box') === 'line')
                    <td style="width:4mm;font-size:4pt;border-bottom:0.6mm solid {{ $item['colour'] }}">&nbsp;</td>
                @else
                    <td style="width:3mm;font-size:6pt;background-color:{{ $item['colour'] }}">&nbsp;</td>
                @endif
                <td style="padding:0 5mm 0 1.5mm;vertical-align:middle">{{ $item['label'] }}</td>
            @endforeach
        </tr>
    </table>
@else
<ul {{ $attributes->class('flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted') }}>
    @foreach ($items as $item)
        <li class="flex items-center gap-1.5">
            @if (($item['shape'] ?? 'box') === 'line')
                <span aria-hidden="true" style="display:inline-block;width:14px;height:2px;border-radius:1px;background:{{ $item['colour'] }}"></span>
            @else
                <span aria-hidden="true" style="display:inline-block;width:10px;height:10px;border-radius:2px;background:{{ $item['colour'] }}"></span>
            @endif
            <span>{{ $item['label'] }}</span>
            @if (filled($item['value'] ?? null))
                <span class="font-semibold tabular-nums text-base-content">{{ $item['value'] }}</span>
            @endif
        </li>
    @endforeach
</ul>
@endif
