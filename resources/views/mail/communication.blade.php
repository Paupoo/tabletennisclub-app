<x-mail::message>
{{ $greeting }}

{{-- Rendu par Markdown::safe() : le HTML saisi par l'auteur est échappé, les liens dangereux retirés. --}}
{!! $bodyHtml !!}

@if ($authorName)
**{{ $authorName }}**<br>
@if ($authorRole)
{{ $authorRole }}<br>
@endif
@endif
{{ $clubName }}
</x-mail::message>
