<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <!-- En-tête de page -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-4 space-y-4 sm:space-y-0">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">{{ __('Contact messages') }}</h2>
                    <p class="text-gray-600 text-sm sm:text-base">{{ __('Manage the contacts from our website here.') }}</p>
                </div>
                
                <!-- Statistiques rapides -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-4">
                    <div class="bg-blue-50 rounded-lg p-3 text-center border border-blue-200">
                        <div class="text-lg sm:text-xl font-bold text-blue-600">{{ $stats->get('totalNew') }}</div>
                        <div class="text-xs text-blue-700">Nouveaux</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-3 text-center border border-yellow-200">
                        <div class="text-lg sm:text-xl font-bold text-yellow-600">{{ $stats->get('totalPending') }}</div>
                        <div class="text-xs text-yellow-700">En cours</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-3 text-center border border-green-200">
                        <div class="text-lg sm:text-xl font-bold text-green-600">{{ $stats->get('totalProcessed') }}</div>
                        <div class="text-xs text-green-700">Traités</div>
                    </div>
                    <div class="bg-red-50 rounded-lg p-3 text-center border border-red-200">
                        <div class="text-lg sm:text-xl font-bold text-red-600">{{ $stats->get('totalRejected') }}</div>
                        <div class="text-xs text-red-700">Refusés</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barre de filtres modernisée -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center space-y-4 lg:space-y-0 lg:space-x-4">
                <!-- Recherche -->
                <div class="flex-1 max-w-md">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input 
                            type="text" 
                            wire:model.live.debounce.500ms="search"
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-club-blue focus:border-club-blue text-sm"
                            placeholder="{{ __('Search contacts...') }}">
                    </div>
                </div>

                <!-- Filtres -->
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                    <!-- Statut -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Status') }}</label>
                        <select wire:model.live="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-club-blue focus:border-club-blue">
                            <option value="">{{ __('All') }}</option>
                            <option value="new">{{ __('New') }}</option>
                            <option value="pending">{{ __('In Progress') }}</option>
                            <option value="processed">{{ __('Processed') }}</option>
                            <option value="rejected">{{ __('Refused') }}</option>
                        </select>
                    </div>

                    <!-- Type d'intérêt -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Type') }}</label>
                        <select wire:model.live="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-club-blue focus:border-club-blue">
                            <option value="">{{ __('All') }}</option>
                            <option value="discovering">{{ __('Discovering') }}</option>
                            <option value="interclub">{{ __('Interclub') }}</option>
                            <option value="sponsorship">{{ __('Sponsorship') }}</option>
                            <option value="subscription">{{ __('Subscription') }}</option>
                        </select>
                    </div>

                    <!-- Pagination -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Per page') }}</label>
                        <select wire:model.live="perPage" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-club-blue focus:border-club-blue">
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table des contacts -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- En-tête responsive -->
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-medium text-gray-900">
                    {{ __('Contacts') }} 
                    <span class="text-sm font-normal text-gray-500">({{ $contacts->total() }} résultats)</span>
                </h3>
            </div>

            <!-- Version desktop -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Contact') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Email') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Subject') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Date') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Status') }}
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($contacts as $contact)
                            <tr class="hover:bg-gray-50 transition-colors duration-200">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-club-blue flex items-center justify-center">
                                                <span class="text-sm font-medium text-white">
                                                    {{ strtoupper(substr($contact->first_name, 0, 1) . substr($contact->last_name, 0, 1)) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $contact->first_name }} {{ $contact->last_name }}
                                            </div>
                                            @if($contact->phone)
                                                <div class="text-sm text-gray-500">{{ $contact->phone }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $contact->email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $contact->interest ?: 'Non spécifié' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $contact->created_at->format('d/m/Y') }}
                                    <div class="text-xs text-gray-400">{{ $contact->created_at->format('H:i') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusConfig = [
                                            'new' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'label' => 'Nouveau'],
                                            'pending' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'label' => 'En cours'],
                                            'processed' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'label' => 'Traité'],
                                            'rejected' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'label' => 'Refusé'],
                                        ];
                                        $config = $statusConfig[$contact->status] ?? $statusConfig['nouveau'];
                                    @endphp
                                    <span class="{{ $config['bg'] }} {{ $config['text'] }} px-2 py-1 rounded-full text-xs font-medium">
                                        {{ $config['label'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <a href="{{ route('admin.contacts.show', $contact) }}" 
                                           class="bg-club-blue hover:bg-club-blue-light text-white p-2 rounded-lg transition-colors duration-200"
                                           title="{{ __('View details') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </a>
                                        @can('delete', Auth()->user())
                                            <button 
                                                wire:click="$set('selectedContactId', {{ $contact->id }})"
                                                @click="$dispatch('open-modal', 'confirm-delete-contact')"
                                                class="bg-red-600 hover:bg-red-700 text-white p-2 rounded-lg transition-colors duration-200"
                                                title="{{ __('Delete contact') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Version mobile -->
            <div class="lg:hidden">
                <div class="space-y-0">
                    @foreach ($contacts as $contact)
                        <div class="border-b border-gray-200 p-4 hover:bg-gray-50 transition-colors duration-200">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-club-blue flex items-center justify-center">
                                            <span class="text-sm font-medium text-white">
                                                {{ strtoupper(substr($contact->first_name, 0, 1) . substr($contact->last_name, 0, 1)) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $contact->first_name }} {{ $contact->last_name }}
                                        </div>
                                        <div class="text-sm text-gray-500">{{ $contact->email }}</div>
                                    </div>
                                </div>
                                
                                @php
                                    $statusConfig = [
                                        'nouveau' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-800', 'label' => 'Nouveau'],
                                        'en_cours' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'label' => 'En cours'],
                                        'traite' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'label' => 'Traité'],
                                        'refuse' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'label' => 'Refusé'],
                                    ];
                                    $config = $statusConfig[$contact->status] ?? $statusConfig['nouveau'];
                                @endphp
                                <span class="{{ $config['bg'] }} {{ $config['text'] }} px-2 py-1 rounded-full text-xs font-medium">
                                    {{ $config['label'] }}
                                </span>
                            </div>
                            
                            <div class="space-y-2 mb-3">
                                <div class="text-sm text-gray-600">
                                    <span class="font-medium">Sujet:</span> {{ $contact->interest ?: 'Non spécifié' }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $contact->created_at->format('d/m/Y à H:i') }}
                                </div>
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <a href="{{ route('admin.contacts.show', $contact) }}" 
                                   class="bg-club-blue hover:bg-club-blue-light text-white px-3 py-2 rounded-lg text-xs font-medium transition-colors duration-200">
                                    Voir détails
                                </a>
                                @can('delete', Auth()->user())
                                    <button 
                                        wire:click="$set('selectedContactId', {{ $contact->id }})"
                                        @click="$dispatch('open-modal', 'confirm-delete-contact')"
                                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg text-xs font-medium transition-colors duration-200">
                                        Supprimer
                                    </button>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Message si aucun résultat -->
            @if($contacts->isEmpty())
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2 2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Aucun contact trouvé</h3>
                    <p class="mt-1 text-sm text-gray-500">Essayez de modifier vos critères de recherche.</p>
                </div>
            @endif
        </div>

        <!-- Pagination -->
        @if($contacts->hasPages())
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mt-6">
                {{ $contacts->links() }}
            </div>
        @endif

        <!-- Modal de confirmation de suppression -->
        <x-modal name="confirm-delete-contact" focusable>
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                
                <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">
                    {{ __('Confirm deletion') }}
                </h3>
                
                <p class="text-sm text-gray-600 text-center mb-6">
                    {{ __('Are you sure you want to delete this contact? This action cannot be undone.') }}
                </p>
                
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                    <x-secondary-button @click="$dispatch('close')" class="flex-1">
                        {{ __('Cancel') }}
                    </x-secondary-button>
                    <x-danger-button 
                        wire:click="deleteContact"
                        @click="$dispatch('close')"
                        class="flex-1">
                        {{ __('Delete permanently') }}
                    </x-danger-button>
                </div>
            </div>
        </x-modal>
    </x-admin-block>
</x-app-layout>