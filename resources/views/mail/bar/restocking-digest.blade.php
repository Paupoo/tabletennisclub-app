<x-mail::message>
# {{ __('Bar shopping') }}

{{ __('Hello :name!', ['name' => $notifiable->first_name]) }}

@if ($trip)
<x-mail::panel>
{{ $trip }}
</x-mail::panel>
@endif

@if ($lines !== [])
{{ __('These products are at or below their min. Whoever has the keys and a moment can fill the bar.') }}

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

{{ __('The automatic restocking followed the sales. A product corrected by hand becomes manual.') }}

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
