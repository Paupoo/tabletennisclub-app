<x-mail::message>
# {{ __('Bar shopping') }}

{{ __('Hello :name!', ['name' => $notifiable->first_name]) }}

@if ($trip)
<x-mail::panel>
{{ $trip }}
</x-mail::panel>
@endif

@if ($lines !== [])
{{ __('These products have reached their minimum threshold, please plan the shopping.') }}

<x-mail::table>
| {{ __('Product') }} | {{ __('To buy') }} |
|:-----|-----:|
@foreach ($lines as $line)
| {{ $line['name'] }} | {{ $line['packs'] }} ({{ $line['units'] }}) |
@endforeach
</x-mail::table>
@else
{{ __('Nothing needs buying right now.') }}
@endif

@if ($adjustments !== [])
## {{ __('Settings adjusted this week') }}

{{ __('The automatic restocking followed the sales.') }}

<x-mail::table>
| {{ __('Product') }} | {{ __('Min') }} | {{ __('Max') }} |
|:-----|-----:|-----:|
@foreach ($adjustments as $adjustment)
| {{ $adjustment['name'] }} | {{ $adjustment['min'] }} | {{ $adjustment['max'] }} |
@endforeach
</x-mail::table>
@endif

@if ($sleeping !== [])
## {{ __('No longer selling') }}

{{ __('No sale over the last weeks of activity. Take them out of restocking?') }}

@foreach ($sleeping as $name)
- {{ $name }}
@endforeach
@endif

<x-mail::button :url="$url">
{{ __('Open the shopping list') }}
</x-mail::button>
</x-mail::message>
