 <nav x-data="{ open: false }" class="bg-white border-gray-100 dark:bg-gray-800 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="mb-4 mx-auto max-w-7xl">
        <div class="flex justify-between ">
            <div class="flex flex-wrap gap-2 hidden sm:block">
                <!-- Navigation Links -->
                    <x-sub-nav-link 
                        :href="route('tournamentShow', $tournament)"
                        :active="request()->routeIs('tournamentShow')"
                        :iconName="'details'">
                            {{ __('Details') }}
                    </x-sub-nav-link>
                    <x-sub-nav-link 
                        :href="route('tournamentShowPlayers', $tournament)"
                        :active="request()->routeIs('tournamentShowPlayers')"
                        :iconName="'people'">
                            {{ __('Players') }}
                            <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800">
                                {{ $tournament->users()->count() }} / 
                                {{ $tournament->max_users }}
                            </span>
                    </x-sub-nav-link>
                    <x-sub-nav-link 
                        :href="route('tournamentShowPools', $tournament)"
                        :active="request()->routeIs('tournamentShowPools')"
                        :iconName="'files'">
                            {{ __('Pools') }}
                            <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">
                                {{ $tournament->pools()->count() }}
                            </span>
                    </x-sub-nav-link>
                    <x-sub-nav-link
                        :href="route('tournamentShowMatches', $tournament)"
                        :active="request()->routeIs('tournamentShowMatches')"
                        :iconName="'list'">
                            {{ __('Matches') }}
                            <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">
                                {{ $tournament->matches()->where('status', 'completed')->count() }} / 
                                {{ $tournament->matches()->count() }}
                            </span>
                    </x-sub-nav-link>
                    <x-sub-nav-link
                        :href="route('tournamentShowTables', $tournament)"
                        :active="request()->routeIs('tournamentShowTables')"
                        :iconName="'table'">
                            {{ __('Tables') }}
                            <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">
                                {{ $tournament->tables()->where('is_table_free', false)->count() }} / 
                                {{ count($tournament->tables) }}
                            </span>
                    </x-sub-nav-link>
                    <x-sub-nav-link class="text-red-700"
                        :href="'#'"
                        :active="''"
                        :iconName="'graph'">
                            {{ __('Standings (TO DO)') }}
                    </x-sub-nav-link>
                
            </div>

            <!-- Hamburger -->
            <div class="flex items-center -me-2 sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 text-gray-400 transition duration-150 ease-in-out rounded-md dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400">
                    <svg class="w-6 h-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
                <div class="ml-2 text-sm">
                    Menu
                </div>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('tournamentShow', $tournament)" :active="request()->routeIs('tournamentShow')">
                {{ __('Details') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('tournamentShowPlayers', $tournament)" :active="request()->routeIs('tournamentShowPlayers')">
                {{ __('Players') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('tournamentShowPools', $tournament)" :active="request()->routeIs('tournamentShowPools')">
                {{ __('Pools') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('tournamentShowMatches', $tournament)" :active="request()->routeIs('tournamentShowMatches')">
                {{ __('Matches') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('tournamentShowTables', $tournament)" :active="request()->routeIs('tournamentShowTables')">
                {{ __('Tables') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('welcome')" :active="request()->routeIs('welcome')">
                {{ __('Standings (TO DO)') }}
            </x-responsive-nav-link>
        </div>
    </div>
</nav>
        <div class="border-t border-gray-300 my-6"></div>