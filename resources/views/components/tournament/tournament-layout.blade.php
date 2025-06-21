@props([
    'tournament',
    'statusesAllowed',
    'breadcrumbs' => []
    ])

<x-app-layout :breadcrumbs="$breadcrumbs">
  
    <!-- Contenu principal -->
    <x-admin-block>
        <!-- Menu de navigation secondaire pour les tournois -->
        <x-tournament.secondary-nav :tournament="$tournament"/>
        {{ $slot }}
    </x-admin-block>

    <!-- Modals communes (si nécessaire) -->
    @stack('modals')
</x-app-layout>
