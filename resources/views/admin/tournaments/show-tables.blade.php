<x-tournament.tournament-layout :tournament="$tournament" :statusesAllowed="$statusesAllowed">

    @include('admin.tournaments.partials.tables')


    @push('modals')
    @endpush
</x-tournament.tournament-layout>


<style>
    /* Animations pour les tables */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .grid>div {
        animation: fadeIn 0.6s ease-out forwards;
    }

    .grid>div:nth-child(1) {
        animation-delay: 0.05s;
    }

    .grid>div:nth-child(2) {
        animation-delay: 0.1s;
    }

    .grid>div:nth-child(3) {
        animation-delay: 0.15s;
    }

    .grid>div:nth-child(4) {
        animation-delay: 0.2s;
    }

    .grid>div:nth-child(5) {
        animation-delay: 0.25s;
    }

    .grid>div:nth-child(6) {
        animation-delay: 0.3s;
    }

    .grid>div:nth-child(7) {
        animation-delay: 0.35s;
    }

    .grid>div:nth-child(8) {
        animation-delay: 0.4s;
    }
</style>
