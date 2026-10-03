<x-mail::message>
# {{ __('Bar inventory') }}

@if ($name)
{{ __('Hello :name!', ['name' => $name]) }}
@endif

{{ __(':name validated the bar inventory on :date. :count products were counted.', [
    'name' => $inventory->closer?->full_name,
    'date' => $inventory->closed_at?->translatedFormat('l j F, H:i'),
    'count' => $counted,
]) }}

@if ($inventory->comment)
<x-mail::panel>
{{ $inventory->comment }}
</x-mail::panel>
@endif

@if ($gaps === [])
{{ __('No gap: every product counted was right.') }}
@else
<x-mail::table>
| {{ __('Product') }} | {{ __('Gap') }} | {{ __('Value') }} | {{ __('What happened') }} |
|:-----|-----:|-----:|:-----|
@foreach ($gaps as $gap)
| {{ $gap['name'] }} | {{ $gap['gap'] }} | {{ $gap['value'] }} | {{ $gap['cause'] }}{{ $gap['note'] ? ' — ' . $gap['note'] : '' }} |
@endforeach
</x-mail::table>

**{{ __('Total: :missing missing, :surplus surplus, :value at the selling price.', ['missing' => $totals['missing'], 'surplus' => $totals['surplus'], 'value' => euros($totals['value'])]) }}**
@endif

@if ($added !== [])
{{ __('Added to the bar: :products.', ['products' => implode(', ', $added)]) }}
@endif

<x-mail::button :url="$url">
{{ __('See the inventory') }}
</x-mail::button>
</x-mail::message>
