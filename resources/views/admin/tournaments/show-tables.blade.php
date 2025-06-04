<x-app-layout>
    <x-slot name="header">
        {{-- Header --}}
        <div class="flex flex-row gap-2 items-center">
            <x-admin.title :title="$tournament->name" />
            <x-tournament.status-badge :status="$tournament->status" />
            <x-admin.action-menu :tournament="$tournament" :statusesAllowed="$statusesAllowed"/>
        </div>
    </x-slot>

    <x-admin-block>
        <!-- Menu secondaire pour tournoi sélectionné -->
        @include('admin.tournaments.partials.secondary-menu')


<!-- Liste des tables -->
        @include('admin.tournaments.partials.tables')

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

            .grid>div:nth-child(1) { animation-delay: 0.05s; }
            .grid>div:nth-child(2) { animation-delay: 0.1s; }
            .grid>div:nth-child(3) { animation-delay: 0.15s; }
            .grid>div:nth-child(4) { animation-delay: 0.2s; }
            .grid>div:nth-child(5) { animation-delay: 0.25s; }
            .grid>div:nth-child(6) { animation-delay: 0.3s; }
            .grid>div:nth-child(7) { animation-delay: 0.35s; }
            .grid>div:nth-child(8) { animation-delay: 0.4s; }
        </style>

    </x-admin-block>
</x-app-layout>