<nav x-data="{ open: false }" class="bg-white shadow-sm border-b border-gray-200 dark:bg-gray-800 dark:border-gray-700 sticky top-0 z-50">
    <!-- Primary Navigation Menu -->
    <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="flex items-center shrink-0">
                    <a href="{{ route('home') }}" class="flex items-center space-x-2 group">
                        <x-logo class="block w-auto text-club-blue fill-current h-9 dark:text-gray-200 group-hover:text-club-blue-light transition-colors duration-200" />
                        <span class="hidden sm:block ml-4 text-lg font-bold text-club-blue dark:text-gray-200 group-hover:text-club-blue-light transition-colors duration-200">
                            {{ config('app.name', 'Club') }}
                        </span>
                    </a>
                </div>

                <!-- Navigation Links Desktop -->
                <div class="hidden lg:flex lg:items-center lg:ml-8 lg:space-x-1">


                    <!-- Lien Dashboard simple -->
                    <a href="{{ route('dashboard') }}" 
                       class="flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200 text-gray-700 dark:text-gray-300 hover:text-club-blue dark:hover:text-gray-200 hover:bg-indigo-300 dark:hover:bg-gray-700' }}" 
                       wire:navigate>
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        {{ __('Dashboard') }}
                    </a>
                    <!-- Groupe Club Life Management -->
                    <div class="relative" x-data="{ open: false }">
                        <button 
                            @click="open = !open" 
                            @click.away="open = false"
                            class="flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-club-blue dark:hover:text-gray-200 hover:bg-indigo-300 dark:hover:bg-gray-700 rounded-lg transition-all duration-200 group"
                            :class="{'bg-club-blue text-white hover:bg-club-blue-light': open}">
                            <x-ui.icon class="w-4 h-4 mr-3" name="people" />
                            {{ __('Management') }}
                            <svg class="w-4 h-4 ml-1 transition-transform duration-200" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div 
                            x-show="open" 
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute top-full left-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-2 z-50">
                            @if(Auth()->user()->is_committee_member || Auth()->user()->is_admin)
                            <a href="{{ route('admin.articles.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-300 dark:hover:bg-gray-700 hover:text-club-blue transition-colors duration-200" wire:navigate>
                                <svg class="w-4 h-4 mr-3 stroke-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <x-ui.icon name="article" />
                                </svg>
                                {{ __('Articles') }}
                            </a>
                            <a href="{{ route('admin.contacts.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-300 dark:hover:bg-gray-700 hover:text-club-blue transition-colors duration-200" wire:navigate>
                                <svg class="w-4 h-4 mr-3 stroke-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <x-ui.icon name="envelope-closed" />
                                </svg>
                                {{ __('Contacts') }}
                                @if(isset($newContactsCount) && $newContactsCount > 0)
                                    <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">{{ $newContactsCount }}</span>
                                @endif
                            </a>    
                            @endif
                            <a href="{{ route('teams.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-300 dark:hover:bg-gray-700 hover:text-club-blue transition-colors duration-200" wire:navigate>
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <x-ui.icon name="people" />
                                </svg>
                                {{ __('Teams') }}
                            </a>
                            <a href="{{ route('users.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-300 dark:hover:bg-gray-700 hover:text-club-blue transition-colors duration-200" wire:navigate>
                                <svg class="w-4 h-4 mr-3 stroke-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <x-ui.icon name="person" />
                                </svg>
                                {{ __('Members') }}
                            </a>
                            <a href="{{ route('admin.seasons.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-300 dark:hover:bg-gray-700 hover:text-club-blue transition-colors duration-200" wire:navigate>
                                <svg class="w-4 h-4 mr-3 stroke-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <x-ui.icon name="calendar" />
                                </svg>
                                {{ __('Seasons') }}
                            </a>
                            @if(Auth()->user()->is_committee_member || Auth()->user()->is_admin)
                            <a href="{{ route('admin.spams.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-300 dark:hover:bg-gray-700 hover:text-club-blue transition-colors duration-200" wire:navigate>
                                <svg class="w-4 h-4 mr-3 stroke-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <x-ui.icon name="bin" class="w-4 h-4 mr-3" />
                                </svg>
                                {{ __('Spams') }}
                            </a>
                            @endif
                        </div>
                    </div>

                    <!-- Groupe Infrastructure -->
                    @if(Auth()->user()->is_admin || Auth()->user()->is_committee_member)
                    <div class="relative" x-data="{ open: false }">
                        <button 
                            @click="open = !open" 
                            @click.away="open = false"
                            class="flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-club-blue dark:hover:text-gray-200 hover:bg-indigo-300 dark:hover:bg-gray-700 rounded-lg transition-all duration-200"
                            :class="{'bg-club-blue text-white hover:bg-club-blue-light': open}">
                            <x-ui.icon class="w-4 h-4 mr-3" name="building" />
                            {{ __('Infrastructure') }}
                            <svg class="w-4 h-4 ml-1 transition-transform duration-200" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div 
                            x-show="open" 
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute top-full left-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-2 z-50">
                            <a href="{{ route('rooms.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-300 dark:hover:bg-gray-700 hover:text-club-blue transition-colors duration-200" wire:navigate>
                                <x-ui.icon class="w-4 h-4 mr-3" name="building" />
                                {{ __('Rooms') }}
                            </a>
                            <a href="{{ route('tables.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-300 dark:hover:bg-gray-700 hover:text-club-blue transition-colors duration-200" wire:navigate>
                                <x-ui.icon class="w-4 h-4 mr-3" name="table" />
                                {{ __('Tables') }}
                            </a>
                        </div>
                    </div>
                    @endif

                    <!-- Groupe Activités -->
                    <div class="relative" x-data="{ open: false }">
                        <button 
                            @click="open = !open" 
                            @click.away="open = false"
                            class="flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-club-blue dark:hover:text-gray-200 hover:bg-indigo-300 dark:hover:bg-gray-700 rounded-lg transition-all duration-200"
                            :class="{'bg-club-blue text-white hover:bg-club-blue-light': open}">
                            <x-ui.icon class="w-4 h-4 mr-3" name="bolt" />                                
                            {{ __('Activities') }}
                            <svg class="w-4 h-4 ml-1 transition-transform duration-200" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div 
                            x-show="open" 
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="absolute top-full left-0 mt-1 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-2 z-50">
                            <a href="{{ route('admin.events.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-300 dark:hover:bg-gray-700 hover:text-club-blue transition-colors duration-200" wire:navigate>
                                <x-ui.icon class="w-4 h-4 mr-3" name="calendar" />                                
                                {{ __('Events') }}
                            </a>
                            <a href="{{ route('trainings.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-300 dark:hover:bg-gray-700 hover:text-club-blue transition-colors duration-200" wire:navigate>
                                <x-ui.icon class="w-4 h-4 mr-3" name="fire" />                                
                                {{ __('Trainings') }}
                            </a>
                            <a href="{{ route('interclubs.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-300 dark:hover:bg-gray-700 hover:text-club-blue transition-colors duration-200" wire:navigate>
                                <x-ui.icon class="w-4 h-4 mr-3" name="rocket-launch" />   
                                {{ __('Matches') }}
                            </a>
                            <a href="{{ route('tournaments.index') }}" class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-300 dark:hover:bg-gray-700 hover:text-club-blue transition-colors duration-200" wire:navigate>
                                <x-ui.icon class="w-4 h-4 mr-3" name="trophy" />   
                                {{ __('Tournaments') }}
                            </a>
                        </div>
                    </div>                    
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden lg:flex lg:items-center lg:ms-6">
                {{-- <!-- Notifications -->
                <button class="p-2 text-gray-500 hover:text-club-blue hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all duration-200 mr-3 relative">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-5 5v-5zM11 6.5V19c0 .55-.45 1-1 1s-1-.45-1-1V6.5c0-.28-.22-.5-.5-.5s-.5.22-.5.5V19c0 .55-.45 1-1 1s-1-.45-1-1V6.5c0-.28-.22-.5-.5-.5s-.5.22-.5.5V19c0 .55-.45 1-1 1s-1-.45-1-1V6.5c0-.28-.22-.5-.5-.5s-.5.22-.5.5V19c0 .55-.45 1-1 1H3"></path>
                    </svg>
                    <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                </button> --}}

                <x-dropdown align="right" width="64">
                    <x-slot name="trigger">
                        <button class="flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border-0 rounded-lg hover:bg-indigo-300 dark:hover:bg-gray-700 hover:text-club-blue transition-all duration-200 group">
                            <div class="flex items-center space-x-2">
                                <div class="w-8 h-8 bg-club-blue rounded-full flex items-center justify-center">
                                    <span class="text-xs font-medium text-white">
                                        {{ strtoupper(substr(Auth::user()->first_name, 0, 1) . substr(Auth::user()->last_name, 0, 1)) }}
                                    </span>
                                </div>
                                <div class="hidden xl:block text-left">
                                    <div class="text-sm font-medium">{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</div>
                                </div>
                            </div>
                            <svg class="w-4 h-4 ml-2 transition-colors duration-200 group-hover:text-club-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="xl:hidden px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</div>
                        </div>

                        <x-dropdown-link :href="route('profile.edit')" wire:navigate class="flex items-center">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <div class="border-t border-gray-100 dark:border-gray-700">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault(); this.closest('form').submit();"
                                        class="flex items-center text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </div>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="flex items-center lg:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 text-gray-400 hover:text-club-blue hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-all duration-200 focus:outline-none">
                    <svg class="w-6 h-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="lg:hidden border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
        <div class="px-4 pt-2 pb-3 space-y-1">
            <!-- Dashboard -->
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate class="flex items-center">
                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            <!-- Section Management -->
            <div class="pt-4 pb-2">
                <div class="px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {{ __('Management') }}
                </div>
            </div>
            @if(Auth()->user()->is_committee_member || Auth()->user()->is_admin)
            <x-responsive-nav-link :href="route('admin.articles.index')" :active="request()->routeIs('admin.articles.index')" wire:navigate class="flex items-center pl-6">
                <x-ui.icon class="w-4 h-4 mr-3" name="article" />
                {{ __('Articles') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.contacts.index')" :active="request()->routeIs('admin.contacts.index')" wire:navigate class="flex items-center pl-6">
                <x-ui.icon class="w-4 h-4 mr-3" name="envelope-closed" />
                {{ __('Contacts') }}
                @if(isset($newContactsCount) && $newContactsCount > 0)
                    <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">{{ $newContactsCount }}</span>
                @endif
            </x-responsive-nav-link>
            @endif
            <x-responsive-nav-link :href="route('teams.index')" :active="request()->routeIs('teams.index')" wire:navigate class="flex items-center pl-6">
                <x-ui.icon class="w-4 h-4 mr-3" name="people" />
                {{ __('Teams') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.index')" wire:navigate class="flex items-center pl-6">
                <x-ui.icon class="w-4 h-4 mr-3" name="person" />
                {{ __('Members') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.seasons.index')" :active="request()->routeIs('users.index')" wire:navigate class="flex items-center pl-6">
                <x-ui.icon class="w-4 h-4 mr-3" name="calendar" />
                {{ __('Seasons') }}
            </x-responsive-nav-link>
            @if(Auth()->user()->is_committee_member || Auth()->user()->is_admin)
            <x-responsive-nav-link :href="route('admin.spams.index')" :active="request()->routeIs('admin.spams.index')" wire:navigate class="flex items-center pl-6">
                <x-ui.icon class="w-4 h-4 mr-3" name="bin" />
                {{ __('Spams') }}
            </x-responsive-nav-link>
            @endif

            <!-- Section Infrastructure -->
            @if(Auth()->user()->is_admin || Auth()->user()->is_committee_member)
            <div class="pt-4 pb-2">
                <div class="px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {{ __('Infrastructure') }}
                </div>
            </div>
            <x-responsive-nav-link :href="route('rooms.index')" :active="request()->routeIs('rooms.index')" wire:navigate class="flex items-center pl-6">
                <x-ui.icon class="w-3 h-3 mr-3" name="building" />
                {{ __('Rooms') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('tables.index')" :active="request()->routeIs('tables.index')" wire:navigate class="flex items-center pl-6">
                <x-ui.icon class="w-3 h-3 mr-3" name="table" />
                {{ __('Tables') }}
            </x-responsive-nav-link>
            @endif

            <!-- Section Activités -->
            <div class="pt-4 pb-2">
                <div class="px-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {{ __('Activities') }}
                </div>
            </div>
            <x-responsive-nav-link :href="route('admin.events.index')" :active="request()->routeIs('trainings.index')" wire:navigate class="flex items-center pl-6">
                <x-ui.icon class="w-4 h-4 mr-3" name="calendar" />
                {{ __('Events') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('trainings.index')" :active="request()->routeIs('trainings.index')" wire:navigate class="flex items-center pl-6">
                <x-ui.icon class="w-4 h-4 mr-3" name="fire" />
                {{ __('Trainings') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('interclubs.index')" :active="request()->routeIs('interclubs.index')" wire:navigate class="flex items-center pl-6">
                <x-ui.icon class="w-4 h-4 mr-3" name="rocket-launch" />
                {{ __('Matches') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('tournaments.index')" :active="request()->routeIs('tournaments.index')" wire:navigate class="flex items-center pl-6">
                <x-ui.icon class="w-4 h-4 mr-3" name="trophy" />
                {{ __('Tournaments') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="px-4 mb-3">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-club-blue rounded-full flex items-center justify-center">
                        <span class="text-sm font-medium text-white">
                            {{ strtoupper(substr(Auth::user()->first_name, 0, 1) . substr(Auth::user()->last_name, 0, 1)) }}
                        </span>
                    </div>
                    <div>
                        <div class="text-base font-medium text-gray-800 dark:text-gray-200">{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}</div>
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</div>
                    </div>
                </div>
            </div>

            <div class="px-4 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')" wire:navigate class="flex items-center">
                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault(); this.closest('form').submit();"
                            class="flex items-center text-red-600">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>