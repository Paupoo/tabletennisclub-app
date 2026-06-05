<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Installation') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased">

    <div class="min-h-screen bg-base-200">

        <div class="flex flex-col items-center justify-start min-h-screen px-4 py-12">

            {{-- Logo --}}
            <div class="mb-8 flex flex-col items-center">
                <a href="/" class="inline-block">
                    <x-logo class="w-20 h-20 fill-primary transition-colors duration-300" />
                </a>
                <p class="mt-3 text-sm font-medium text-base-content/50 uppercase tracking-widest">
                    {{ __('Setup wizard') }}
                </p>
            </div>

            {{-- Main card --}}
            <div class="w-full max-w-3xl">
                <div class="bg-base-100 shadow-xl rounded-2xl border border-base-300/50 overflow-hidden">

                    {{-- Colour accent stripe --}}
                    <div class="h-2 bg-gradient-to-r from-club-blue to-club-yellow"></div>

                    {{-- Content --}}
                    <div class="px-8 py-8">
                        {{ $slot }}
                    </div>

                </div>
            </div>

        </div>

        {{-- Decorative blobs --}}
        <div class="fixed inset-0 pointer-events-none overflow-hidden -z-10">
            <div class="absolute -top-40 -right-40 w-80 h-80 rounded-full bg-primary/5"></div>
            <div class="absolute -bottom-40 -left-40 w-80 h-80 rounded-full bg-secondary/5"></div>
        </div>

    </div>

    @livewireScripts
    <x-toast />
</body>
</html>
