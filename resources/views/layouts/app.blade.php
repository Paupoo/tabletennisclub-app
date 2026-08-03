@php
    $user = Auth::user();
@endphp
<!DOCTYPE html>
<html data-db-theme="{{ $user->theme ?? 'auto' }}"
    lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    {{-- No maximum-scale: pinch zoom is the only magnification a member has (WCAG 1.4.4).
    The overflow-x-hidden on <body> below is what keeps Firefox mobile from widening the
    layout viewport, so capping the scale was never what held that together. --}}
    <meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport">
    <meta content="{{ csrf_token() }}" name="csrf-token">
    <title>{{ isset($title) ? config('app.name') . ' - ' . $title : config('app.name') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo-club.svg') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @if(app()->environment('production'))
    <script defer src="https://stats.cttottigniesblocry.be/umami-script" data-website-id="9d9befdc-3f9d-4ece-aab7-dc2858457005"></script>
    <script defer src="https://stats.cttottigniesblocry.be/recorder.js" data-website-id="9d9befdc-3f9d-4ece-aab7-dc2858457005" data-sample-rate="0.2" data-mask-level="moderate" data-max-duration="300000"></script>
    @endif
</head>

{{-- overflow-x-hidden: any page content wider than the screen must clip, not create
horizontal scroll — otherwise Firefox mobile widens the layout viewport and every
position:fixed overlay (notification sheet, drawers) gets cropped on the right. --}}
<body class="bg-base-200 min-h-screen overflow-x-hidden font-sans antialiased" x-data="{
    dbTheme: '{{ $user->theme ?? 'auto' }}',
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
            @auth
                <livewire:admin.notification-bell />
            @endauth
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

    {{-- Session flash → Mary toast bridge: controllers redirecting with
         ->with('success'|'error', …) surface as the same toasts Livewire uses. --}}
    @if (session('success') || session('error'))
        @php
            $flashToast = Illuminate\Support\Js::from(['toast' => [
                'title' => session('success') ?? session('error'),
                'css' => session()->has('success') ? 'alert-success' : 'alert-error',
                'icon' => svg(session()->has('success') ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle', 'w-7 h-7')->toHtml(),
                'timeout' => 3000,
                'position' => 'toast-bottom toast-start',
            ]]);
        @endphp
        <div x-data x-init="toast({{ $flashToast }})"></div>
    @endif
    @livewireScripts
</body>

</html>