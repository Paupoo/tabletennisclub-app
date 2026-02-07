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

        <livewire:admin.contacts.index />
    </x-admin-block>
</x-app-layout>