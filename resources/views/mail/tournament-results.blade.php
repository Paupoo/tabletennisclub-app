<x-mail::message>
# {{ $tournament->name }}

Bonjour **{{ $recipient->first_name }}**,

{{-- Written in <x-markdown-editor>; Markdown::safe() escapes raw HTML and drops unsafe links. --}}
{!! \App\Support\Markdown::safe($emailBody) !!}

---

@if ($rankings->isNotEmpty())
## Classement final

@foreach ($rankings as $entry)
@php
$rankedName = isset($entry['pair']) && $entry['pair'] !== null ? $entry['pair']->fullName() : $entry['user']->full_name;
@endphp
@if ($entry['rank'] === 1)
🥇 **{{ $rankedName }}** — {{ $entry['result'] }}
@elseif ($entry['rank'] === 2)
🥈 **{{ $rankedName }}** — {{ $entry['result'] }}
@elseif ($entry['rank'] === 3)
🥉 **{{ $rankedName }}** — {{ $entry['result'] }}
@else
{{ $entry['rank'] }}. {{ $rankedName }}
@endif
@endforeach

@endif

{{ config('app.name') }}

<x-mail::subcopy>
Email : {{ config('mail.from.address') }} · Site : {{ config('app.url') }}
</x-mail::subcopy>
</x-mail::message>
