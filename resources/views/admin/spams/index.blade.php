{{-- resources/views/admin/spam/index.blade.php --}}
<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <!-- En-tête de page -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-4 space-y-4 sm:space-y-0">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">{{ __('Spam Management') }}</h2>
                    <p class="text-gray-600 text-sm sm:text-base">{{ __('Monitor and manage spam attempts detected on your website.') }}</p>
                </div>
                
                <!-- Statistiques rapides -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-4">
                    <div class="bg-red-50 rounded-lg p-3 text-center border border-red-200">
                        <div class="text-lg sm:text-xl font-bold text-red-600">{{ $stats->get('totalSpams', 0) }}</div>
                        <div class="text-xs text-red-700">{{ __('Total spams') }}</div>
                    </div>
                    <div class="bg-orange-50 rounded-lg p-3 text-center border border-orange-200">
                        <div class="text-lg sm:text-xl font-bold text-orange-600">{{ $stats->get('todaySpams', 0) }}</div>
                        <div class="text-xs text-orange-700">{{ __('Today') }}</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-3 text-center border border-yellow-200">
                        <div class="text-lg sm:text-xl font-bold text-yellow-600">{{ $stats->get('uniqueIPs', 0) }}</div>
                        <div class="text-xs text-yellow-700">{{ __('Unique IPs') }}</div>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-3 text-center border border-blue-200">
                        <div class="text-lg sm:text-xl font-bold text-blue-600">{{ $stats->get('blockedIps', 0) }}</div>
                        <div class="text-xs text-blue-700">{{ __('Blocked IPs') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Composant Livewire -->
        <div class="bg-white rounded-lg shadow-lg">
            <livewire:admin.spams.index />
        </div>
    </x-admin-block>
</x-app-layout>