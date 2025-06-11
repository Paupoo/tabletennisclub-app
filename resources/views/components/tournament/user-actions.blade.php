<?php
// ===========================================
// 17. resources/views/components/tournament/status-actions.blade.php
// ===========================================
?>
@props(['tournament', 'user'])

<div class="flex justify-end space-x-2">
    @can('updatesBeforeStart', $tournament)
    <x-ui.action-button 
        variant="{{ $user->pivot->has_paid ? 'success' : 'warning'}}" 
        icon="euro" 
        tooltip="{{ $user->pivot->has_paid ? __('Mark as unpaid') : __('Mark as paid') }}"
        onclick="window.location.href='{{ route('tournaments.toggleHasPaid', [$tournament, $user]) }}'"
    />
    @endcan
    <x-ui.action-button 
        variant="danger" 
        icon="delete" 
        tooltip="{{ __('Unregister User') }}"
        onclick="if(confirm('Are you sure you want to unregisteR this user from the tournament?')) { window.location.href='{{ route('tournamentUnregister', [$tournament, $user]) }}' }"
    />
</div>