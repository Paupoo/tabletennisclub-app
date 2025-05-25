<?php
// ===========================================
// 17. resources/views/components/tournament/status-actions.blade.php
// ===========================================
?>
@props(['tournament'])

<div class="flex justify-end space-x-2">
    @if($tournament->status === 'open')
        <x-ui.action-button 
            variant="default" 
            icon="draft" 
            tooltip="Mettre en brouillon"
            onclick="window.location.href='{{ route('unpublishTournament', $tournament) }}'"
        />
    @endif

    @if($tournament->status === 'draft')
        <x-ui.action-button 
            variant="info" 
            icon="publish" 
            tooltip="Publier"
            onclick="window.location.href='{{ route('publishTournament', $tournament) }}'"
        />
    @endif

    @if($tournament->status === 'closed')
        <x-ui.action-button 
            variant="primary" 
            icon="view" 
            tooltip="Voir les résultats"
            onclick="window.location.href='{{ route('tournamentShow', $tournament) }}'"
        />
    @else
        <x-ui.action-button 
            variant="primary" 
            icon="edit" 
            tooltip="Modifier"
            onclick="window.location.href='{{ route('tournamentShow', $tournament) }}'"
        />
    @endif

    <x-ui.action-button 
        variant="danger" 
        icon="delete" 
        tooltip="Supprimer"
        onclick="if(confirm('Êtes-vous sûr de vouloir supprimer ce tournoi ?')) { window.location.href='{{ route('deleteTournament', $tournament) }}' }"
    />
</div>