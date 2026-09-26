<x-mail::message>
# {{ __('Bar shopping') }}

{{ __('Hello :name!', ['name' => $notifiable->first_name]) }}

@if ($trip)
<x-mail::panel>
{{ $trip }}
</x-mail::panel>
@endif

{{ __('These products are at or below their min. Whoever has the keys and a moment can fill the bar.') }}

<x-mail::table>
| {{ __('Product') }} | {{ __('To buy') }} |
|:-----|-----:|
@foreach ($lines as $line)
| {{ $line['name'] }} | {{ $line['packs'] }} ({{ $line['units'] }}) |
@endforeach
</x-mail::table>

<x-mail::button :url="$url">
{{ __('Open the shopping list') }}
</x-mail::button>
</x-mail::message>
