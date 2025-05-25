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
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-500"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                {{ __('Create tournament') }}
                            </a>
                        </div>
                    </div>
                </div>

                {{-- <div class="inline-block h-4/5 min-h-[1em] w-0.5 mx-2 my-auto self-stretch bg-neutral-300 dark:bg-white/10"></div> --}}
                <!-- Boutons d'accès rapide -->
                {{-- <a href="#"
                    class="h-8 text-md flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md transition duration-150 ease-in-out">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-white" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8h18M3 8v2a1 1 0 001 1h16a1 1 0 001-1V8M6 8v8m12-8v8" />
                    </svg>
                    {{ __('Show Tables') }}
                </a>
                <a href=""
                    class="h-8 text-md flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md transition duration-150 ease-in-out">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    {{ __('Show scores') }}
                </a> --}}
            </div>
        </div>
    </x-slot>
    <x-admin-block>
        <livewire:tournaments-table>
    </x-admin-block>
</x-app-layout>
