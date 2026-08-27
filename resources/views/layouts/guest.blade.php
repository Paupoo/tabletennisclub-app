<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('club.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo-club.svg') }}">
    @if(!empty($description ?? null))
        <meta name="description" content="{{ $description }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @if(app()->environment('production'))
    <script defer src="https://stats.cttottigniesblocry.be/umami-script" data-website-id="9d9befdc-3f9d-4ece-aab7-dc2858457005"></script>
    <script defer src="https://stats.cttottigniesblocry.be/recorder.js" data-website-id="9d9befdc-3f9d-4ece-aab7-dc2858457005" data-sample-rate="0.2" data-mask-level="moderate" data-max-duration="300000"></script>
    @endif
</head>

<body class="bg-white text-gray-900 relative" x-data="{ mobileMenuOpen: false }">

    <div x-data="scrollAnimations">

        <x-public.navigation />

        <main>
            {{ $slot }}
        </main>

        <x-public.footer :club="App\Domains\Competitions\Interclub\Models\Club::own()"/>
    </div>
    @livewireScripts
</body>

</html>