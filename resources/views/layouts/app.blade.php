<!DOCTYPE html>
<html data-db-theme="{{ App\Models\ClubAdmin\Users\User::first()->theme ?? 'auto' }}"
    lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover" name="viewport">
    <meta content="{{ csrf_token() }}" name="csrf-token">
    <title>{{ isset($title) ? $title . ' - ' . config('app.name') : config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body class="bg-base-200 min-h-screen font-sans antialiased" x-data="{
    updateTheme(theme) {
        if (theme === 'auto') {
            localStorage.removeItem('theme');
            // On détecte le thème système actuel
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

        @php

            $user = App\Models\ClubAdmin\Users\User::firstOrFail();
        @endphp

        {{-- SIDEBAR --}}
        <x-slot:sidebar class="bg-base-100 lg:bg-inherit" collapsible drawer="main-drawer">

            {{-- BRAND --}}
            <x-app-brand class="px-5 pt-4" />

            {{-- MENU --}}
            <x-menu activate-by-route class="mt-10">
                <x-menu-sub icon="o-user" title="{{ $user->first_name }}">
                    {{-- L'avatar et l'email s'affichent mieux ici dans un menu-item spécial ou le titre du sub-menu --}}
                    <x-slot:title>
                        <div class="flex items-center gap-3">
                            <div class="overflow-hidden truncate">
                                <div class="truncate font-bold">{{ $user->first_name }}</div>
                                <div class="truncate text-[10px] opacity-50">{{ $user->email }}</div>
                            </div>
                        </div>
                    </x-slot:title>

                    <x-menu-item icon="o-user" link="{{ route('profile.edit', $user) }}"
                        title="{{ __('My profile') }}" />
                    <x-menu-item icon="o-users" link="#" title="{{ __('My team(s)') }}" />
                    <x-menu-item icon="o-star" link="#" title="{{ __('My registrations') }}" />
                    <x-menu-item icon="o-calendar-days" link="#" title="{{ __('Calendar') }}" />
                    <x-menu-item icon="o-credit-card" link="#" title="{{ __('Affiliation') }}" />
                    <x-menu-item disabled icon="o-lock-closed" title="{{ __('Affiliation') }}" />
                    <x-menu-item icon="o-cog-8-tooth" link="#" title="{{ __('Settings') }}" />
                    <x-menu-separator />
                    <x-menu-item class="text-error" icon="o-power" link="#" no-wire-navigate
                        title="{{ __('Logout') }}" />
                </x-menu-sub>

                <x-menu-separator />

                <x-menu-sub icon="o-building-office" title="{{ __('Infrastructure') }}">
                    <x-menu-item icon="o-identification" link="" title="{{ __('Club info') }}" />
                    <x-menu-item icon="o-building-office-2" link="" title="{{ __('Rooms') }}" />
                    <x-menu-item icon="o-squares-2x2" link="" title="{{ __('Tables') }}" />
                    {{-- <x-menu-item title="Archives" icon="o-archive-box" link="####" /> --}}
                </x-menu-sub>

                <x-menu-sub icon="o-inbox-stack" title="{{ __('Members Admin') }}">
                    <x-menu-item icon="o-users" link="" title="{{ __('Users') }}" />
                    <x-menu-item icon="o-credit-card" link="" title="{{ __('Registrations') }}" />
                    <x-menu-item icon="o-banknotes" link="" title="{{ __('Payments') }}" />
                </x-menu-sub>
                <x-menu-sub icon="o-cog-6-tooth" link="#" title="{{ __('Club') }}">
                </x-menu-sub>

                <x-menu-separator />

                <x-menu-item icon="o-academic-cap" link="" title="{{ __('Trainings') }}" />

                <x-menu-sub icon="o-calendar-days" link="#" title="{{ __('Interclubs') }}">
                    <x-menu-item icon="o-identification" link="" title="{{ __('Teams') }}" />
                    <x-menu-item icon="o-user-group" link="" title="{{ __('Selections') }}" />
                    <x-menu-item icon="o-eye" link="" title="{{ __('Overview') }}" />
                    <x-menu-item icon="o-squares-2x2" link="" title="{{ __('Results') }}" />
                    {{-- <x-menu-item title="Archives" icon="o-archive-box" link="####" /> --}}
                </x-menu-sub>

                <x-menu-sub icon="o-star" title="{{ __('Events') }}">
                    <x-menu-item icon="o-trophy" link="" title="{{ __('Tournaments') }}">
                    </x-menu-item>
                </x-menu-sub>

                <x-menu-separator />

                <x-menu-sub icon="o-globe-alt" link="#" title="{{ __('Website') }}">
                    <x-menu-item icon="o-envelope-open" link="#" title="{{ __('Contacts') }}" />
                    <x-menu-item icon="o-newspaper" link="#" title="{{ __('News') }}" />
                </x-menu-sub>

                <x-menu-separator />

            </x-menu>

        </x-slot:sidebar>

        {{-- The `$slot` goes here --}}
        <x-slot:content>
            <div class="mb-10 mt-2 flex items-center justify-between">
                @if (isset($breadcrumbs))
                    {{ $breadcrumbs }}
                @endif
            </div>

            {{ $slot }}

        </x-slot:content>

    </x-main>

    {{-- TOAST area --}}
    <x-toast position="toast-bottom toast-start" />
</body>

</html>
