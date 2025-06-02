<x-app-layout>

    <x-slot name="header">
        {{-- Header --}}
        <div class="flex">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ __('Tournaments list') }}
            </h2>
            <!-- Menu d'actions -->
            <div class="ml-auto mt-4 md:mt-0 flex flex-wrap gap-3" x-data="{ showMenu: false }">
                <!-- Bouton principal avec dropdown -->
                <div class="relative">
                    <button @click="showMenu = !showMenu"
                        class="h-8 text-md flex items-center justify-between bg-yellow-500 hover:bg-yellow-700 text-white px-4 py-2 rounded-md transition duration-150 ease-in-out">
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
                            <a href="{{ route('createTournament') }}"
                                class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                                <x-ui.icon name="plus"/>
                                {{ __('Create tournament') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>
    <x-admin-block>
        <livewire:tournaments-table>
    </x-admin-block>
</x-app-layout>
