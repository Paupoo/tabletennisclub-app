<x-tournament.tournament-layout :tournament="$tournament" :statusesAllowed="$statusesAllowed">

    @include('admin.tournaments.partials.pools')

    @push('modals')
    
    @endpush
</x-tournament.tournament-layout>
