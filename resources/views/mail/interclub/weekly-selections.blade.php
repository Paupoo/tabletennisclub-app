<x-mail::message>
# {{ __('Weekly selections') }}

{{ __('Hello,') }}

{{ $intro }}

@foreach ($days as $day => $fixtures)
<p style="margin: 28px 0 10px 0; padding-bottom: 6px; border-bottom: 2px solid #e2e8f0; font-size: 12px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: #1e40af;">{{ ucfirst(\Illuminate\Support\Carbon::parse($day)->translatedFormat('l j F')) }}</p>
<div style="font-size: 0;">
@foreach ($fixtures as $fixture)
@php
    $isMine = collect($memberIds)->contains(fn (int $id): bool => $fixture->involves($id));
@endphp
<a href="{{ route('admin.interclubs.my-match', $fixture->id) }}" @if ($isMine) data-mine="{{ $fixture->id }}" @endif style="display: inline-block; vertical-align: top; width: 47%; margin: 0 1.5% 12px 1.5%; box-sizing: border-box; border: {{ $isMine ? '2px solid #1e40af' : '1px solid #e2e8f0' }}; border-radius: 10px; overflow: hidden; text-decoration: none; color: #0f172a; font-size: 14px; background-color: #ffffff;">
<span style="display: block; padding: 8px 10px; background-color: {{ $isMine ? '#eff6ff' : '#f8fafc' }}; border-bottom: 1px solid #e2e8f0; font-weight: 700;">{{ $fixture->startsAt->format('H\hi') }}<span style="float: right; font-size: 11px; font-weight: 700; padding: 1px 7px; border-radius: 999px; background-color: {{ $fixture->isHome ? '#fbbf24' : '#eff6ff' }}; color: #1e40af;">{{ $fixture->isHome ? __('At home') : ($fixture->town !== null ? __('in :town', ['town' => $fixture->town]) : __('Away match')) }}</span></span>
<span style="display: block; padding: 10px 10px 8px 10px;"><span style="display: block; font-size: 16px; font-weight: 700; color: #1e40af;">{{ $fixture->teamName }}</span><span style="display: block; color: #475569;">{{ __('against') }} <strong style="color: #0f172a;">{{ $fixture->opponentName }}</strong></span></span>
<span style="display: block; padding: 8px 10px 10px 10px; border-top: 1px solid #e2e8f0;">
@if ($fixture->forfeit !== null)
<span style="color: #be123c; font-weight: 700;">{{ $fixture->forfeit->label() }}</span>
@elseif (! $fixture->isPublished)
<span style="color: #94a3b8; font-style: italic;">{{ __('Lineup not sent yet') }}</span>
@else
@foreach ($fixture->players as $player)
<span style="display: block; {{ in_array($player['id'], $memberIds, true) ? 'font-weight: 700;' : '' }}">{{ $player['name'] }}@if ($player['is_walkover']) <span style="font-size: 10px; font-weight: 700; padding: 1px 5px; border-radius: 4px; background-color: #fbbf24; color: #78350f;">WO</span>@endif</span>
@endforeach
@if ($fixture->shortHandedWith !== null)
<span style="display: inline-block; margin-top: 6px; font-size: 11px; font-weight: 700; padding: 1px 7px; border-radius: 999px; background-color: #fff1f2; color: #be123c;">{{ __('Plays with :n', ['n' => $fixture->shortHandedWith]) }}</span>
@endif
@endif
</span>
</a>
@endforeach
</div>
@endforeach

<x-mail::button :url="$url">
{{ __('See on the website') }}
</x-mail::button>

{{ __('Unable to play? Let your captain know as soon as possible.') }}
</x-mail::message>
