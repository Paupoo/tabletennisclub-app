<x-tournament.tournament-layout :tournament="$tournament" :statusesAllowed="$statusesAllowed">

    @livewire('tournament.registered-players', ['tournament' => $tournament])

    @push('modals')
    
    @endpush
</x-tournament.tournament-layout>
