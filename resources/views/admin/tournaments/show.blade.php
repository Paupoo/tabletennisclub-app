<x-app-layout>


    <div class="container mx-auto px-4 py-8">
        <!-- En-tête du tournoi -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="p-6">
                <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6">
                    <div class="flex flex-row items-center gap-4">
                        <h1 class="text-2xl font-bold text-gray-800 mb-4 md:mb-0">{{ $tournament->name }}</h1>
                        <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded-full">En
                            cours</span>
                    </div>

                    <!-- Menu d'actions -->
                    <div class="mt-4 md:mt-0 flex flex-wrap gap-3" x-data="{ showMenu: false }">
                        <!-- Bouton principal avec dropdown -->
                        <div class="relative">
                            <button @click="showMenu = !showMenu"
                                class="flex items-center justify-between bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md transition duration-150 ease-in-out">
                                <span class="mr-2">Actions</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>

                            <!-- Menu Dropdown -->
                            <div x-show="showMenu" @click.away="showMenu = false"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-50">
                                <div class="py-1">
                                    {{-- <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        Voir les détails
                                    </a> --}}
                                    <a href="#" @click="showModal = true"
                                        class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                        Inscrire un joueur
                                    </a>
                                    <a href="{{ route('tournamentSetup', $tournament) }}"
                                        class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Modifier
                                    </a>
                                    <a href="#"
                                        class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                                        </svg>
                                        Dupliquer
                                    </a>
                                    <div class="border-t border-gray-200"></div>
                                    <a href="{{ route('deleteTournament', $tournament) }}"
                                        class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-gray-100 hover:text-red-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-red-500"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Supprimer
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Boutons d'accès rapide -->
                        <a href="#"
                            class="flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md transition duration-150 ease-in-out">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-white" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8h18M3 8v2a1 1 0 001 1h16a1 1 0 001-1V8M6 8v8m12-8v8" />
                            </svg>
                            Tables
                        </a>

                        <a href="{{ route('knockoutBracket', $tournament) }}"
                            class="flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md transition duration-150 ease-in-out">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            Tableau des scores
                        </a>
                        <a href="{{ route('tournamentsIndex') }}" <x-primary-button>{{ __('Tournaments list') }}</x-primary-button></a>
                    </div>
                </div>


            </div>

            <!-- Menu secondaire pour tournoi sélectionné -->
            <div class="bg-white p-4 rounded-lg shadow mb-6">

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('tournamentSetup', $tournament) }}"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        Détails
                    </a>

                    <a href="#"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        Joueurs
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800">
                            {{ $tournament->users()->count() }}
                        </span>
                    </a>

                    <a href="#"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        Poules<span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">
                            {{ count($tournament->pools) }}
                        </span>
                    </a>

                    <a href="#"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                        Matches<span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">
                            {{ count($matches) }}
                        </span>
                    </a>

                    <a href="#"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
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
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        Résultats
                    </a>

                    <a href="#"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Classement
                    </a>


                </div>
            </div>

            <!-- Informations sur le tournoi -->
            <div class="bg-gray-50 rounded-lg p-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex items-center">
                        <div class="rounded-full bg-purple-100 p-3 mr-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="text-gray-500">
                            <p class="text-sm text-gray-500">{{ __('Hours') }}</p>
                            <p class="font-bold text-lg">{{ $tournament->start_date->format('d/m/Y') }} |
                                {{ $tournament->start_date->format('H:i') }} -
                                {{ $tournament->end_date->format('H:i') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="rounded-full bg-red-100 p-3 mr-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div class="text-gray-500">
                            <p class="text-sm">{{ __('Registered Players') }}</p>
                            <p class="font-bold text-lg">{{ $tournament->total_users }}/{{ $tournament->max_users }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="rounded-full bg-orange-100 p-3 mr-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div class="text-gray-500">
                            <p class="text-sm">{{ __('Rooms') }}</p>
                            @foreach ($tournament->rooms as $room)
                                <p class="font-bold text-lg">{{ $room->name }}</p>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="rounded-full bg-green-100 p-3 mr-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8h18M3 8v2a1 1 0 001 1h16a1 1 0 001-1V8M6 8v8m12-8v8" />
                            </svg>
                        </div>
                        <div class="text-gray-500">
                            <p class="text-sm">{{ __('Number of tables') }}</p>
                            <p class="font-bold text-lg">{{ $tournament->tables->count() }}</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="rounded-full bg-violet-100 p-3 mr-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-violet-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="text-gray-500">
                            <p class="text-sm">{{ __('Number of pools') }}</p>
                            <p class="font-bold text-lg">{{ $tournament->pools->count() }}</p>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <div class="rounded-full bg-yellow-100 p-3 mr-3">
                            <!-- SVG de la balance ici -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <line x1="12" y1="3" x2="12" y2="21" />
                                <line x1="4" y1="7" x2="20" y2="7" />
                                <line x1="9" y1="21" x2="15" y2="21" />
                                <circle cx="7" cy="14" r="3" />
                                <circle cx="17" cy="14" r="3" />
                                <line x1="7" y1="7" x2="7" y2="11" />
                                <line x1="17" y1="7" x2="17" y2="11" />
                            </svg>
                        </div>
                        <div class="text-gray-500">
                            <p class="text-sm">Handicap Points</p>
                            <p class="font-bold text-lg">{{ $tournament->has_handicap_points ? __('Yes') : __('No') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Messages de succès -->
    {{-- @if (session()->has('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-8" role="alert">
                <p>{{ session()->get('success') }}</p>
            </div>
        @elseif (session()->has('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-8" role="alert">
                <p>{{ session()->get('error') }}</p>
            </div>
        @endif --}}


    <!-- Système de navigation par onglets -->
    <div class="m-auto w-5/6 mb-8 bg-white rounded-lg shadow-lg overflow-hidden" x-data="{ activeTab: 'registered' }">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
                <button id="tab-registered" @click="activeTab = 'registered'"
                    :class="{ 'border-blue-500 text-blue-600 font-semibold': activeTab === 'registered', 'text-gray-500 border-transparent': activeTab !== 'registered' }"
                    class="tab-button px-6 py-4 border-b-2 font-medium leading-5 transition duration-150 ease-in-out">
                    Joueurs inscrits
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800">
                        {{ $tournament->users()->count() }}
                    </span>
                </button>

                <button id="tab-pools" @click="activeTab = 'pools'"
                    :class="{ 'border-blue-500 text-blue-600 font-semibold': activeTab === 'pools', 'text-gray-500 border-transparent': activeTab !== 'pools' }"
                    class="tab-button px-6 py-4 border-b-2 font-medium leading-5 hover:text-gray-700 hover:border-gray-300 transition duration-150 ease-in-out">
                    Liste des poules
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">
                        {{ $tournament->pools->count() }}
                    </span>
                </button>
                <button id="tab-matches" @click="activeTab = 'matches'"
                    :class="{ 'border-blue-500 text-blue-600 font-semibold': activeTab === 'matches', 'text-gray-500 border-transparent': activeTab !== 'matches' }"
                    class="tab-button px-6 py-4 border-b-2 font-medium leading-5 hover:text-gray-700 hover:border-gray-300 transition duration-150 ease-in-out">
                    Liste des matches
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">
                        {{ count($matches) }}
                    </span>
                </button>
                <button id="tab-tables" @click="activeTab = 'tables'"
                    :class="{ 'border-blue-500 text-blue-600 font-semibold': activeTab === 'tables', 'text-gray-500 border-transparent': activeTab !== 'tables' }"
                    class="tab-button px-6 py-4 border-b-2 font-medium leading-5 hover:text-gray-700 hover:border-gray-300 transition duration-150 ease-in-out">
                    Liste des tables
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">
                        {{ count($tournament->tables()->get()) }}
                    </span>
                </button>
                <button id="tab-config" @click="activeTab = 'config'"
                    :class="{ 'border-blue-500 text-blue-600 font-semibold': activeTab === 'config', 'text-gray-500 border-transparent': activeTab !== 'config' }"
                    class="tab-button px-6 py-4 border-b-2 font-medium leading-5 transition duration-150 ease-in-out">
                    Configuration
                    {{-- <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800">
                {{ $tournament->users()->count() }}
            </span> --}}
                </button>
            </nav>
        </div>

        <x-admin-block2>
            <!-- Contenu de l'onglet: Joueurs inscrits -->
            <div id="content-config" x-show="activeTab === 'config'" class="tab-content">
                <div class="container mx-auto px-4 py-8">
                    <div class="flex justify-center">
                        <div class="w-full max-w-8xl">
                            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                                <div class="p-6">

                                    <div class="border-b pb-4 mb-6">
                                        <h3 class="text-2xl font-bold text-gray-800">{{ __('Configuration') }}
                                        </h3>
                                    </div>

                                    <!-- Informations sur le tournoi -->
                                    <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
                                        <h4 class="text-lg font-medium mb-4 text-gray-800">{{ __('Parameters') }}
                                        </h4>
                                        <p class="mt-2 mb-4 text-sm text-gray-500">
                                            Veuillez définir ici les différents paramètres de votre tournoi.
                                        </p>
                                        <x-forms.tournament :rooms="$rooms" :tournament="$tournament" />
                                    </div>

                                    @include('admin.tournaments.partials.pool-generator')

                                    <!-- Messages de succès -->
                                    @if (session()->has('success'))
                                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-8"
                                            role="alert">
                                            <p>{{ session()->get('success') }}</p>
                                        </div>
                                    @elseif (session()->has('error'))
                                        <div class="bg-red-100 border-l-4 border-Red-500 text-red-700 p-4 mb-8"
                                            role="alert">
                                            <p>{{ session()->get('error') }}</p>
                                        </div>
                                    @endif

                                    <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
                                        <!-- Affichage des pools existantes -->
                                        @if ($tournament->pools->count() > 0)
                                            <div class="mt-8">
                                                <h2 class="text-xl font-bold mb-6 text-gray-800">Pools existantes
                                                </h2>
                                                <!-- Bouton pour générer les matches -->
                                                <div class="my-6">
                                                    <h3 class="text-lg font-medium mb-2">Génération des matches
                                                    </h3>
                                                    <form method="POST"
                                                        action="{{ route('generatePoolMatches', $tournament) }}">
                                                        @csrf
                                                        <button type="submit"
                                                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                                            Générer tous les matches de poules
                                                        </button>
                                                    </form>
                                                    <p class="text-sm text-gray-600 mt-2">
                                                        Cette action va générer tous les matches pour toutes les
                                                        poules selon
                                                        l'algorithme Round
                                                        Robin.
                                                    </p>
                                                </div>
                                                <form
                                                    action="{{ route('tournament.updatePoolPlayers', $tournament->id) }}"
                                                    method="POST">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                                        @foreach ($tournament->pools as $pool)
                                                            <div
                                                                class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                                                                <div class="bg-gray-50 px-4 py-3 border-b">
                                                                    <h3 class="text-lg font-medium text-gray-800">
                                                                        {{ $pool->name }}
                                                                    </h3>
                                                                </div>
                                                                <div class="mt-4">
                                                                    <a href="{{ route('showPoolMatches', $pool) }}"
                                                                        class="m-4 text-blue-600 hover:underline">
                                                                        Voir les matches de la poule
                                                                        {{ $pool->name }}
                                                                    </a>
                                                                </div>
                                                                <ul class="divide-y divide-gray-200">
                                                                    @forelse ($pool->users->sortBy('ranking') as $user)
                                                                        <li class="px-4 py-3">
                                                                            <div
                                                                                class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                                                                                <div
                                                                                    class="flex items-center mb-2 sm:mb-0">
                                                                                    <span
                                                                                        class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-800 font-medium text-sm mr-3">
                                                                                        {{ $loop->iteration }}
                                                                                    </span>
                                                                                    <div>
                                                                                        <p
                                                                                            class="font-medium text-gray-500">
                                                                                            {{ $user->first_name }}
                                                                                            {{ $user->last_name }}
                                                                                        </p>
                                                                                        <p
                                                                                            class="text-sm text-gray-500">
                                                                                            Rank:
                                                                                            {{ $user->ranking }}
                                                                                        </p>
                                                                                    </div>
                                                                                </div>
                                                                                <select
                                                                                    name="player_moves[{{ $user->id }}]"
                                                                                    class="mt-2 sm:mt-0 block w-full sm:w-auto pl-3 pr-10 py-2 text-base text-gray-500 border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                                                    <option value="">Déplacer
                                                                                        vers...</option>
                                                                                    @foreach ($tournament->pools as $targetPool)
                                                                                        @if ($targetPool->id != $pool->id)
                                                                                            <option
                                                                                                value="{{ $targetPool->id }}">
                                                                                                {{ $targetPool->name }}
                                                                                            </option>
                                                                                        @endif
                                                                                    @endforeach
                                                                                </select>
                                                                            </div>
                                                                        </li>
                                                                    @empty
                                                                        <li class="px-4 py-3 text-gray-500 italic">
                                                                            Aucun joueur dans
                                                                            cette pool</li>
                                                                    @endforelse
                                                                </ul>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <div class="mt-8 flex justify-end">
                                                        <button type="submit"
                                                            class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-md shadow-sm transition duration-200">
                                                            Enregistrer les modifications
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="container mx-auto px-4 py-8">
                    <div class="flex justify-center">
                        <div class="w-full max-w-8xl">
                            <div class="bg-white rounded-lg shadow-lg overflow-hidden p-6 text-gray-900">
                                <div class="flex justify-between mb-6">
                                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                                        {{ __('Configuration de la phase finale') }} - {{ $tournament->name }}
                                    </h2>
                                    <div>
                                        <a href="{{ route('tournamentShow', $tournament) }}"
                                            class="text-blue-600 hover:underline">
                                            &larr; Retour au tournoi
                                        </a>
                                    </div>
                                </div>

                                <form action="{{ route('configureKnockout', $tournament) }}" method="POST">
                                    @csrf
                                    <div class="mb-6">
                                        <label for="starting_round"
                                            class="block text-sm font-medium text-gray-700">Phase de
                                            départ</label>
                                        <select id="starting_round" name="starting_round"
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                            <option value="round_16">16ème de finale (16 joueurs)</option>
                                            <option value="round_8">8ème de finale (8 joueurs)</option>
                                            <option value="round_4">Quart de finale (4 joueurs)</option>
                                        </select>
                                        <p class="mt-2 text-sm text-gray-500">
                                            Sélectionnez la phase à partir de laquelle vous souhaitez démarrer le
                                            tableau final.
                                            Les joueurs seront sélectionnés en fonction de leurs résultats dans les
                                            poules.
                                        </p>
                                    </div>

                                    <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
                                        <button type="submit"
                                            class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                            Configurer la phase finale
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenu de l'onglet: Joueurs inscrits -->
            <div id="content-registered" x-show="activeTab === 'registered'" class="tab-content">
                <h2 class="text-xl font-bold text-gray-800 mb-6">Joueurs inscrits</h2>

                <div class="overflow-x-auto mb-8">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-100">
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                    #</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                    Joueur</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                    Classement</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                    Date d'inscription</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                    Paiement</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @if (count($tournament->users()->get()) > 0)
                                @foreach ($tournament->users()->get() as $user)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span
                                                class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-800 font-medium text-sm">
                                                {{ $loop->iteration }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="font-medium text-gray-900">{{ $user->first_name }}
                                                {{ $user->last_name }}</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="text-gray-900">{{ $user->ranking }}</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">31/03/2025</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-{{ $user->pivot->has_paid ? 'green' : 'red' }}-100 text-{{ $user->pivot->has_paid ? 'green' : 'red' }}-800">
                                                @if ($user->pivot->has_paid)
                                                    Payé
                                                @else
                                                    Paiement en attente
                                                @endif
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div>
                                                <a
                                                    href="{{ route('tournamentToggleHasPaid', [$tournament, $user]) }}">
                                                    @if (!$user->pivot->has_paid)
                                                        <!-- Marquer comme payé -->
                                                        <button
                                                            class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                                            title="Marquer comme payé">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                                                viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd"
                                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                                    clip-rule="evenodd" />
                                                            </svg>
                                                        </button>
                                                    @else
                                                        <!-- Marquer comme impayé -->
                                                        <button
                                                            class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-yellow-500 hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500"
                                                            title="Marquer comme impayé">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                                                viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd"
                                                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                                    clip-rule="evenodd" />
                                                            </svg>
                                                        </button>
                                                    @endif
                                                </a>

                                                <!-- Désinscrire -->
                                                <a href="{{ route('tournamentUnregister', [$tournament, $user]) }}">
                                                    <button
                                                        class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                                        title="Désinscrire">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                                            viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd"
                                                                d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-center text-gray-500 italic">Aucun
                                        joueur
                                        inscrit pour le moment.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Contenu de l'onglet: Liste des poules -->
            <div id="content-pools" x-show="activeTab === 'pools'" class="tab-content" x-cloak>
                <div class="flex flex-col gap-2 mb-2">
                    <div class="overflow-x-auto">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">Liste des poules</h2>
                        @if ($tournament->pools->count() > 0 && ($tournament->status != 'pending' || $tournament->status != 'closed'))
                            {{-- Bouton pour effacer les poules --}}
                            <form method="GET" action="{{ route('erasePools', $tournament) }}">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    {{ __('Erase all pools') }}
                                </button>
                            </form>
                            <p class="text-sm text-gray-600 mt-2">
                                Cette action va supprimer toutes les poules.
                            </p>

                            <!-- Bouton pour générer les matches -->
                            @if ($tournament->matches->count() == 0)
                                <div class="my-6">
                                    <form method="POST" action="{{ route('generatePoolMatches', $tournament) }}">
                                        @csrf
                                        <button type="submit"
                                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                            Générer tous les matches de poules
                                        </button>
                                    </form>
                                    <p class="text-sm text-gray-600 mt-2">
                                        Cette action va générer tous les matches pour toutes les poules selon
                                        l'algorithme Round
                                        Robin.
                                    </p>
                                </div>
                            @endif
                            <form action="{{ route('tournament.updatePoolPlayers', $tournament->id) }}"
                                method="POST">
                                @csrf
                                @method('PUT')
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    @foreach ($tournament->pools as $pool)
                                        <div
                                            class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                                            <div class="bg-gray-50 px-4 py-3 border-b">
                                                <h3 class="text-lg font-medium text-gray-800">{{ $pool->name }}
                                                    <div class="border-t pt-2">
                                                        <p class="text-sm text-gray-600">
                                                            <span class="font-medium">Joueurs:</span>
                                                            {{ $pool->users->count() }}
                                                        </p>
                                                        <p class="text-sm text-gray-600">
                                                            <span class="font-medium">Matches:</span>
                                                            {{ $pool->tournamentMatches->count() }}
                                                        </p>
                                                    </div>
                                                </h3>
                                            </div>
                                            <div class="mt-4">
                                                <a href="{{ route('showPoolMatches', $pool) }}"
                                                    class="m-4 text-blue-600 hover:underline">
                                                    Voir les matches de la poule {{ $pool->name }}
                                                </a>
                                            </div>
                                            <ul class="divide-y divide-gray-200">
                                                @forelse ($pool->users->sortBy('ranking') as $user)
                                                    <li class="px-4 py-3">
                                                        <div
                                                            class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                                                            <div class="flex items-center mb-2 sm:mb-0">
                                                                <span
                                                                    class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-800 font-medium text-sm mr-3">
                                                                    {{ $loop->iteration }}
                                                                </span>
                                                                <div>
                                                                    <p class="font-medium text-gray-500">
                                                                        {{ $user->first_name }}
                                                                        {{ $user->last_name }}</p>
                                                                    <p class="text-sm text-gray-500">Rank:
                                                                        {{ $user->ranking }}</p>
                                                                </div>
                                                            </div>
                                                            <select name="player_moves[{{ $user->id }}]"
                                                                class="mt-2 sm:mt-0 block w-full sm:w-auto pl-3 pr-10 py-2 text-base text-gray-500 border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                                <option value="">Déplacer vers...</option>
                                                                @foreach ($tournament->pools as $targetPool)
                                                                    @if ($targetPool->id != $pool->id)
                                                                        <option value="{{ $targetPool->id }}">
                                                                            {{ $targetPool->name }}</option>
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </li>
                                                @empty
                                                    <li class="px-4 py-3 text-gray-500 italic">Aucun joueur dans
                                                        cette pool</li>
                                                @endforelse
                                            </ul>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-8 flex justify-end">
                                    <button type="submit"
                                        class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-md shadow-sm transition duration-200">
                                        Enregistrer les modifications
                                    </button>
                                </div>
                            </form>
                        @else
                            <p class="text-left text-gray-500 italic">{{ __('No pools generated yet.') }}</p>
                            @include('admin.tournaments.partials.pool-generator')
                        @endif
                    </div>
                </div>

            </div>

            <!-- Contenu de l'onglet: Liste des matches -->
            @include('admin.tournaments.partials.matches-list')

            <!-- Contenu de l'onglet: Liste des tables -->
            <div id="content-tables" x-show="activeTab === 'tables'" class="tab-content" x-cloak>
                <h2 class="text-xl font-bold text-gray-800 mb-6">Liste des matches</h2>
                <div class="max-w-7xl mx-auto p-6">
                    <h1 class="text-2xl font-bold text-white mb-6">État des tables</h1>

                    <!-- Filtres et recherche -->
                    <div class="flex flex-wrap gap-4 mb-6">
                        <button
                            class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">Toutes</button>
                        <button
                            class="px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-100 transition">Disponibles</button>
                        <button
                            class="px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-100 transition">Occupées</button>
                        <div class="ml-auto">
                            <input type="text" placeholder="Rechercher..."
                                class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                    </div>
                    <!-- Grille des tables avec espacement et responsive améliorés -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">

                        @foreach ($tables as $table)
                            @if ($table->pivot->is_table_free)
                                <!-- Table 1 - Disponible -->
                                <div
                                    class="group relative rounded-xl border border-green-400 bg-gradient-to-br from-green-50 to-green-100 p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
                                    <div class="absolute top-3 right-3">
                                        <span class="flex h-3 w-3">
                                            <span
                                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                            <span
                                                class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                                        </span>
                                    </div>

                                    <div class="flex items-center space-x-4">
                                        <div
                                            class="flex items-center justify-center w-12 h-12 rounded-full bg-green-500 text-white font-bold text-xl shadow-inner">
                                            {{ $table->name }}
                                        </div>
                                        <div class="flex flex-col">
                                            <span
                                                class="text-green-700 font-semibold text-lg">{{ __('Free') }}</span>
                                            {{-- <span class="text-green-600 text-sm">Libre depuis {{ round($table->pivot->match_ended_at->diffInMinutes(now())) }} min</span> --}}
                                        </div>
                                    </div>

                                    <div class="mt-4 flex justify-end">
                                        <button
                                            class="px-3 py-1 bg-green-500 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                            {{ __('Book') }}
                                        </button>
                                    </div>
                                </div>
                            @else
                                {{-- Duration --}}
                                @php
                                    $expected_match_duration = 20;
                                    $duration = round($table->pivot->match_started_at->diffInMinutes(now()));
                                    $percent = min(100, ($duration / $expected_match_duration) * 100);
                                @endphp

                                <!-- Table 2 - Occupée -->
                                <div
                                    class="group relative rounded-xl border border-{{ $percent < 100 ? 'gray' : 'red' }}-400 bg-gradient-to-br from-{{ $percent < 100 ? 'gray' : 'red' }}-50 to-{{ $percent < 100 ? 'gray' : 'red' }}-100 p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
                                    <div class="absolute top-3 right-3">
                                        <span class="flex h-3 w-3">
                                            <span
                                                class="relative inline-flex rounded-full h-3 w-3 bg-{{ $percent < 100 ? 'gray' : 'red' }}-500"></span>
                                        </span>
                                    </div>

                                    <div class="flex items-center space-x-4">
                                        <div
                                            class="flex items-center justify-center w-12 h-12 rounded-full bg-{{ $percent < 100 ? 'gray' : 'red' }}-500 text-white font-bold text-xl shadow-inner">
                                            {{ $table->name }}
                                        </div>
                                        <div class="flex flex-col">
                                            <span
                                                class="text-{{ $percent < 100 ? 'gray' : 'red' }}-700 font-semibold text-lg">Occupée</span>
                                            <span
                                                class="text-{{ $percent < 100 ? 'gray' : 'red' }}-600 text-sm">Depuis
                                                {{ $duration }} min</span>
                                        </div>
                                    </div>

                                    <div class="mt-4 bg-white rounded-lg p-4 shadow-sm">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 text-gray-600 mr-1" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24"
                                                    xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                                    </path>
                                                </svg>
                                                @php
                                                    $match = $table->match->first();
                                                @endphp
                                                <span
                                                    class="text-gray-800 font-medium text-sm">{{ $match->player1->first_name }}
                                                    {{ $match->player1->last_name }}</span>
                                            </div>
                                            <span class="text-gray-500 text-xs">VS</span>
                                            <div class="flex items-center">
                                                <span
                                                    class="text-gray-800 font-medium text-sm">{{ $match->player2->first_name }}
                                                    {{ $match->player2->last_name }}</span>
                                                <svg class="w-4 h-4 text-gray-600 ml-1" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24"
                                                    xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                                    </path>
                                                </svg>
                                            </div>
                                        </div>
                                        <div class="flex justify-center items-center mt-2">
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                <div class="bg-{{ $percent < 100 ? 'gray' : 'red' }}-500 h-2 rounded-full"
                                                    style="width: {{ $percent }}%"></div>
                                            </div>
                                        </div>
                                        <div class="mt-4 flex justify-end">
                                            <a href="{{ route('editMatch', $match) }}">
                                                <button type="submit"
                                                    class="px-3 py-1 bg-blue-500 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                                    {{ __('Encode results') }}
                                                </button>
                                            </a>
                                        </div>
                                    </div>

                                </div>
                            @endif
                        @endforeach

                    </div>
                </div>
            </div>
        </x-admin-block2>

            <div x-data="playerSelector()" class="max-w-md mx-auto">

        <!-- Modal -->
        <div x-show="showModal" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click="showModal = false">

            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4" @click.stop>

                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Inscrire un joueur</h3>
                    <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Formulaire -->
                <form action="#" method="POST" class="space-y-4">
                    <div class="relative">
                        <label for="player-search" class="block text-sm font-medium text-gray-700 mb-2">
                            Rechercher un joueur
                        </label>

                        <!-- Input de recherche -->
                        <div class="relative">
                            <input type="text" id="player-search" name="player" x-model="searchQuery"
                                @input="filterPlayers()" @focus="showDropdown = true"
                                @keydown.escape="showDropdown = false" @keydown.arrow-down.prevent="navigateDown()"
                                @keydown.arrow-up.prevent="navigateUp()" @keydown.enter.prevent="selectHighlighted()"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Tapez le nom du joueur..." autocomplete="off">

                            <!-- Icône de recherche -->
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                        </div>

                        <!-- Liste déroulante des résultats -->
                        <div x-show="showDropdown && filteredPlayers.length > 0"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute z-10 mt-1 w-full bg-white rounded-md shadow-lg border border-gray-200 max-h-60 overflow-auto">

                            <template x-for="(player, index) in filteredPlayers" :key="player.id">
                                <div @click="selectPlayer(player)"
                                    :class="{ 'bg-blue-50 text-blue-700': index === highlightedIndex, 'text-gray-900': index !==
                                            highlightedIndex }"
                                    class="px-4 py-2 cursor-pointer hover:bg-gray-50 flex items-center justify-between">
                                    <div>
                                        <div class="font-medium" x-text="player.name"></div>
                                        <div class="text-sm text-gray-500" x-text="player.email"></div>
                                    </div>
                                    <div class="text-xs text-gray-400" x-text="'Niveau: ' + player.level"></div>
                                </div>
                            </template>
                        </div>

                        <!-- Message si aucun résultat -->
                        <div x-show="showDropdown && searchQuery.length > 0 && filteredPlayers.length === 0"
                            class="absolute z-10 mt-1 w-full bg-white rounded-md shadow-lg border border-gray-200 px-4 py-3 text-sm text-gray-500">
                            Aucun joueur trouvé pour "<span x-text="searchQuery"></span>"
                        </div>
                    </div>

                    <!-- Joueur sélectionné -->
                    <div x-show="selectedPlayer" class="bg-green-50 border border-green-200 rounded-md p-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium text-green-800" x-text="selectedPlayer?.name"></div>
                                <div class="text-sm text-green-600" x-text="selectedPlayer?.email"></div>
                            </div>
                            <button @click="clearSelection()" type="button"
                                class="text-green-400 hover:text-green-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Input hidden pour le formulaire -->
                    <input type="hidden" name="player" :value="selectedPlayer?.id">

                    <!-- Boutons d'action -->
                    <div class="flex justify-end space-x-3 pt-4">
                        <button @click="showModal = false" type="button"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Annuler
                        </button>
                        <button type="submit" :disabled="!selectedPlayer"
                            :class="selectedPlayer ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-300 cursor-not-allowed'"
                            class="px-4 py-2 text-sm font-medium text-white rounded-md">
                            Inscrire
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function playerSelector() {
            return {
                showModal: false,
                showDropdown: false,
                searchQuery: '',
                selectedPlayer: null,
                highlightedIndex: -1,

                // Données d'exemple - remplacez par vos vraies données
                players: [{
                        id: 1,
                        name: 'Jean Dupont',
                        email: 'jean.dupont@email.com',
                        level: 'Débutant'
                    },
                    {
                        id: 2,
                        name: 'Marie Martin',
                        email: 'marie.martin@email.com',
                        level: 'Intermédiaire'
                    },
                    {
                        id: 3,
                        name: 'Pierre Durand',
                        email: 'pierre.durand@email.com',
                        level: 'Avancé'
                    },
                    {
                        id: 4,
                        name: 'Sophie Bernard',
                        email: 'sophie.bernard@email.com',
                        level: 'Expert'
                    },
                    {
                        id: 5,
                        name: 'Lucas Moreau',
                        email: 'lucas.moreau@email.com',
                        level: 'Débutant'
                    },
                    {
                        id: 6,
                        name: 'Emma Petit',
                        email: 'emma.petit@email.com',
                        level: 'Intermédiaire'
                    },
                    {
                        id: 7,
                        name: 'Thomas Roux',
                        email: 'thomas.roux@email.com',
                        level: 'Avancé'
                    },
                    {
                        id: 8,
                        name: 'Léa Blanc',
                        email: 'lea.blanc@email.com',
                        level: 'Expert'
                    }
                ],

                filteredPlayers: [],

                init() {
                    this.filteredPlayers = this.players;
                },

                filterPlayers() {
                    const query = this.searchQuery.toLowerCase().trim();
                    if (query === '') {
                        this.filteredPlayers = this.players;
                    } else {
                        this.filteredPlayers = this.players.filter(player =>
                            player.name.toLowerCase().includes(query) ||
                            player.email.toLowerCase().includes(query)
                        );
                    }
                    this.highlightedIndex = -1;
                    this.showDropdown = true;
                },

                selectPlayer(player) {
                    this.selectedPlayer = player;
                    this.searchQuery = player.name;
                    this.showDropdown = false;
                },

                clearSelection() {
                    this.selectedPlayer = null;
                    this.searchQuery = '';
                    this.showDropdown = false;
                },

                navigateDown() {
                    if (this.highlightedIndex < this.filteredPlayers.length - 1) {
                        this.highlightedIndex++;
                    }
                },

                navigateUp() {
                    if (this.highlightedIndex > 0) {
                        this.highlightedIndex--;
                    }
                },

                selectHighlighted() {
                    if (this.highlightedIndex >= 0 && this.highlightedIndex < this.filteredPlayers.length) {
                        this.selectPlayer(this.filteredPlayers[this.highlightedIndex]);
                    }
                }
            }
        }
    </script>
    </div>
    </div>


    <style>
        [x-cloak] {
            display: none !important;
        }

        /* Animations pour les tables */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .grid>div {
            animation: fadeIn 0.6s ease-out forwards;
        }

        .grid>div:nth-child(1) {
            animation-delay: 0.05s;
        }

        .grid>div:nth-child(2) {
            animation-delay: 0.1s;
        }

        .grid>div:nth-child(3) {
            animation-delay: 0.15s;
        }

        .grid>div:nth-child(4) {
            animation-delay: 0.2s;
        }

        .grid>div:nth-child(5) {
            animation-delay: 0.25s;
        }

        .grid>div:nth-child(6) {
            animation-delay: 0.3s;
        }

        .grid>div:nth-child(7) {
            animation-delay: 0.35s;
        }

        .grid>div:nth-child(8) {
            animation-delay: 0.4s;
        }
    </style>



</x-app-layout>
