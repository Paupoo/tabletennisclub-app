<x-app-layout>
    <x-slot name="header">
        {{-- Header --}}
        <div class="flex flex-row gap-2 items-center">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ $tournament->name }}
            </h2>
            <x-tournament.status-badge status="{{ $tournament->status }}" />
            <!-- Menu d'actions -->
            <div class="ml-auto mt-4 md:mt-0 flex flex-wrap gap-3" x-data="{ showMenu: false }">
                <!-- Bouton principal avec dropdown -->
                <div class="relative">
                    <button @click="showMenu = !showMenu"
                        class="h-8 text-md flex items-center justify-between bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md transition duration-150 ease-in-out">
                        <span class="mr-2">Actions</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
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
                            <!-- Composant Livewire pour l'inscription de joueur -->
                            <livewire:player-registration :tournament="$tournament" />
                            
                            <a href="{{ route('tournamentSetup', $tournament) }}"
                                class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                                <x-ui.icon name="modify" class="mr-2" />
                                {{ __('Modify') }}
                            </a>
                            <a href="#"
                                class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                                <x-ui.icon name="duplicate" class="mr-2" />
                                {{ __('Duplicate') }}
                            </a>
                            <div class="border-t border-gray-200"></div>
                            <a href="{{ route('deleteTournament', $tournament) }}"
                                class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-gray-100 hover:text-red-700">
                                <x-ui.icon name="delete" class="mr-2" />
                                {{ __('Delete') }}
                            </a>
                        </div>
                    </div>
                </div>
                <!-- Boutons d'accès rapide -->
                <a href="#"
                    class="h-8 text-md flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md transition duration-150 ease-in-out">
                    <x-ui.icon name="table" class="mr-2" />
                    {{ __('Tables') }}
                </a>
                <a href="{{ route('knockoutBracket', $tournament) }}"
                    class="h-8 text-md flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md transition duration-150 ease-in-out">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    {{ __('Results') }}
                </a>
                <a href="{{ route('tournamentsIndex') }}">
                    <x-primary-button>
                        <div class="flex">
                            <x-ui.icon name="arrow-left" class="mr-2" />
                            {{ __('Tournaments list') }}
                        </div>
                    </x-primary-button>
                </a>
            </div>
        </div>
    </x-slot>

    <x-admin-block>
        <!-- Menu secondaire pour tournoi sélectionné -->
        @include('admin.tournaments.partials.secondary-menu')


        <!-- Contenu de l'onglet: Joueurs inscrits -->
        @include('admin.tournaments.partials.matches-list')

    </x-admin-block>
</x-app-layout>