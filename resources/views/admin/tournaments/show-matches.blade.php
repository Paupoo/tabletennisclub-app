<x-tournament.tournament-layout :tournament="$tournament" :statusesAllowed="$statusesAllowed">

    @include('admin.tournaments.partials.matches-list')


    @push('modals')
    @endpush
</x-tournament.tournament-layout>