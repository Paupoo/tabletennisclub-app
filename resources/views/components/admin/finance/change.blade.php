@props([
    'current',
    'previous',
    'previousLabel',
    // 'up' when more is good for the club (income, result), 'down' when less is (expenses)
    'goodWhen' => 'up',
    // 'percent' for a flow, 'euros' for a result that may cross zero
    'as' => 'percent',
])

{{--
    A flow compared with the financial year before: an arrow, the change, and
    its colour judged for the club — spending more is bad news even though
    the number went up. The arrow and the words carry the meaning; the
    colour only repeats it.
--}}
@php
    use App\Support\Charts\ChartFormat;

    $difference = round((float) $current - (float) $previous, 2);
    $text = $as === 'euros'
        ? ($difference > 0 ? '+' : ($difference < 0 ? '−' : '')) . ChartFormat::euros(abs($difference))
        : ChartFormat::change((float) $current, (float) $previous);
    $direction = $difference > 0 ? 'up' : ($difference < 0 ? 'down' : 'flat');
    $tone = match (true) {
        $direction === 'flat' || $text === null => 'text-muted',
        $direction === $goodWhen => 'text-success-content dark:text-success',
        default => 'text-error',
    };
    $icon = match ($direction) {
        'up' => 'o-arrow-trending-up',
        'down' => 'o-arrow-trending-down',
        default => 'o-minus',
    };
    $verdict = match (true) {
        $direction === 'flat' || $text === null => null,
        $direction === $goodWhen => __('good for the club'),
        default => __('bad for the club'),
    };
@endphp

<div data-change="{{ $direction }}" {{ $attributes->class(['mt-1 flex items-center gap-1 text-xs font-semibold', $tone]) }}>
    @if ($text === null)
        <span>{{ __('Nothing to compare with in :year', ['year' => $previousLabel]) }}</span>
    @else
        <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
        <span>{{ $text }} {{ __('vs :year', ['year' => $previousLabel]) }}</span>
        @if ($verdict)
            <span class="sr-only">— {{ $verdict }}</span>
        @endif
    @endif
</div>
