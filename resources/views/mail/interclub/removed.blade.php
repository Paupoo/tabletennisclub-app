<x-mail::message>
# {{ __('Interclub — You are no longer selected') }}

{{ __('Hello :name!', ['name' => $notifiable->first_name]) }}

{{ __('The lineup for the **:team** match has changed — you are no longer selected for this match.', ['team' => $ourTeamName]) }}

<x-mail::panel>

**{{ __('Team') }} :** {{ $ourTeamName }}

**{{ __('Opponent') }} :** {{ $opponent }}

**{{ __('Date') }} :** {{ $dateStr }}

**{{ __('Location') }} :** {{ $address }} *({{ $venue }})*

</x-mail::panel>

{{ __('Contact your captain if you have any questions.') }}
</x-mail::message>
