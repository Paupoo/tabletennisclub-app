{{--
    Ce membre répond d'un autre : un parent qui tient la fiche de tuteur d'un
    pupille. Indépendant du statut d'affiliation — un parent peut jouer, avoir
    joué ou n'avoir jamais touché une raquette.

    Usage :
        <x-admin.users.responsible-adult-badge :user="$user" size="badge-xs" />
--}}

@props([
    'user',
    'size' => 'badge-sm',
])

@if ($user->isResponsibleAdult())
    <span data-responsible-adult class="inline-flex">
        <x-badge :value="__('Responsible')" class="shrink-0 whitespace-nowrap badge-secondary badge-soft {{ $size }}" />
    </span>
@endif
