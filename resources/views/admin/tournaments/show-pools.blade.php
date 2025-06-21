<x-tournament.tournament-layout :breadcrumbs="$breadcrumbs" :tournament="$tournament" :statusesAllowed="$statusesAllowed">

        {{-- actions menu --}}
    @push('header-actions')
    <x-tournament.actions-menu :tournament="$tournament" :statusesAllowed="$statusesAllowed ?? []">
            @include('admin.tournaments.partials.action-menu')
        </x-tournament.actions-menu>
    @endpush

    @include('admin.tournaments.partials.pools')

    @push('modals')
    
    @endpush
</x-tournament.tournament-layout>
