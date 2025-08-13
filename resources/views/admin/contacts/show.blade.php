<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <!-- En-tête avec informations principales -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-4 space-y-4 sm:space-y-0">
                <div class="flex-1">
                    <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">
                        {{ $contact->first_name }} {{ $contact->last_name }}
                    </h2>
                    <div class="space-y-1">
                        <p class="text-sm sm:text-base text-gray-600">
                            <span class="font-medium">Email:</span> {{ $contact->email }}
                        </p>
                        @if($contact->phone)
                            <p class="text-sm sm:text-base text-gray-600">
                                <span class="font-medium">Téléphone:</span> {{ $contact->phone }}
                            </p>
                        @endif
                        <p class="text-xs sm:text-sm text-gray-500">
                            Demande reçue le {{ $contact->created_at->format('d/m/Y à H:i') }}
                        </p>
                    </div>
                </div>
                
                <!-- Status Badge -->
                <div class="self-start">
                    @php
                        $statusConfig = [
                            'nouveau' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800'],
                            'en_cours' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800'],
                            'traite' => ['bg' => 'bg-green-100', 'text' => 'text-green-800'],
                            'refuse' => ['bg' => 'bg-red-100', 'text' => 'text-red-800'],
                        ];
                        $config = $statusConfig[$contact->status] ?? $statusConfig['nouveau'];
                    @endphp
                    <span class="{{ $config['bg'] }} {{ $config['text'] }} px-3 py-1 rounded-full text-xs sm:text-sm font-medium">
                        {{ ucfirst(str_replace('_', ' ', $contact->status)) }}
                    </span>
                </div>
            </div>

            <!-- Actions principales -->
            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3" x-data="{ emailTemplateOpen: false }">
                @if ($contact->interest === 'join' && !\App\Models\User::where('email', $contact->email)->exists())
                    <form action="{{ route('admin.contacts.invite-new-user') }}" method="POST">
                        @csrf
                        @php
                            $password = 'kjfeAL978$"lklaf';
                        @endphp
                        <input type="hidden" name="first_name" value="{{ $contact->first_name }}">
                        <input type="hidden" name="last_name" value="{{ $contact->last_name }}">
                        <input type="hidden" name="email" value="{{ $contact->email }}">
                        <input type="hidden" name="sex" value="{{ App\Enums\Sex::OTHER->name }}">
                        <input type="hidden" name="password" value="{{ $password }}">
                        <input type="hidden" name="password_confirmation" value="{{ $password }}">
                        <button type="submit" class="bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto" title="{{ __('Create an account and send invitation') }}">
                            <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            Créer un compte
                        </button>
                    </form>
                @endif

                <!-- Bouton Email avec dropdown -->
                <div class="relative">
                    <button 
                        @click="emailTemplateOpen = !emailTemplateOpen"
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto flex items-center justify-center"
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

                    <!-- Menu dropdown des templates -->
                    <div 
                        x-show="emailTemplateOpen" 
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        @click.away="emailTemplateOpen = false"
                        class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-50"
                        style="display: none;"
                    >
                        <div class="py-2">
                            <div class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b">
                                Choisir un template
                            </div>

                            <form action="{{ route('admin.contacts.send-email', $contact) }}" method="POST" class="divide-y divide-gray-100">
                                @csrf
                                
                                <!-- Template de bienvenue -->
                                <button 
                                    type="submit" 
                                    name="template" 
                                    value="welcome"
                                    class="w-full px-4 py-3 text-left hover:bg-gray-50 transition-colors group"
                                    @click="emailTemplateOpen = false"
                                >
                                    <div class="flex items-start space-x-3">
                                        <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-1.586z"></path>
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900">Email de bienvenue</p>
                                            <p class="text-xs text-gray-500 mt-1">Accueil chaleureux avec informations générales du club</p>
                                        </div>
                                    </div>
                                </button>

                                <!-- Template d'information adhésion -->
                                <button 
                                    type="submit" 
                                    name="template" 
                                    value="membership_info"
                                    class="w-full px-4 py-3 text-left hover:bg-gray-50 transition-colors group"
                                    @click="emailTemplateOpen = false"
                                >
                                    <div class="flex items-start space-x-3">
                                        <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center group-hover:bg-green-200 transition-colors">
                                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900">Informations adhésion</p>
                                            <p class="text-xs text-gray-500 mt-1">Détails sur les tarifs, licences et démarches</p>
                                        </div>
                                    </div>
                                </button>

                                <!-- Template de refus poli -->
                                <button 
                                    type="submit" 
                                    name="template" 
                                    value="polite_decline"
                                    class="w-full px-4 py-3 text-left hover:bg-gray-50 transition-colors group"
                                    @click="emailTemplateOpen = false"
                                >
                                    <div class="flex items-start space-x-3">
                                        <div class="flex-shrink-0 w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center group-hover:bg-orange-200 transition-colors">
                                            <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L4.35 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900">Refus poli</p>
                                            <p class="text-xs text-gray-500 mt-1">Réponse courtoise en cas de refus d'adhésion</p>
                                        </div>
                                    </div>
                                </button>

                                <!-- Template de demande compléments -->
                                <button 
                                    type="submit" 
                                    name="template" 
                                    value="request_info"
                                    class="w-full px-4 py-3 text-left hover:bg-gray-50 transition-colors group"
                                    @click="emailTemplateOpen = false"
                                >
                                    <div class="flex items-start space-x-3">
                                        <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center group-hover:bg-purple-200 transition-colors">
                                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900">Demande d'informations</p>
                                            <p class="text-xs text-gray-500 mt-1">Solliciter des précisions ou documents manquants</p>
                                        </div>
                                    </div>
                                </button>

                                <!-- Template personnalisé -->
                                <button 
                                    type="submit" 
                                    name="template" 
                                    value="custom"
                                    class="w-full px-4 py-3 text-left hover:bg-gray-50 transition-colors group"
                                    @click="emailTemplateOpen = false"
                                >
                                    <div class="flex items-start space-x-3">
                                        <div class="flex-shrink-0 w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center group-hover:bg-gray-200 transition-colors">
                                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900">Message personnalisé</p>
                                            <p class="text-xs text-gray-500 mt-1">Rédiger un email sur mesure</p>
                                        </div>
                                    </div>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reste du contenu identique... -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Informations de contact -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                <h3 class="text-lg sm:text-xl font-bold text-club-blue mb-4">Informations de contact</h3>
                <div class="space-y-3">
                    <div class="border-b border-gray-100 pb-2">
                        <span class="text-xs sm:text-sm font-medium text-gray-500">Centre d'intérêt</span>
                        <p class="text-sm sm:text-base text-gray-800 mt-1">{{ $contact->interest ?: 'Non spécifié' }}</p>
                    </div>
                    
                    @if($contact->message)
                        <div class="border-b border-gray-100 pb-2">
                            <span class="text-xs sm:text-sm font-medium text-gray-500">Message</span>
                            <p class="text-sm sm:text-base text-gray-800 mt-1 whitespace-pre-line">{{ $contact->message }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Changement de statut -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                <h3 class="text-lg sm:text-xl font-bold text-club-blue mb-4">Gestion du statut</h3>

                <form action="{{ route('admin.contacts.update', $contact) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <div class="space-y-3">
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="radio" name="status" value="new" class="text-blue-600" {{ $contact->status === 'new' ? 'checked' : '' }}>
                            <span class="text-xs sm:text-sm font-medium text-blue-700">Nouveau</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="radio" name="status" value="pending" class="text-yellow-600" {{ $contact->status === 'pending' ? 'checked' : '' }}>
                            <span class="text-xs sm:text-sm font-medium text-yellow-700">En cours</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="radio" name="status" value="processed" class="text-green-600" {{ $contact->status === 'processed' ? 'checked' : '' }}>
                            <span class="text-xs sm:text-sm font-medium text-green-700">Traité</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="radio" name="status" value="rejected" class="text-red-600" {{ $contact->status === 'rejected' ? 'checked' : '' }}>
                            <span class="text-xs sm:text-sm font-medium text-red-700">Refusé</span>
                        </label>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full">
                            Mettre à jour le statut
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Informations adhésion (si présentes) -->
        @if($contact->interest === 'join')
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mt-6">
                <h3 class="text-lg sm:text-xl font-bold text-club-blue mb-4">Informations d'adhésion</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    @if($contact->membership_family_members)
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-xl sm:text-2xl font-bold text-club-blue">{{ $contact->membership_family_members }}</div>
                            <div class="text-xs sm:text-sm text-gray-600">Licence{{ $contact->membership_family_members > 1 ? 's' : '' }} récréative{{ $contact->membership_family_members > 1 ? 's' : '' }}</div>
                        </div>
                    @endif
                    
                    @if($contact->membership_competitors)
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-xl sm:text-2xl font-bold text-green-600">{{ $contact->membership_competitors }}</div>
                            <div class="text-xs sm:text-sm text-gray-600">Licence{{ $contact->membership_competitors > 1 ? 's' : '' }} compétitive{{ $contact->membership_family_members > 1 ? 's' : '' }}</div>
                        </div>
                    @endif
                    
                    @if($contact->membership_training_sessions)
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-xl sm:text-2xl font-bold text-club-yellow">{{ $contact->membership_training_sessions }}</div>
                            <div class="text-xs sm:text-sm text-gray-600">Séance{{ $contact->membership_training_sessions > 1 ? 's' : '' }} d'entraînement</div>
                        </div>
                    @endif
                    
                    @if($contact->membership_total_cost)
                        <div class="bg-gray-50 rounded-lg p-4 text-center">
                            <div class="text-xl sm:text-2xl font-bold text-club-blue">{{ $contact->membership_total_cost }}€</div>
                            <div class="text-xs sm:text-sm text-gray-600">Coût total</div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Actions supplémentaires -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mt-6">
            <h3 class="text-lg sm:text-xl font-bold text-club-blue mb-4">Actions supplémentaires</h3>
            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                <form action="{{ route('admin.contacts.destroy', $contact) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto">
                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Supprimer
                    </button>
                </form>
                <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto">
                    <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Générer PDF (MOCKUP)
                </button>
            </div>
        </div>
    </x-admin-block>

    <x-modal name="confirm-delete-contact" focusable>
        <form wire:submit.prevent="destroy({{ $user ?? null }})" class="p-6" x-data="{ confirmText: '', isValid() { return this.confirmText === 'DELETE' } }">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
            </div>
            
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 text-center mb-2">
                {{ __('Are you sure you want to delete this user?') }}
            </h2>

            <p class="text-sm text-gray-600 dark:text-gray-400 text-center mb-4">
                {{ __('This action is irreversible. All associated data will be permanently deleted.') }}
            </p>

            <!-- Champ de confirmation -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    {{ __('To confirm, type') }} <strong>"DELETE"</strong> {{ __('in the box below') }}:
                </label>
                <input 
                    type="text" 
                    x-model="confirmText"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    placeholder="DELETE"
                    autocomplete="off"
                >
            </div>

            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                <x-secondary-button @click="$dispatch('close')" class="flex-1">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button 
                    class="flex-1" 
                    x-bind:disabled="!isValid()"
                    x-bind:class="{ 'opacity-50 cursor-not-allowed': !isValid() }"
                    type="submit"
                >
                    {{ __('Delete permanently') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
    
</x-app-layout>