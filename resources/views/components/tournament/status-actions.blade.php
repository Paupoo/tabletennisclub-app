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
            variant="default" 
            icon="edit" 
            tooltip="Modifier"
            onclick="window.location.href='{{ route('tournament.edit', $tournament) }}'"
        />
    @endif

    @if($tournament->users->contains(auth()->user()->id) && $tournament->status === App\Enums\TournamentStatusEnum::PUBLISHED)
    <a href="/admin/tournament/unregister/{{$tournament->id}}/{{auth()->user()->id}}">
        <x-ui.action-button 
        variant="warning" 
        icon="leave" 
        tooltip="{{ __('Register to tournament') }}"
        />
    </a>
    @endif
    @if(!$tournament->users->contains(auth()->user()->id) && $tournament->status === App\Enums\TournamentStatusEnum::PUBLISHED)
        <a href="/admin/tournament/register/{{$tournament->id}}/{{auth()->user()->id}}">
            <x-ui.action-button 
            variant="default" 
            icon="join" 
            tooltip="{{ __('Register to tournament') }}"
            />
        </a>
    @endif
    <x-ui.action-button 
        variant="danger" 
        icon="delete" 
        tooltip="Supprimer"
        onclick="if(confirm('Êtes-vous sûr de vouloir supprimer ce tournoi ?')) { window.location.href='{{ route('deleteTournament', $tournament) }}' }"
    />
</div>