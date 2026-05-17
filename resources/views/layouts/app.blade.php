@php
    $user = Auth::user();
@endphp
<!DOCTYPE html>
<html data-db-theme="{{ $user->theme ?? 'auto' }}"
    lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover" name="viewport">
    <meta content="{{ csrf_token() }}" name="csrf-token">
    <title>{{ isset($title) ? config('app.name') . ' - ' . $title : config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @if(app()->environment('production'))
    <script defer src="https://stats.cttottigniesblocry.be/umami-script" data-website-id="9d9befdc-3f9d-4ece-aab7-dc2858457005"></script>
    <script defer src="https://stats.cttottigniesblocry.be/recorder.js" data-website-id="9d9befdc-3f9d-4ece-aab7-dc2858457005" data-sample-rate="0.2" data-mask-level="moderate" data-max-duration="300000"></script>
    @endif
</head>

<body class="bg-base-200 min-h-screen font-sans antialiased" x-data="{
    dbTheme: '{{ App\Models\ClubAdmin\Users\User::first()->theme ?? 'auto' }}',
    init() {
        let currentTheme = localStorage.getItem('theme') || this.dbTheme;
        this.updateTheme(currentTheme);
    },

    updateTheme(theme) {
        if (theme === 'auto') {
            localStorage.removeItem('theme');
            const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', systemTheme);
        } else {
            localStorage.setItem('theme', theme);
            document.documentElement.setAttribute('data-theme', theme);
        }
    }
}"
    x-on:set-theme.window="updateTheme($event.detail.theme)">

    {{-- NAVBAR mobile only --}}
    <x-nav class="lg:hidden" sticky>
        <x-slot:brand>
            <x-app-brand />
        </x-slot:brand>
        <x-slot:actions>
            <label class="me-3 lg:hidden" for="main-drawer">
                <x-icon class="cursor-pointer" name="o-bars-3" />
            </label>
        </x-slot:actions>
    </x-nav>

    {{-- MAIN --}}
    <x-main>
        {{-- SIDEBAR --}}
        <x-slot:sidebar class="bg-base-100 lg:bg-inherit" collapsible drawer="main-drawer">

            {{-- BRAND --}}
            <x-app-brand class="px-5 pt-4" />

            {{-- MENU --}}
            <x-admin.navigation :user="$user" />

        </x-slot:sidebar>

        {{-- The `$slot` goes here --}}
        <x-slot:content>
            <div class="mb-10 mt-2 flex items-center justify-between">
               {{ $breadcrumbs ?? null }}
            </div>

            {{ $slot }}

        </x-slot:content>

    </x-main>

    {{-- TOAST area --}}
    <x-toast position="toast-bottom toast-start" />
    @livewireScripts
</body>

</html>
