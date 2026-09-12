{{--
    Où en est un membre avec son compte — partagé par les deux jumeaux de la
    liste, mobile et bureau.

    Usage :
        <x-admin.users.account-status-badge :user="$user" size="badge-xs" />

    Deux échelles pour une seule question. Un membre qui a une adresse à lui
    voyage par ses propres états d'invitation ; un compte géré n'en a aucun —
    personne ne remettra jamais un identifiant à une adresse qui n'existe pas —
    et rapporte donc l'avancement de ses tuteurs à la place.
--}}

@props([
    'user',
    'size' => 'badge-sm',
])

@php
    $state = $user->guardianshipStatus() ?? $user->invitationStatus();

    $badge = match ($state) {
        'active' => ['label' => __('Account created'), 'class' => 'badge-success badge-soft'],
        'pending' => ['label' => __('Pending'), 'class' => 'badge-warning badge-soft'],
        'expired' => ['label' => __('Expired'), 'class' => 'badge-error badge-soft'],
        'guardian_to_invite' => ['label' => __('Guardian to invite'), 'class' => 'badge-info badge-soft'],
        'guardian_invited' => ['label' => __('Guardian invited'), 'class' => 'badge-warning badge-soft'],
        'managed' => ['label' => __('Managed'), 'class' => 'badge-success badge-soft'],
        'guardian_unreachable' => ['label' => __('No reachable guardian'), 'class' => 'badge-error badge-soft'],
        default => ['label' => __('Not invited'), 'class' => 'badge-ghost'],
    };
@endphp

{{-- shrink-0 garde le libellé entier : dans une ligne flex, le badge est
     comprimé sous la largeur de son propre texte, et sa hauteur étant fixe,
     la deuxième ligne est rognée plutôt qu'affichée. --}}
<x-badge :value="$badge['label']" class="shrink-0 whitespace-nowrap {{ $badge['class'] }} {{ $size }}" />
