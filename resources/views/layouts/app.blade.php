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

                    <x-menu-item icon="o-user" link="{{ route('admin.user.profile', $user) }}"
                        title="{{ __('My profile') }}" />
                    <x-menu-item icon="o-users" link="{{ route('admin.user.teams', $user) }}" title="{{ __('My team(s)') }}" />
                    <x-menu-item icon="o-star" link="{{ route('admin.user.event-subscription', $user) }}" title="{{ __('My registrations') }}" />
                    <x-menu-item icon="o-calendar-days" link="{{ route('admin.user.calendar', $user) }}" title="{{ __('Calendar') }}" />
                    <x-menu-item icon="o-credit-card" link="{{ route('admin.user.registration-management', $user) }}" title="{{ __('Affiliation') }}" />
                    <x-menu-item disabled icon="o-lock-closed" title="{{ __('Affiliation') }}" />
                    <x-menu-item icon="o-cog-8-tooth" :link="route('admin.user.settings', $user)" title="{{ __('Settings') }}" />
                    <x-menu-separator />
                    <livewire:actions.logout />
                </x-menu-sub>

                <x-menu-separator />

                <x-menu-sub icon="o-building-office" title="{{ __('Infrastructure') }}">
                    <x-menu-item icon="o-identification" link="" title="{{ __('Club info') }}" />
                    <x-menu-item icon="o-building-office-2" link="" title="{{ __('Rooms') }}" />
                    <x-menu-item icon="o-squares-2x2" link="" title="{{ __('Tables') }}" />
                    {{-- <x-menu-item title="Archives" icon="o-archive-box" link="####" /> --}}
                </x-menu-sub>

                <x-menu-sub icon="o-inbox-stack" title="{{ __('Members Admin') }}">
                    <x-menu-item icon="o-users" link="{{ route('admin.users.index') }}" title="{{ __('Users') }}" />
                    <x-menu-item icon="o-credit-card" link="{{  route('admin.users.registrations') }}" title="{{ __('Registrations') }}" />
                    <x-menu-item icon="o-banknotes" link="{{ route('admin.users.payments') }}" title="{{ __('Payments') }}" />
                </x-menu-sub>
                <x-menu-sub icon="o-cog-6-tooth" link="#" title="{{ __('Club') }}">
                </x-menu-sub>

                <x-menu-separator />

                <x-menu-item icon="o-academic-cap" link="{{ route('admin.trainings.index') }}" title="{{ __('Trainings') }}" />

                <x-menu-sub icon="o-calendar-days" link="#" title="{{ __('Interclubs') }}">
                    <x-menu-item icon="o-identification" link="" title="{{ __('Teams') }}" />
                    <x-menu-item icon="o-user-group" link="{{ route('admin.interclubs.captain-selection') }}" title="{{ __('Selections') }}" />
                    <x-menu-item icon="o-eye" link="{{ route('admin.interclubs.control-center') }}" title="{{ __('Control Center') }}" />
                    <x-menu-item icon="o-squares-2x2" link="" title="{{ __('Results') }}" />
                    {{-- <x-menu-item title="Archives" icon="o-archive-box" link="####" /> --}}
                </x-menu-sub>

                <x-menu-sub icon="o-star" title="{{ __('Events') }}">
                    <x-menu-item icon="o-trophy" link="{{ route('admin.tournaments.index') }}" title="{{ __('Tournaments') }}">
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
