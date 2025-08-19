<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <!-- En-tête avec informations principales -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <!-- Première section : Informations de base -->
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
                    
                    <!-- Nom et avatar -->
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
                    </div>
                </div>
                
                <!-- Statistiques rapides -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-4">
                    <div class="bg-blue-50 rounded-lg p-3 text-center border border-blue-200">
                        <div class="text-lg sm:text-xl font-bold text-blue-600">15</div>
                        <div class="text-xs text-blue-700">Victoires</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-3 text-center border border-yellow-200">
                        <div class="text-lg sm:text-xl font-bold text-yellow-600">8</div>
                        <div class="text-xs text-yellow-700">Défaites   </div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-3 text-center border border-green-200">
                        <div class="text-lg sm:text-xl font-bold text-green-600">2</div>
                        <div class="text-xs text-green-700">Performances</div>
                    </div>
                    <div class="bg-red-50 rounded-lg p-3 text-center border border-red-200">
                        <div class="text-lg sm:text-xl font-bold text-red-600">3</div>
                        <div class="text-xs text-red-700">Contres</div>
                    </div>
                </div>
            </div>

            <!-- Deuxième section : Actions principales -->
            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3" x-data="{ emailTemplateOpen: false, showDeleteModal: false }">
                @can('update', $user)
                    <a href="{{ route('users.edit', $user) }}" class="inline-flex items-center justify-center px-3 py-2 bg-club-blue hover:bg-club-blue-light text-white text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        {{ __('Edit') }}
                    </a>
                @endcan
                
                {{-- @can('delete', $user)
                    <button @click="showDeleteModal = true" class="inline-flex items-center justify-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
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
                        
                        <div class="flex items-center justify-center min-h-screen px-4">
                            <div x-show="showDeleteModal"
                                @click.outside="showDeleteModal = false"
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
                @endcan --}}
                
            @can('sendEmail', $user)    
                <button 
                    @click="emailTemplateOpen = !emailTemplateOpen"
                    class="inline-flex items-center justify-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors"
                    type="button"
                >
                    <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    Envoyer un email
                    <svg class="w-4 h-4 ml-2 transition-transform" :class="{ 'rotate-180': emailTemplateOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                @endcan
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Informations personnelles -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                <h3 class="text-lg sm:text-xl font-bold text-club-blue mb-4">Informations personnelles</h3>
                <div class="space-y-4">
                    <!-- Contact -->
                    <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                        <div class="flex-shrink-0 mt-1">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">{{ __('Contact info') }}</p>
                            <p class="text-sm text-gray-800">Email:  {{ $user->email }}</p>
                            <p class="text-sm text-gray-800">Phone number: {{ $user->phone_number }}</p>
                        </div>
                    </div>
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

                    <!-- Compétition -->
                    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                        <div class="flex-shrink-0">
                            <x-ui.icon name="medal" class="w-5 h-5 {{ $user->is_competitor ? 'text-green-600' : 'text-red-500' }}" />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">{{ __('Competition') }}</p>
                            <p class="text-sm font-semibold {{ $user->is_competitor ? 'text-green-700' : 'text-red-600' }}">
                                {{ $user->is_competitor ? __('Can play in Interclubs') : __('Can\'t play in Interclub') }}
                            </p>
                        </div>
                    </div>  

                    <!-- Licence -->
                    <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V4a2 2 0 114 0v2m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500">{{ __('Licence') }}</p>
                            <p class="text-sm text-gray-800">{{ $user->licence ?? __('No licene') }}</p>
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
                                <p class="text-sm font-medium text-gray-500">Équipe{{ $user->teams->count() > 1 ? 's' : '' }}</p>
                                <div class="space-y-1">
                                    @foreach ($user->teams as $team)
                                        <p class="text-sm text-gray-800">
                                            {{ $team->league->level }} {{ $team->league->division }} {{ $team->name }}
                                        </p>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
                
            </div>
            

            <!-- Section Modification du Mot de Passe -->
            @can('updatePassword',  $user)
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
                <div class="flex items-start space-x-3 mb-6">
                    <div class="flex-shrink-0 mt-1">
                        <svg class="w-6 h-6 text-club-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-bold text-club-blue">{{ __('Update Password') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ __('Ensure your account is using a long, random password to stay secure.') }}
                        </p>
                    </div>
                </div>

                <form method="post" action="{{ route('password.update') }}" class="space-y-6">
                    @csrf
                    @method('put')
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <x-input-label for="update_password_current_password" :value="__('Current Password')" />
                            <x-text-input id="update_password_current_password" 
                                        name="current_password" 
                                        type="password" 
                                        class="mt-1 block w-full" 
                                        autocomplete="current-password" />
                            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
                        </div>
                        
                        <div>
                            <x-input-label for="update_password_password" :value="__('New Password')" />
                            <x-text-input id="update_password_password" 
                                        name="password" 
                                        type="password" 
                                        class="mt-1 block w-full" 
                                        autocomplete="new-password" />
                            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
                        </div>
                        
                        <div>
                            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
                            <x-text-input id="update_password_password_confirmation" 
                                        name="password_confirmation" 
                                        type="password" 
                                        class="mt-1 block w-full" 
                                        autocomplete="new-password" />
                            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-4 pt-4 border-t border-gray-200">
                        <button type="submit" class="bg-club-blue hover:bg-club-blue-light text-white px-6 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base">
                            <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            {{ __('Save') }}
                        </button>
                        
                        @if (session('status') === 'password-updated')
                            <p x-data="{ show: true }"
                            x-show="show"
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 transform scale-95"
                            x-transition:enter-end="opacity-100 transform scale-100"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 transform scale-100"
                            x-transition:leave-end="opacity-0 transform scale-95"
                            x-init="setTimeout(() => show = false, 3000)"
                            class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 text-sm rounded-lg">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                {{ __('Saved.') }}
                            </p>
                        @endif
                    </div>
                </form>
            </div>
            @endcan

            <!-- Section Suppression du Compte -->
            @can('delete',  $user)
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6" x-data="{ showDeleteModal2: false }">
                <div class="flex items-start space-x-3 mb-6">
                    <div class="flex-shrink-0 mt-1">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg sm:text-xl font-bold text-red-600">{{ __('Delete Account') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
                        </p>
                    </div>
                </div>
                
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            <span class="text-sm font-medium text-red-800">Zone dangereuse</span>
                        </div>
                        <button @click="showDeleteModal2 = true" 
                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                            <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            {{ __('Delete Account') }}
                        </button>
                    </div>
                </div>
                
                <!-- Modal de confirmation de suppression de compte -->
                <div x-show="showDeleteModal2" 
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 z-50 overflow-y-auto" 
                    style="display: none;">
                    <div class="flex items-center justify-center min-h-screen px-4">
                        <div x-show="showDeleteModal2"
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 transform scale-95"
                            x-transition:enter-end="opacity-100 transform scale-100"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 transform scale-100"
                            x-transition:leave-end="opacity-0 transform scale-95"
                            class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md mx-auto">
                            
                            <form method="post" action="{{ route('profile.destroy') }}">
                                @csrf
                                @method('delete')
                                
                                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                </div>
                                
                                <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">
                                    {{ __('Are you sure you want to delete your account?') }}
                                </h3>
                                
                                <p class="text-sm text-gray-600 text-center mb-6">
                                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                                </p>
                                
                                <div class="mb-6">
                                    <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />
                                    <x-text-input id="password"
                                                name="password"
                                                type="password"
                                                class="mt-1 block w-full"
                                                placeholder="{{ __('Password') }}"
                                                required />
                                    <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                                </div>
                                
                                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                                    <button type="button" 
                                            @click="showDeleteModal2 = false" 
                                            class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                                        {{ __('Cancel') }}
                                    </button>
                                    <button type="submit" 
                                            class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        {{ __('Delete Account') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @endcan
        </div>

       <!-- Section Liste des matchs -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mt-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg sm:text-xl font-bold text-club-blue">Matchs joués</h3>
                <div class="text-sm text-gray-500">
                    Saison 2024-2025
                </div>
            </div>
            
            <!-- Statistiques rapides en haut -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-green-50 rounded-lg p-3 text-center border border-green-200">
                    <div class="text-lg font-bold text-green-600">13</div>
                    <div class="text-xs text-green-700">Victoires</div>
                </div>
                <div class="bg-red-50 rounded-lg p-3 text-center border border-red-200">
                    <div class="text-lg font-bold text-red-600">3</div>
                    <div class="text-xs text-red-700">Défaites</div>
                </div>
                <div class="bg-club-blue bg-opacity-10 rounded-lg p-3 text-center border border-club-blue border-opacity-20">
                    <div class="text-lg font-bold text-club-blue">81%</div>
                    <div class="text-xs text-club-blue">Taux victoire</div>
                </div>
            </div>

            <!-- Liste des matchs -->
            <div class="space-y-4">
                <!-- Match 1 - Victoire -->
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Journée 8 - Division 2B</p>
                                    <p class="text-xs text-gray-500">TC Rochefort vs TC Malonne</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Adversaire</p>
                                <p class="text-sm font-medium">B4</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Résultat</p>
                                <p class="text-lg font-bold text-green-600">3-1</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Date</p>
                                <p class="text-sm">15/03/24</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match 2 - Défaite -->
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Journée 7 - Division 2B</p>
                                    <p class="text-xs text-gray-500">TC Salzinnes vs TC Malonne</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Adversaire</p>
                                <p class="text-sm font-medium">B6</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Résultat</p>
                                <p class="text-lg font-bold text-red-600">1-3</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Date</p>
                                <p class="text-sm">08/03/24</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match 3 - Victoire -->
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Journée 6 - Division 2B</p>
                                    <p class="text-xs text-gray-500">TC Malonne vs TC Ciney</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Adversaire</p>
                                <p class="text-sm font-medium">D6</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Résultat</p>
                                <p class="text-lg font-bold text-green-600">3-0</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Date</p>
                                <p class="text-sm">01/03/24</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match 4 - Victoire serrée -->
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Journée 5 - Division 2B</p>
                                    <p class="text-xs text-gray-500">TC Andenne vs TC Malonne</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Adversaire</p>
                                <p class="text-sm font-medium">C0</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Résultat</p>
                                <p class="text-lg font-bold text-green-600">3-2</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Date</p>
                                <p class="text-sm">22/02/24</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match 5 - Défaite -->
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Journée 4 - Division 2B</p>
                                    <p class="text-xs text-gray-500">TC Malonne vs RTC Namur</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Adversaire</p>
                                <p class="text-sm font-medium">B0</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Résultat</p>
                                <p class="text-lg font-bold text-red-600">0-3</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Date</p>
                                <p class="text-sm">15/02/24</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bouton "Voir plus" -->
            <div class="mt-6 text-center">
                <button class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                    Voir tous les matchs (16)
                </button>
            </div>
        </div>
    </x-admin-block>
</x-app-layout>