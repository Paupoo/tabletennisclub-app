<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'CTT Ottignies-Blocry' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

</head>

<body class="bg-white text-gray-900 relative" x-data="{ mobileMenuOpen: false }">
    <div x-data="scrollAnimations">

        <x-navigation />

        <main>
            {{ $slot }}
        </main>

        <x-footer />
    </div>
    @livewireScripts
</body>

</html>
