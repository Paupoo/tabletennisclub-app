@props([
    'pageTitle' => __('Page title'),
    'tournamentStatus' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Scripts -->
    @livewireStyles()
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 pb-6">
        @include('layouts.navigation')
        
        <x-header :pageTitle="$pageTitle" :tournamentStatus="$tournamentStatus"/>

        <!-- Page Content -->
        <main>
            {{ $slot }}
        </main>
    </div>
    <x-growler />
    @livewireScriptConfig
</body>

</html>
