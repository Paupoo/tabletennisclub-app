@props([
    // list<array{label: string, colour: string, shape?: 'box'|'line', value?: string|null}>
    'items' => [],
])

{{--
    A chart's legend: identity is never carried by colour alone. The key
    mirrors the mark — a box for a bar, a stroke for a line — and the text
    stays in text colours, never in the series colour. Inline styles rather
    than classes so a PDF renderer draws the keys too.
--}}
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
