<?php
// ===========================================
// 17. resources/views/components/tournament/status-actions.blade.php
// ===========================================
?>
@props(['tournament', 'user'])

<div class="flex justify-end space-x-2">
    <x-ui.action-button 
        variant="{{ $user->pivot->has_paid ? 'success' : 'warning'}}" 
        icon="euro" 
        tooltip="{{ $user->pivot->has_paid ? __('Mark as unpaid') : __('Mark as paid') }}"
        onclick="window.location.href='{{ route('tournamentToggleHasPaid', [$tournament, $user]) }}'"
    />
    <x-ui.action-button 
        variant="danger" 
        icon="delete" 
        tooltip="{{ __('Unregister') }}"
        onclick="if(confirm('Êtes-vous sûr de vouloir supprimer ce tournoi ?')) { window.location.href='{{ route('tournamentUnregister', [$tournament, $user]) }}' }"
    />
</div>