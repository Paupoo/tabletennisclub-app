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
            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                <button class="bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto">
                    <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Créer un compte
                </button>
                <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto">
                    <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    Envoyer un email
                </button>
            </div>
        </div>

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

                {{-- Affichage des erreurs de validation --}}
                @if ($errors->any())
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                        <strong class="font-bold">Erreur{{ $errors->count() > 1 ? 's' : '' }} :</strong>
                        <ul class="mt-2 list-disc list-inside text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
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
                <button class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto">
                    <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Modifier
                </button>
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
                    Générer PDF
                </button>
            </div>
        </div>
    </x-admin-block>
</x-app-layout>