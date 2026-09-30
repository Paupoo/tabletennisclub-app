<x-mail::message>
# {{ $tournament->name }}

Bonjour **{{ $recipient->first_name }}**,

{{-- Written in <x-markdown-editor>; Markdown::safe() escapes raw HTML and drops unsafe links. --}}
{!! \App\Support\Markdown::safe($emailBody) !!}

---

@if ($rankings->isNotEmpty())
## Classement final

@foreach ($rankings as $entry)
@if ($entry['rank'] === 1)
🥇 **{{ $entry['user']->full_name }}** — {{ $entry['result'] }}
@elseif ($entry['rank'] === 2)
🥈 **{{ $entry['user']->full_name }}** — {{ $entry['result'] }}
@elseif ($entry['rank'] === 3)
🥉 **{{ $entry['user']->full_name }}** — {{ $entry['result'] }}
@else
{{ $entry['rank'] }}. {{ $entry['user']->full_name }}
@endif
@endforeach

@endif

{{ config('app.name') }}

<x-mail::subcopy>
Email : {{ config('mail.from.address') }} · Site : {{ config('app.url') }}
</x-mail::subcopy>
</x-mail::message>
