@props(['tournament'])

<div class="flex justify-end space-x-2">
    <x-ui.action-button 
        variant="default" 
        icon="view" 
        tooltip="Voir les résultats"
        onclick="window.location.href='{{ route('tournaments.show', $tournament) }}'"
    />
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
    @can('update', $tournament)
        <x-ui.action-button 
            variant="default" 
            icon="edit" 
            tooltip="Modifier"
            onclick="window.location.href='{{ route('tournament.edit', $tournament) }}'"
        />
    @endcan

    @can('updateSubscriptionAsUser', $tournament)
    @if($tournament->users->contains(auth()->user()->id))
    <a href="/admin/tournament/unregister/{{$tournament->id}}/{{auth()->user()->id}}">
        <x-ui.action-button 
        variant="danger" 
        icon="leave" 
        tooltip="{{ __('Unregister from tournament') }}"
        />
    </a>
    @endif
    @if(!$tournament->users->contains(auth()->user()->id))
    <a href="/admin/tournament/register/{{$tournament->id}}/{{auth()->user()->id}}">
        <x-ui.action-button 
        variant="default" 
        icon="join" 
        tooltip="{{ __('Register to tournament') }}"
        />
    </a>
    @endif
    @endcan
    @can('delete',  $tournament)
    <x-ui.action-button 
        variant="danger" 
        icon="delete" 
        tooltip="Supprimer"
        onclick="if(confirm('Êtes-vous sûr de vouloir supprimer ce tournoi ?')) { window.location.href='{{ route('tournaments.destroy', $tournament) }}' }"
    />
    @endcan
</div>