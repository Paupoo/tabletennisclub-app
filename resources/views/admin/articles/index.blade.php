<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <!-- En-tête de page -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-4 space-y-4 sm:space-y-0">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">{{ __('Articles management') }}</h2>
                    <p class="text-gray-600 text-sm sm:text-base">{{ __('Manage all your articles here.') }}</p>
                </div>
                
                <!-- Statistiques rapides -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-4">
                    <div class="bg-green-50 rounded-lg p-3 text-center border border-green-200">
                        <div class="text-lg sm:text-xl font-bold text-green-600">{{ $stats->get('totalPublished') }}</div>
                        <div class="text-xs text-green-700">Publiés</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-3 text-center border border-yellow-200">
                        <div class="text-lg sm:text-xl font-bold text-yellow-600">{{ $stats->get('totalDraft') }}</div>
                        <div class="text-xs text-yellow-700">Brouillons</div>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-3 text-center border border-blue-200">
                        <div class="text-lg sm:text-xl font-bold text-blue-600">{{ $stats->get('totalPublic') }}</div>
                        <div class="text-xs text-blue-700">Publics</div>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-3 text-center border border-purple-200">
                        <div class="text-lg sm:text-xl font-bold text-purple-600">{{ $stats->get('totalPrivate') }}</div>
                        <div class="text-xs text-purple-700">Privés</div>
                    </div>
                </div>
            </div>

            <!-- Bouton d'ajout -->
            <div class="flex justify-start">
                <a href="{{ route('admin.articles.create') }}" 
                   class="bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    {{ __('New article') }}
                </a>
            </div>
        </div>

        <livewire:admin.articles.index />

        <!-- Modal de confirmation de suppression -->
        <x-modal name="confirm-delete-article" focusable>
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
                    {{ __('Are you sure you want to delete this article? This action cannot be undone.') }}
                </p>
                
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                    <x-secondary-button @click="$dispatch('close')" class="flex-1">
                        {{ __('Cancel') }}
                    </x-secondary-button>
                    <x-danger-button 
                        wire:click="deleteArticle"
                        @click="$dispatch('close')"
                        class="flex-1">
                        {{ __('Delete permanently') }}
                    </x-danger-button>
                </div>
            </div>
        </x-modal>
    </x-admin-block>
</x-app-layout>