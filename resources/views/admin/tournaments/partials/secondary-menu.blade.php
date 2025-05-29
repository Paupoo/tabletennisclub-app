<!-- Menu secondaire pour tournoi sélectionné -->
<div class="lg-mx-auto max-w-7xl sm:px-6 lg:px-8">
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('tournamentShow', $tournament) }}"
            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
            <x-ui.icon name="details" class="mr-2" />
            Détails
        </a>

        <a href="{{ route('tournamentShowPlayers', $tournament) }}"
            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            {{ __('Players') }}
            <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800">
                {{ $tournament->users()->count() }}
            </span>
        </a>

        <a href="{{ route('tournamentShowPools', $tournament) }}"
            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            Poules<span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">
                {{ count($tournament->pools) }}
            </span>
        </a>

        <a href="{{ route('tournamentShowMatches', $tournament) }}"
            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 6h16M4 10h16M4 14h16M4 18h16" />
            </svg>
            Matches<span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">
                {{ count($matches) }}
            </span>
        </a>

        <a href="{{ route('tournamentShowTables', $tournament) }}"
            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="3" x2="12" y2="21" />
                <line x1="4" y1="7" x2="20" y2="7" />
                <line x1="9" y1="21" x2="15" y2="21" />
                <circle cx="7" cy="14" r="3" />
                <circle cx="17" cy="14" r="3" />
                <line x1="7" y1="7" x2="7" y2="11" />
                <line x1="17" y1="7" x2="17" y2="11" />
            </svg>
            Tables
            <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">
                {{ count($tournament->tables) }}
            </span>
        </a>

        <a href="#"
            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-red-700 bg-white border border-gray-300 hover:bg-gray-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            Résultats
        </a>

        <a href="#"
            class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-red-700 bg-white border border-gray-300 hover:bg-gray-50">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            Classement
        </a>
    </div>
</div>

<!-- Séparateur pour montrer la différence -->
<div class="border-t border-gray-300 my-6"></div>