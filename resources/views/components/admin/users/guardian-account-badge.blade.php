{{--
    Where a responsible adult stands with their account, as Guardian::invitationStage() reads it.
--}}
@props(['guardian'])

@php
    [$label, $class] = match ($guardian->invitationStage()) {
        'ready' => [__('Account active'), 'badge-success'],
        'waiting' => [__('Invitation pending'), 'badge-info'],
        'actionable' => [__('To invite'), 'badge-warning'],
        default => [__('No account, no email'), 'badge-neutral'],
    };
@endphp

<x-badge :value="$label" class="{{ $class }} badge-soft badge-sm whitespace-nowrap" />
