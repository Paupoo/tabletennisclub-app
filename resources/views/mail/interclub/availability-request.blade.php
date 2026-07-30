<x-mail::message>
# {{ __('Interclub — your availability') }}

{{ __('Hello :name!', ['name' => $notifiable->first_name]) }}

{{ __('Your captain needs to know if you are available for the upcoming interclub match.') }}

<x-mail::panel>

**{{ __('Team') }} :** {{ $ourTeamName }}

**{{ __('Opponent') }} :** {{ $opponent }}

**{{ __('Date') }} :** {{ $dateStr }}

**{{ __('Location') }} :** {{ $address }} *({{ $venue }})*

</x-mail::panel>

<x-mail::button :url="$url">
{{ __('Indicate my availability') }}
</x-mail::button>

{{ __('Thank you!') }}
</x-mail::message>
