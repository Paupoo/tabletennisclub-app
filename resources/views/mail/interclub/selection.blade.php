<x-mail::message>
# {{ __('Interclub — You are selected!') }}

{{ __('Hello :name!', ['name' => $notifiable->first_name]) }}

{{ __('You have been selected for the **:team** interclub match.', ['team' => $ourTeamName]) }}

<x-mail::panel>

**{{ __('Team') }} :** {{ $ourTeamName }}

**{{ __('Opponent') }} :** {{ $opponent }}

**{{ __('Date') }} :** {{ $dateStr }}

**{{ __('Location') }} :** {{ $address }} *({{ $venue }})*

</x-mail::panel>

---

**{{ __('Selected lineup') }}**

@foreach($selectedPlayers as $player)
- {{ $player->full_name }}
@endforeach

@if($captainMessage)
<div style="border-left: 4px solid #fbbf24; background-color: #fffbeb; border-radius: 0 6px 6px 0; margin: 24px 0; overflow: hidden;">
<div style="padding: 16px 20px;">
<p style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #b45309; margin: 0 0 8px 0;">{{ __('Message from your captain') }}</p>
<p style="color: #78350f; margin: 0; font-style: italic;">{{ $captainMessage }}</p>
</div>
</div>
@endif

{{ __('See you on the court!') }}
</x-mail::message>
