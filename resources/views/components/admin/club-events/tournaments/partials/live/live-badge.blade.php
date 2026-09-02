{{--
    « Ça se joue là, maintenant. »

    Le point qui bat est la seule chose animée de la page : sur un téléphone,
    au bord du terrain, c'est ce qu'on repère avant de lire. Le nom de la table
    suit, parce que c'est la seule information qui fait bouger quelqu'un.

    @param string      $table    Nom de la table
    @param string|null $room     Nom de la salle, omis quand le club n'en a qu'une
    @param mixed       $startedAt Début du match, pour les minutes écoulées
    @param bool        $compact  Pastille seule, pour une ligne de classement
--}}
@props([
    'table',
    'room' => null,
    'startedAt' => null,
    'compact' => false,
])

@php
    $minutes = $startedAt ? (int) \Carbon\Carbon::parse($startedAt)->diffInMinutes(now()) : null;
@endphp

<span {{ $attributes->class([
    'inline-flex shrink-0 items-center gap-1.5 rounded-full bg-error/10 font-semibold text-error',
    'px-2 py-0.5 text-xs' => $compact,
    'px-2.5 py-1 text-sm' => ! $compact,
]) }}>
    <span class="relative flex h-2 w-2 shrink-0" aria-hidden="true">
        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-error opacity-75"></span>
        <span class="relative inline-flex h-2 w-2 rounded-full bg-error"></span>
    </span>

    <span class="truncate">
        <span class="sr-only">{{ __('Being played now on') }} </span>{{ $table }}
        @if ($room && ! $compact)
            <span class="font-normal opacity-70">· {{ $room }}</span>
        @endif
    </span>

    @if ($minutes !== null && ! $compact)
        <span class="font-mono font-normal opacity-70">{{ $minutes }}′</span>
    @endif
</span>
