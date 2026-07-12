{{--
    Dictionnaire unique des statuts d'inscription/affiliation du design system.
    Un statut = toujours le même couple couleur + libellé, sur toutes les pages.

    Usage :
        <x-admin.shared.status-badge status="waiting" :detail="$position" />
        <x-admin.shared.status-badge status="registered" />

    `detail` : position de liste d'attente, ou tout suffixe court (montant…).
--}}
@props([
    'status',
    'detail' => null,
])

@php
    [$classes, $label, $icon] = match ($status) {
        'registered' => ['badge-success', __('Registered'), null],
        'enrolled' => ['badge-success', __('Enrolled'), null],
        'paid' => ['badge-success', __('Paid'), null],
        'confirmed' => ['badge-info', __('Confirmed'), null],
        'offered' => ['badge-success', __('Spot available!'), null],
        'waiting' => ['badge-warning badge-soft', __('Waitlist') . ($detail !== null && $detail !== '' ? ' #' . $detail : ''), null],
        'pending' => ['badge-warning badge-soft', __('Awaiting validation'), null],
        'selected' => ['badge-primary', __('Selected'), 'o-check'],
        'full' => ['badge-ghost', __('Full'), null],
        'open' => ['badge-ghost', __('Open'), null],
        'closed' => ['badge-error badge-soft', __('Closed'), null],
        'cancelled' => ['badge-ghost', __('Cancelled'), null],
        'no_response' => ['badge-ghost', __('No response'), null],
        default => ['badge-ghost', $status, null],
    };
@endphp

<x-badge :value="$label" :icon="$icon"
    class="badge-sm font-bold {{ $classes }} {{ $attributes->get('class') }}" />
