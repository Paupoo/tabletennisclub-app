@props([
    'players',
    'category' => null,
    'highlight' => null,
])
@php
    $sortedPlayers = collect($players)->sortBy(
        fn ($player) => sprintf('%04d|%s|%s', $player->forceListFor($category) ?? 9999, $player->last_name, $player->first_name)
    );
    $hasForceList = $sortedPlayers->contains(fn ($player) => $player->forceListFor($category) !== null);
@endphp
@if ($sortedPlayers->isNotEmpty())
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 8px 0 4px 0; border-collapse: collapse;">
@if ($hasForceList)
<tr>
<th align="left" style="width: 64px; padding: 0 12px 8px 0; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; border-bottom: 1px solid #e2e8f0;">{{ __('Force list') }}</th>
<th align="left" style="padding: 0 0 8px 0; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; border-bottom: 1px solid #e2e8f0;">{{ __('Player') }}</th>
</tr>
@endif
@foreach ($sortedPlayers as $player)
@php
    $forceList = $player->forceListFor($category);
    $isRecipient = $highlight?->id !== null && $highlight->id === $player->id;
@endphp
<tr>
@if ($hasForceList)
<td align="left" style="width: 64px; padding: 10px 12px 10px 0; vertical-align: middle; border-bottom: 1px solid #f1f5f9;">
<span style="display: inline-block; min-width: 34px; padding: 4px 8px; border-radius: 4px; background-color: {{ $forceList === null ? '#f1f5f9' : '#eff6ff' }}; color: {{ $forceList === null ? '#94a3b8' : '#1e40af' }}; font-size: 13px; font-weight: 700; text-align: center;">{{ $forceList === null ? '—' : '#' . $forceList }}</span>
</td>
@endif
<td align="left" style="padding: 10px 0; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 15px; color: #0f172a;">
<span style="font-weight: {{ $isRecipient ? '700' : '500' }};">{{ $player->full_name }}</span>
@if ($player->ranking)
<span style="margin-left: 6px; font-size: 12px; font-weight: 600; color: #64748b;">{{ $player->ranking }}</span>
@endif
@if ($isRecipient)
<span style="margin-left: 6px; padding: 2px 6px; border-radius: 4px; background-color: #1e40af; color: #ffffff; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('you') }}</span>
@endif
</td>
</tr>
@endforeach
</table>
@if ($hasForceList)
<p style="margin: 4px 0 0 0; font-size: 12px; color: #94a3b8;">{{ __('Players are listed in club force-list order. Players sharing a ranking share the same index.') }}</p>
@endif
@endif
