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


        <!-- Contenu de l'onglet: Joueurs inscrits -->
        @include('admin.tournaments.partials.registered-players')

    </x-admin-block>
</x-app-layout>