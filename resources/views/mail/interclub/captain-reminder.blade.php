<x-mail::message>
# {{ __('Lineups to send') }}

{{ __('Hello :name!', ['name' => $notifiable->first_name]) }}

{{ __('These matches are coming up and your team has not received its lineup yet. The club aims to send every lineup at least two weeks before the match, so everyone can get organised.') }}

<x-mail::table>
| {{ __('Date') }} | {{ __('Match') }} | {{ __('Status') }} |
|:-----|:-----|:-----|
@foreach ($fixtures as $fixture)
| {{ $fixture['date'] }} | {{ $fixture['team'] }} – {{ $fixture['opponent'] }} | @if ($fixture['saved']){{ __('Saved, not sent') }}@elseif ($fixture['missing'] > 0 && $fixture['pending'] > 0){{ trans_choice(':count player missing, :pending without an answer|:count players missing, :pending without an answer', $fixture['missing'], ['count' => $fixture['missing'], 'pending' => $fixture['pending']]) }}@elseif ($fixture['missing'] > 0){{ trans_choice(':count player missing, everyone answered|:count players missing, everyone answered', $fixture['missing'], ['count' => $fixture['missing']]) }}@else{{ __('To compose') }}@endif |
@endforeach
</x-mail::table>

{{ __('Only the players added or removed are told of a change.') }} {{ __('Sending early costs nothing if the lineup has to move later.') }}

<x-mail::button :url="$url">
{{ __('Open my selections') }}
</x-mail::button>
</x-mail::message>
