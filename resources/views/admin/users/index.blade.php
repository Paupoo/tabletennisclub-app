<x-app-layout :breadcrumbs="$breadcrumbs">
    <div x-data="{ showStats: false, showActionsMenu: false }">
        <x-admin-block>
            <!-- En-tête de page avec actions -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start space-y-4 sm:space-y-0">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">{{ __('Members') }}</h2>
                        <p class="text-gray-600 text-sm sm:text-base">Gérez les membres du club et leurs informations.</p>
                    </div>
        
                    <!-- Menu d'actions modernisé -->
                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                        @can('create', $user_model)
                            <!-- Action principale : Créer un utilisateur -->
                            <a href="{{ route('users.create') }}"
                               class="bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto text-center flex items-center justify-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                {{ __('Create new user') }}
                            </a>
                            <!-- Bouton toggle statistiques -->
                            <button
                                @click="showStats = !showStats"
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto flex items-center justify-center"
                            >
                                <!-- Icône qui tourne selon l'état -->
                                <svg
                                    x-bind:class="showStats ? 'rotate-180' : 'rotate-0'"
                                    class="w-4 h-4 mr-2 transition-transform duration-200"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
        
                                <!-- Texte du bouton qui change selon l'état -->
                                <span x-text="showStats ? '{{ __('Hide Statistics') }}' : '{{ __('Show Statistics') }}'"></span>
                            </button>
                            <!-- Menu d'actions avancées -->
                            <div class="relative">
                                <button @click="showActionsMenu = !showActionsMenu"
                                        class="bg-club-yellow hover:bg-club-yellow-light text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                    </svg>
                                    Actions avancées
                                    <svg class="w-4 h-4 ml-2 transition-transform duration-200"
                                         :class="showActionsMenu ? 'rotate-180' : ''"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                <!-- Dropdown menu -->
                                <div x-show="showActionsMenu"
                                     @click.away="showActionsMenu = false"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 transform scale-95"
                                     x-transition:enter-end="opacity-100 transform scale-100"
                                     x-transition:leave="transition ease-in duration-150"
                                     x-transition:leave-start="opacity-100 transform scale-100"
                                     x-transition:leave-end="opacity-0 transform scale-95"
                                     class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50"
                                     style="display: none;">
                                    <div class="py-2">
                                        <div class="px-4 py-2 text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-100">
                                            Gestion Force Index
                                        </div>
        
                                        <button @click="$dispatch('open-modal', 'confirm-forceList-reset'); showActionsMenu = false"
                                                class="w-full flex items-center px-4 py-3 text-sm text-orange-700 hover:bg-orange-50 hover:text-orange-900 transition-colors duration-200">
                                            <div class="flex-shrink-0 w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center mr-3">
                                                <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="font-medium">{{ __('Reset Force Index') }}</div>
                                                <div class="text-xs text-gray-500">Remettre à zéro les indices</div>
                                            </div>
                                        </button>
        
                                        <button @click="$dispatch('open-modal', 'confirm-forceList-deletion'); showActionsMenu = false"
                                                class="w-full flex items-center px-4 py-3 text-sm text-red-700 hover:bg-red-50 hover:text-red-900 transition-colors duration-200">
                                            <div class="flex-shrink-0 w-8 h-8 bg-red-100 rounded-full flex items-center justify-center mr-3">
                                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="font-medium">{{ __('Delete Force Index') }}</div>
                                                <div class="text-xs text-gray-500">Supprimer tous les indices</div>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endcan
                    </div>
                </div>
            </div>
            <!-- Bloc des statistiques avec transition -->
            <div
                x-show="showStats"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform -translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 transform translate-y-0"
                x-transition:leave-end="opacity-0 transform -translate-y-2"
                class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6"
            >
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="bg-club-blue bg-opacity-10 rounded-lg p-4 text-center border border-club-blue border-opacity-20">
                        <div class="text-xl sm:text-2xl font-bold text-club-yellow">{{ $stats->get('totalActiveUsers') ?? 0 }}</div>
                        <div class="text-xs sm:text-sm text-club-yellow font-medium">{{ __('Active Members') }}</div>
                    </div>
                    <div class="bg-club-yellow rounded-lg p-4 text-center border border-club-yellow">
                        <div class="text-xl sm:text-2xl font-bold text-club-blue">{{ $stats->get('totalCompetitors') ?? 0 }}</div>
                        <div class="text-xs sm:text-sm text-club-blue font-medium">{{ __('Competitors') }}</div>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-4 text-center border border-blue-200">
                        <div class="text-xl sm:text-2xl font-bold text-blue-600">{{ $stats->get('totalUsersCreatedLastYear') ?? 0 }}</div>
                        <div class="text-xs sm:text-sm text-blue-700 font-medium">{{ __('Since less than a year') }}</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-4 text-center border border-yellow-200">
                        <div class="text-xl sm:text-2xl font-bold text-yellow-600">{{ $stats->get('totalUnpaidUsers') ?? 0 }}</div>
                        <div class="text-xs sm:text-sm text-yellow-700 font-medium">{{ __('Unpaid Subscription') }}</div>
                    </div>
                    <div class="bg-gray-100 rounded-lg p-4 text-center border border-gray-300">
                        <div class="text-xl sm:text-2xl font-bold text-gray-600">{{ $stats->get('totalUnderagedUsers') ?? 0 }}</div>
                        <div class="text-xs sm:text-sm text-gray-600 font-medium">{{ __('Young people') }}</div>
                    </div>
                    <div class="bg-gray-100 rounded-lg p-4 text-center border border-gray-300">
                        <div class="text-xl sm:text-2xl font-bold text-gray-600">{{ $stats->get('totalWomen') ?? 0 }}</div>
                        <div class="text-xs sm:text-sm text-gray-600 font-medium">{{ __('Women') }}</div>
                    </div>
                    <div class="bg-gray-100 rounded-lg p-4 text-center border border-gray-300">
                        <div class="text-xl sm:text-2xl font-bold text-gray-600">{{ $stats->get('totalMen') ?? 0 }}</div>
                        <div class="text-xs sm:text-sm text-gray-600 font-medium">{{ __('Men') }}</div>
                    </div>
                    <div class="bg-gray-100 rounded-lg p-4 text-center border border-gray-300">
                        <div class="text-xl sm:text-2xl font-bold text-gray-600">{{ $stats->get('totalVeterans') ?? 0 }}</div>
                        <div class="text-xs sm:text-sm text-gray-600 font-medium">{{ __('Veterans') }}</div>
                    </div>
                </div>
            </div>
            <!-- Table des utilisateurs -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <livewire:admin.users.users-table>
            </div>
            <!-- Modal de confirmation - Reset Force Index -->
            <x-modal name="confirm-forceList-reset" focusable>
                <div class="p-6" x-data="{ confirmText: '', isValid() { return this.confirmText === 'RESET_FI' } }">
                    <!-- Icon d'avertissement -->
                    <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-orange-100 rounded-full">
                        <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">
                        {{ __('Reset Force Index') }}
                    </h3>
                    <div class="text-center mb-6">
                        <p class="text-sm text-gray-600 mb-3">
                            {{ __('Are you sure you want to reset the users force index?') }}
                        </p>
                        <p class="text-xs text-gray-500 bg-gray-50 p-3 rounded-lg">
                            {{ __('This action is irreversible. All associated data will be permanently changed.') }}
                        </p>
                    </div>
                    <!-- Champ de confirmation stylisé -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-3 text-center">
                            {{ __('To confirm, type') }} <span class="font-bold text-orange-600">"RESET_FI"</span> {{ __('in the box below') }}:
                        </label>
                        <input
                            type="text"
                            x-model="confirmText"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg text-center font-mono focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors"
                            placeholder="RESET_FI"
                            autocomplete="off"
                        >
                    </div>
                    <form method="get" action="{{ route('setForceList') }}">
                        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                            <button type="button"
                                    @click="$dispatch('close')"
                                    class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit"
                                    x-bind:disabled="!isValid()"
                                    x-bind:class="isValid() ? 'bg-orange-600 hover:bg-orange-700 text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                                    class="flex-1 px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                                {{ __('Reset Force Index') }}
                            </button>
                        </div>
                    </form>
                </div>
            </x-modal>
            <!-- Modal de confirmation - Delete Force Index -->
            <x-modal name="confirm-forceList-deletion" focusable>
                <div class="p-6" x-data="{ confirmText: '', isValid() { return this.confirmText === 'DELETE_FI' } }">
                    <!-- Icon d'avertissement -->
                    <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">
                        {{ __('Delete Force Index') }}
                    </h3>
                    <div class="text-center mb-6">
                        <p class="text-sm text-gray-600 mb-3">
                            {{ __('Are you sure you want to delete all the users force index?') }}
                        </p>
                        <p class="text-xs text-red-600 bg-red-50 p-3 rounded-lg border border-red-200">
                            {{ __('This action is irreversible. All associated data will be permanently removed.') }}
                        </p>
                    </div>
                    <!-- Champ de confirmation stylisé -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-3 text-center">
                            {{ __('To confirm, type') }} <span class="font-bold text-red-600">"DELETE_FI"</span> {{ __('in the box below') }}:
                        </label>
                        <input
                            type="text"
                            x-model="confirmText"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg text-center font-mono focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                            placeholder="DELETE_FI"
                            autocomplete="off"
                        >
                    </div>
                    <form method="get" action="{{ route('deleteForceList') }}">
                        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                            <button type="button"
                                    @click="$dispatch('close')"
                                    class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit"
                                    x-bind:disabled="!isValid()"
                                    x-bind:class="isValid() ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                                    class="flex-1 px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                                {{ __('Delete Permanently') }}
                            </button>
                        </div>
                    </form>
                </div>
            </x-modal>
        </x-admin-block>
    </div>
</x-app-layout>