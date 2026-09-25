<x-mail::message>
# {{ __('Lineups to send') }}

{{ __('Hello :name!', ['name' => $notifiable->first_name]) }}

{{ __('These matches are coming up and your team has not received its lineup yet. The club aims to send every lineup at least two weeks before the match, so everyone can get organised.') }}

<x-mail::table>
| {{ __('Date') }} | {{ __('Match') }} | {{ __('Status') }} |
|:-----|:-----|:-----|
@foreach ($fixtures as $fixture)
| {{ $fixture['date'] }} | {{ $fixture['team'] }} – {{ $fixture['opponent'] }} | {{ $fixture['saved'] ? __('Saved, not sent') : __('To compose') }} |
@endforeach
</x-mail::table>

{{ __('Only the players added or removed are told of a change.') }} {{ __('Sending early costs nothing if the lineup has to move later.') }}

<x-mail::button :url="$url">
{{ __('Open my selections') }}
</x-mail::button>
</x-mail::message>
