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


    </x-admin-block>
</x-app-layout>