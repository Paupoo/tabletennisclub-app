<x-app-layout :breadcrumbs="$breadcrumbs">
        <x-slot name="header">
        {{-- Header --}}
        <div class="flex">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ __('Members') }}
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
                            @can('create', $user_model)
                            <a href="{{ route('users.create') }}"
                                class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                                <x-ui.icon name="plus" class="mr-2"/>
                                {{ __('Create new user') }}
                            </a>
                            <div class="border-t border-gray-200"></div>
                            <button @click="$dispatch('open-modal', 'confirm-forceList-reset')"
                                class="flex items-center px-4 py-2 text-sm text-red-700 hover:bg-gray-100 hover:text-red-900">
                                <x-ui.icon name="reset" class="mr-2"/>
                                {{ __('Reset Force Index') }}
                            </button>  
                            <button @click="$dispatch('open-modal', 'confirm-forceList-deletion')"
                                class="w-full flex items-center px-4 py-2 text-sm text-red-600 hover:bg-gray-100 hover:text-red-700">
                                <x-ui.icon name="delete" class="mr-2" />
                                {{ __('Delete Force Index') }}
                            </button>
                            @endcan

                            <!-- Modal to confirm reset force index -->
                            <x-modal name="confirm-forceList-reset" focusable>
                                <form method="get" action="{{ route('setForceList') }}" class="p-6" x-data="{ confirmText: '', isValid() { return this.confirmText === 'RESET_FI' } }" >
                                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                        {{ __('Are you sure you want to reset the users force index?') }}
                                    </h2>

                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {{ __('This action is irreversible. All associated data will be permanently changed.') }}
                                    </p>

                                    <!-- Champ de confirmation -->
                                    <div class="mt-4">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            {{ __('To confirm, type') }} <strong>"RESET_FI"</strong> {{ __('in the box below') }}:
                                        </label>
                                        <input 
                                            type="text" 
                                            x-model="confirmText"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                            placeholder="RESET_FI"
                                            autocomplete="off"
                                        >
                                    </div>

                                    <div class="mt-6 flex justify-end">
                                        <x-secondary-button x-on:click="$dispatch('close')">
                                            {{ __('Cancel') }}
                                        </x-secondary-button>
                                        
                                        <x-danger-button 
                                            class="ms-3" 
                                            x-bind:disabled="!isValid()"
                                            x-bind:class="{ 'opacity-50 cursor-not-allowed': !isValid() }"
                                        >
                                            {{ __('Reset') }}
                                        </x-danger-button>
                                    </div>
                                </form>
                            </x-modal>

                            <!-- Modal to confirm delete force index -->
                            <x-modal name="confirm-forceList-deletion" focusable >
                                <form method="get" action="{{ route('deleteForceList') }}" x-data="{ confirmText: '', isValid() { return this.confirmText === 'DELETE_FI' } }" class="p-6">
                                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                        {{ __('Are you sure you want to delete all the users force index?') }}
                                    </h2>

                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {{ __('This action is irreversible. All associated data will be permanently removed.') }}
                                    </p>

                                    <!-- Champ de confirmation -->
                                    <div class="mt-4">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            {{ __('To confirm, type') }} <strong>"DELETE_FI"</strong> {{ __('in the box below') }}:
                                        </label>
                                        <input 
                                            type="text" 
                                            x-model="confirmText"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                            placeholder="DELETE_FI"
                                            autocomplete="off"
                                        >
                                    </div>

                                    <div class="mt-6 flex justify-end">
                                        <x-secondary-button x-on:click="$dispatch('close')">
                                            {{ __('Cancel') }}
                                        </x-secondary-button>
                                        
                                        <x-danger-button 
                                            class="ms-3" 
                                            x-bind:disabled="!isValid()"
                                            x-bind:class="{ 'opacity-50 cursor-not-allowed': !isValid() }"
                                        >
                                            {{ __('Delete') }}
                                        </x-danger-button>
                                    </div>
                                </form>
                            </x-modal>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <x-admin-block>
        <livewire:users-table>
    </x-admin-block>

</x-app-layout>
