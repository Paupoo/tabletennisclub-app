@props(['tournament', 'statusesAllowed', 'pageTitle' => null])

<x-app-layout :pageTitle="$pageTitle ?? $tournament->name" :tournamentStatus="$tournament->status">
  
    <!-- Contenu principal -->
    <x-admin-block>
        <!-- Menu de navigation secondaire pour les tournois -->
        <x-tournament.secondary-nav :tournament="$tournament" />
        {{ $slot }}
    </x-admin-block>

    <!-- Modals communes (si nécessaire) -->
    @stack('modals')
</x-app-layout>
