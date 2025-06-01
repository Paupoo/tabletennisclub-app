<!-- Menu d'actions -->
<div class="ml-auto mt-4 md:mt-0 flex flex-wrap gap-3" x-data="{ showMenu: false }">
    <!-- Bouton principal avec dropdown -->
    <div class="relative">
        <x-primary-button @click="showMenu = !showMenu"
            class="flex items-center justify-between bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-md transition duration-150 ease-in-out"
            type="button">
                <span class="mr-2">{{ __('Actions') }}</span>
                <x-ui.icon name="arrow-down" />
        </x-primary-button>
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

                <a href="{{ route('tournament.edit', $tournament) }}"
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
    <a href="{{ route('tournamentsIndex') }}">
        <x-primary-button type="button"
            >
                <span class="mr-2">{{ __('Tournaments list') }}</span>
                <x-ui.icon name="arrow-left"/>
        </x-primary-button>
    </a>
</div>
