{{--
    Où en est un membre avec le club, saison après saison — partagé par les deux
    jumeaux de la liste et par la fiche d'un adulte responsable.

    Usage :
        <x-admin.users.membership-status-badge :user="$user" :last-season="$name" size="badge-xs" />

    La licence ne s'affiche qu'à côté d'une affiliation de la saison en cours :
    « Récréatif » sous un ancien membre laissait croire qu'il jouait encore.

    Un membre « à relancer » porte le nom de la saison où on l'a vu pour la
    dernière fois : c'est la phrase que la secrétaire lui écrira.

    Un membre « à relancer » qu'on a déjà relancé porte la date de la dernière
    relance : c'est ce qui dit à la secrétaire s'il faut recommencer.

    Un membre parti porte son motif et sa date en infobulle : la liste ne
    lui consacre pas de colonne, la fiche les écrit en toutes lettres.

    Les attributs `data-*` servent aux tests : le tiroir de filtres imprime les
    mêmes mots que les badges, le texte seul ne dit pas d'où il vient.
--}}

@props([
    'user',
    'lastSeason' => null,
    'size' => 'badge-sm',
])

@php
    $membershipStatus = $user->membershipStatus();
@endphp

<span class="inline-flex flex-wrap items-center gap-1.5" data-membership-status="{{ $membershipStatus->value }}">
    @if ($membershipStatus === \App\Domains\Shared\Enums\MembershipStatus::Left && ($departure = $user->currentDeparture()) !== null)
        <span class="tooltip inline-flex" data-departure-reason="{{ $departure->reason->value }}"
            data-tip="{{ __('Left on :date — :reason', ['date' => $departure->left_on->format('d/m'), 'reason' => $departure->reason->label()]) }}">
            <x-badge :value="$membershipStatus->label()"
                class="shrink-0 whitespace-nowrap {{ $membershipStatus->badgeClass() }} {{ $size }}" />
            <span class="sr-only">{{ $departure->reason->label() }}</span>
        </span>
    @else
        <x-badge :value="$membershipStatus->label()"
            class="shrink-0 whitespace-nowrap {{ $membershipStatus->badgeClass() }} {{ $size }}" />
    @endif
    @if ($membershipStatus->isAffiliatedThisSeason())
        @if ($user->holdsCompetitiveLicence())
            <span data-licence="competitive" class="inline-flex">
                <x-badge :value="__('Competitive')" class="shrink-0 whitespace-nowrap badge-primary badge-soft {{ $size }}" />
            </span>
        @else
            <span data-licence="recreational" class="inline-flex">
                <x-badge :value="__('Recreational')" class="shrink-0 whitespace-nowrap badge-ghost {{ $size }}" />
            </span>
        @endif
    @endif
    @if ($membershipStatus === \App\Domains\Shared\Enums\MembershipStatus::ToFollowUp && filled($lastSeason))
        <span class="w-full text-xs text-muted">{{ __('Last season: :season', ['season' => $lastSeason]) }}</span>
    @endif
    @if ($membershipStatus === \App\Domains\Shared\Enums\MembershipStatus::ToFollowUp && $user->renewal_reminded_at !== null)
        <span class="w-full text-xs text-muted" data-renewal-reminded>{{ __('Reminded on :date', ['date' => $user->renewal_reminded_at->format('d/m')]) }}</span>
    @endif
</span>
