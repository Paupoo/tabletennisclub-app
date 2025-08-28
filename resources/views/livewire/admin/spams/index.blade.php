{{-- resources/views/livewire/admin/spam/index.blade.php --}}
<div x-data="{ 
    showFilters: @entangle('showFilters'),
    selectedItems: @entangle('selectedItems'),
    selectAll: @entangle('selectAll')
}">
    <!-- Barre d'outils -->
    <div class="border-b border-gray-200 p-4 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
            <!-- Recherche et filtres -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-2 sm:space-y-0 sm:space-x-3">
                <div class="relative">
                    <input type="text" 
                           wire:model.live.debounce.300ms="search"
                           placeholder="Rechercher par IP ou User Agent..."
                           class="w-full sm:w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-club-blue focus:border-club-blue">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>
                
                <button wire:click="toggleFilters" 
                        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    {{ __('Filters') }}
                    @if($this->hasActiveFilters())
                        <span class="ml-1 bg-club-blue text-white text-xs rounded-full px-2 py-0.5">!</span>
                    @endif
                </button>
            </div>

            <!-- Actions -->
            <div class="flex items-center space-x-2">
                @if(count($selectedItems) > 0)
                    <button wire:click="bulkDelete" 
                            wire:confirm="Êtes-vous sûr de vouloir supprimer {{ count($selectedItems) }} spam(s) ?"
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        {{ __('Delete') }} ({{ count($selectedItems) }})
                    </button>
                @endif
                
                <button wire:click="exportData" 
                        class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    {{ __('Export') }}
                </button>
                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Per page') }}</label>
                    <select wire:model.live="perPage" class="border border-gray-300 rounded-lg px-3 py-2 pr-8 text-sm focus:ring-club-blue focus:border-club-blue">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
            
        </div>

        <!-- Panneau de filtres avancés -->
        @if($showFilters)
            <div class="mt-4 pt-4 border-t border-gray-200"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform -translate-y-2"
                 x-transition:enter-end="opacity-100 transform translate-y-0">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Period') }}</label>
                        <select wire:model.live="filters.period" 
                                class="block w-full border-gray-300 rounded-md focus:ring-club-blue focus:border-club-blue text-sm">
                            <option value="">{{ __('All dates') }}</option>
                            <option value="today">{{ __('Today') }}</option>
                            <option value="week">{{ __('This week') }}</option>
                            <option value="month">{{ __('This month') }}</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('User Agent Type') }}</label>
                        <select wire:model.live="filters.userAgentType" 
                                class="block w-full border-gray-300 rounded-md focus:ring-club-blue focus:border-club-blue text-sm">
                            <option value="">{{ __('All types') }}</option>
                            <option value="bot">{{ __('Bots') }}</option>
                            <option value="curl">{{ __('cURL/Scripts') }}</option>
                            <option value="browser">{{ __('Browsers') }}</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Specific IP') }}</label>
                        <input type="text" 
                               wire:model.live.debounce.500ms="filters.specificIp"
                               placeholder="192.168.1.1"
                               class="block w-full border-gray-300 rounded-md focus:ring-club-blue focus:border-club-blue text-sm">
                    </div>
                    
                    <div class="flex items-end">
                        <button wire:click="clearFilters" 
                                class="w-full px-3 py-2 text-sm text-gray-600 bg-gray-100 rounded-md hover:bg-gray-200">
                            {{ __('Reset') }}
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Sélection groupée -->
    <div class="px-4 sm:px-6 py-3 bg-gray-50 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <input type="checkbox" 
                       wire:model.live="selectAll"
                       class="h-4 w-4 text-club-blue focus:ring-club-blue border-gray-300 rounded">
                <label class="ml-2 text-sm text-gray-700">
                    {{ __('Select all') }}
                    @if(count($selectedItems) > 0)
                        <span class="text-club-blue font-medium">
                            ({{ count($selectedItems) }} {{ __('selected') }})
                        </span>
                    @endif
                </label>
            </div>
            <div class="text-sm text-gray-500">
                {{ $totalResults }} {{ __('result(s)') }} {{ __('on') }} {{ $stats->get('totalSpams') }}
            </div>
        </div>
    </div>

    <!-- Liste des spams -->
    <div class="divide-y divide-gray-200">
        @forelse($spams as $spam)
            <div class="p-4 sm:p-6 hover:bg-gray-50 transition-colors">
                <div class="flex items-start space-x-4">
                    <!-- Checkbox -->
                    <div class="flex-shrink-0 pt-1">
                        <input type="checkbox" 
                               value="{{ $spam->id }}"
                               wire:model.live="selectedItems"
                               class="h-4 w-4 text-club-blue focus:ring-club-blue border-gray-300 rounded">
                    </div>

                    <!-- Contenu principal -->
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between space-y-2 sm:space-y-0">
                            <!-- Informations principales -->
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1.5"></span>
                                        {{ __('Spam detected') }}
                                    </span>
                                    <span class="text-sm font-mono text-gray-900 bg-gray-100 px-2 py-1 rounded">{{ $spam->ip }}</span>
                                    <span class="text-xs text-gray-500">{{ $spam->created_at->format('d/m/Y H:i') }}</span>
                                </div>
                                
                                <p class="text-sm text-gray-600 mb-2">
                                    <span class="font-medium">{{ __('User Agent') }}:</span>
                                    <span class="font-mono">{{ $this->truncateText($spam->user_agent ?? 'N/A') }}</span>
                                </p>
                                
                                @if($spam->inputs)
                                    <div class="text-sm">
                                        <span class="font-medium text-gray-700">{{ __('Submitted data') }}:</span>
                                        <span class="text-gray-600">{{ $this->formatInputs($spam->inputs) }}</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center space-x-2">
                                @if($spam->ip)
                                    <button wire:click="blockIp('{{ $spam->ip }}')" 
                                            wire:confirm="Bloquer l'IP {{ $spam->ip }} ?"
                                            class="text-sm text-orange-600 hover:text-orange-800 font-medium">
                                        {{ __('Block IP') }}
                                    </button>
                                @endif
                                <button wire:click="deleteSpam({{ $spam->id }})" 
                                        wire:confirm="Supprimer ce spam ?"
                                        class="text-sm text-red-600 hover:text-red-800 font-medium">
                                    {{ __('Delete') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">{{ __('No spam found') }}</h3>
                <p class="mt-1 text-sm text-gray-500">
                    @if($this->hasActiveFilters())
                        {{ __('No results match your search criteria.') }}
                    @else
                        {{ __('No spam detected yet.') }}
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($spams->hasPages())
        <div class="bg-gray-50 px-4 sm:px-6 py-3 border-t border-gray-200">
            {{ $spams->links() }}
        </div>
    @endif

    <!-- Loading states -->
    <div wire:loading.delay wire:target="search,filters.period,filters.userAgentType,filters.specificIp">
        <div class="absolute inset-0 bg-white/50 flex items-center justify-center">
            <div class="flex items-center space-x-2">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-club-blue"></div>
                <span class="text-sm text-gray-600">{{ __('Loading...') }}</span>
            </div>
        </div>
    </div>
</div>

@script
<script>
    // Écouter les événements Livewire pour les notifications
    $wire.on('spam-deleted', (data) => {
        // Tu peux utiliser ton système de notifications existant
        console.log('Spam deleted:', data.message);
        // Exemple: showNotification(data.message, data.type);
    });

    $wire.on('spam-bulk-deleted', (data) => {
        console.log('Bulk delete:', data.message);
    });

    $wire.on('ip-blocked', (data) => {
        console.log('IP blocked:', data.message);
    });

    $wire.on('spam-error', (data) => {
        console.log('Error:', data.message);
    });

    $wire.on('export-started', (data) => {
        console.log('Export:', data.message);
    });
</script>
@endscript