<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <!-- Navigation rapide -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                <form action="{{ route('dashboard') }}" method="GET">
                    <x-primary-button class="w-full sm:w-auto">{{ __('Dashboard') }}</x-primary-button>
                </form>
                <form action="{{ route('users.index') }}" method="GET">
                    <x-primary-button class="w-full sm:w-auto">{{ __('Manage members') }}</x-primary-button>
                </form>
            </div>
        </div>

        <!-- En-tête avec informations principales -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-4 space-y-4 sm:space-y-0">
                <div class="flex items-center space-x-4">
                    <!-- Photo de profil -->
                    <div class="flex-shrink-0">
                        <img class="rounded-full w-16 h-16 sm:w-20 sm:h-20 border-4 border-club-blue object-cover"
                            @if ($user->sex == \App\Enums\Sex::MEN->name) 
                                src="{{ asset('images/man.png') }}"
                            @elseif ($user->sex == \App\Enums\Sex::WOMEN->name)
                                src="{{ asset('images/woman.png') }}"
                            @endif
                            alt="Photo de profil">
                    </div>
                    
                    <!-- Nom et informations de base -->
                    <div class="flex-1">
                        <div class="flex items-center space-x-2 mb-2">
                            <h2 class="text-xl sm:text-2xl font-bold text-club-blue">
                                {{ $user->first_name }} {{ $user->last_name }}
                            </h2>
                            <span class="text-lg text-gray-600">
                                @if ($user->sex == \App\Enums\Sex::MEN->name)
                                    &#9794;
                                @elseif ($user->sex == \App\Enums\Sex::WOMEN->name)
                                    &#9792;
                                @endif
                            </span>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm sm:text-base text-gray-600">
                                <span class="font-medium">Email:</span> {{ $user->email }}
                            </p>
                            <p class="text-sm sm:text-base text-gray-600">
                                <span class="font-medium">Téléphone:</span> {{ $user->phone_number }}
                            </p>
                            @if($user->birthdate)
                                <p class="text-xs sm:text-sm text-gray-500">
                                    Né(e) le {{ $user->birthdate->format('d/m/Y') }} ({{ $user->age }} ans)
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
                
                <!-- Actions principales -->
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3" x-data="{ showDeleteModal: false }">
                    @can('update', $user)
                        <a href="{{ route('users.edit', $user) }}" class="bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto text-center">
                            <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            {{ __('Edit') }}
                        </a>
                    @endcan
                    
                    @can('delete', $user)
                        <button @click="showDeleteModal = true" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto">
                            <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            {{ __('Delete') }}
                        </button>

                        <!-- Modal de confirmation de suppression -->
                        <div x-show="showDeleteModal" 
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="fixed inset-0 z-50 overflow-y-auto" 
                             style="display: none;">
                            <div class="fixed inset-0 bg-black bg-opacity-50" @click="showDeleteModal = false"></div>
                            <div class="flex items-center justify-center min-h-screen px-4">
                                <div x-show="showDeleteModal"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 transform scale-95"
                                     x-transition:enter-end="opacity-100 transform scale-100"
                                     x-transition:leave="transition ease-in duration-200"
                                     x-transition:leave-start="opacity-100 transform scale-100"
                                     x-transition:leave-end="opacity-0 transform scale-95"
                                     class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md mx-auto">
                                    <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">
                                        Confirmer la suppression
                                    </h3>
                                    <p class="text-sm text-gray-600 text-center mb-6">
                                        Êtes-vous sûr de vouloir supprimer le membre <strong>{{ $user->first_name }} {{ $user->last_name }}</strong> ? Cette action est irréversible.
                                    </p>
                                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                                        <button @click="showDeleteModal = false" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                                            Annuler
                                        </button>
                                        <a href="{{ route('users.destroy', $user) }}" class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm text-center">
                                            Supprimer définitivement
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endcan
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Informations personnelles -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                <h3 class="text-lg sm:text-xl font-bold text-club-blue mb-4">Informations personnelles</h3>
                <div class="space-y-4">
                    <!-- Adresse -->
                    <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                        <div class="flex-shrink-0 mt-1">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">Adresse</p>
                            <p class="text-sm text-gray-800">{{ $user->street }}</p>
                            <p class="text-sm text-gray-800">{{ $user->city_code }} {{ $user->city_name }}</p>
                        </div>
                    </div>
                    
                    <!-- Date de naissance -->
                    @if($user->birthdate)
                        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                            <div class="flex-shrink-0">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Date de naissance</p>
                                <p class="text-sm text-gray-800">{{ $user->birthdate->format('d/m/Y') }} ({{ $user->age }} {{ __('years') }})</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Informations joueur -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                <h3 class="text-lg sm:text-xl font-bold text-club-blue mb-4">Informations joueur</h3>
                <div class="space-y-4">
                    <!-- Licence -->
                    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V4a2 2 0 114 0v2m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">{{ __('Licence') }}</p>
                            <p class="text-sm text-gray-800">{{ $user->licence }}</p>
                        </div>
                    </div>

                    <!-- Classement -->
                    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">{{ __('Ranking') }}</p>
                            <p class="text-sm text-gray-800">{{ $user->ranking }}</p>
                        </div>
                    </div>

                    <!-- Équipes -->
                    @if($user->teams->count() > 0)
                        <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                            <div class="flex-shrink-0 mt-1">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Équipes</p>
                                <div class="space-y-1">
                                    @foreach ($user->teams as $team)
                                        <span class="inline-block bg-club-blue text-white px-2 py-1 rounded-full text-xs">
                                            {{ $team->league->level }} {{ $team->league->division }} {{ $team->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Statistiques de match -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mt-6">
            <h3 class="text-lg sm:text-xl font-bold text-club-blue mb-4">{{ __('Matches played') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-green-50 rounded-lg p-4 text-center border border-green-200">
                    <div class="flex items-center justify-center w-8 h-8 mx-auto mb-2 bg-green-600 rounded-full">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="text-xl sm:text-2xl font-bold text-green-600">13</div>
                    <div class="text-xs sm:text-sm text-green-700 font-medium">Victoires</div>
                </div>
                
                <div class="bg-red-50 rounded-lg p-4 text-center border border-red-200">
                    <div class="flex items-center justify-center w-8 h-8 mx-auto mb-2 bg-red-600 rounded-full">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="text-xl sm:text-2xl font-bold text-red-600">3</div>
                    <div class="text-xs sm:text-sm text-red-700 font-medium">Défaites</div>
                </div>
                
                <div class="bg-club-blue bg-opacity-10 rounded-lg p-4 text-center border border-club-blue border-opacity-20">
                    <div class="flex items-center justify-center w-8 h-8 mx-auto mb-2 bg-club-blue rounded-full">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="text-xl sm:text-2xl font-bold text-club-blue">81%</div>
                    <div class="text-xs sm:text-sm text-club-blue font-medium">Taux de victoire</div>
                </div>
            </div>
        </div>
    </x-admin-block>
</x-app-layout>