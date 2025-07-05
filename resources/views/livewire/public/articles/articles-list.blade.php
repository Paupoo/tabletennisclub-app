<div >
    <!-- Filtres -->
    <div class="bg-white border-b sticky top-16 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center space-y-4 lg:space-y-0">
                <!-- Filtres par date -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-4 sm:space-y-0 sm:space-x-4">
                    <div class="flex items-center space-x-2">
                        <label for="year" class="text-sm font-medium text-gray-700">Année:</label>
                        <select wire:model.live="year" id="year"
                                class="px-3 text-xs py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                            <option value="">Toutes les années</option>
                            @foreach($years as $yearOption)
                                <option value="{{ $yearOption }}">{{ $yearOption }}</option>
                            @endforeach
                        </select>
                    </div>
    
                    <div class="flex items-center space-x-2">
                        <label for="month" class="text-sm font-medium text-gray-700">Mois:</label>
                        <select wire:model.live="month" id="month"
                                class="text-xs px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                            <option value="">Tous les mois</option>
                            @foreach($months as $monthValue => $monthName)
                                <option value="{{ $monthValue }}">{{ $monthName }}</option>
                            @endforeach
                        </select>
                    </div>
    
                    <div class="flex items-center space-x-2">
                        <label for="category" class="text-sm font-medium text-gray-700">Catégorie:</label>
                        <select wire:model.live="category" id="category"
                                class="text-xs px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                            <option value="">Toutes les catégories</option>
                            @foreach($categories as $categoryOption)
                                <option value="{{ $categoryOption }}">{{ $categoryOption }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
    
                <!-- Résultats et tri -->
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">
                        {{ $articles->total() }} article{{ $articles->total() > 1 ? 's' : '' }} trouvé{{ $articles->total() > 1 ? 's' : '' }}
                    </span>
                    <select wire:model.live="sort"
                            class="text-xs px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent text-sm">
                        <option value="desc">Plus récent</option>
                        <option value="asc">Plus ancien</option>
                    </select>
                </div>
            </div>
    
            <!-- Filtres actifs -->
            @if($activeFiltersCount > 0)
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="text-sm text-gray-600">Filtres actifs:</span>
    
                    @if($year)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-club-blue text-white">
                            Année: {{ $year }}
                            <button wire:click="clearFilter('year')" class="ml-2 hover:text-club-yellow">×</button>
                        </span>
                    @endif
    
                    @if($month)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-club-blue text-white">
                            Mois: {{ $months[$month] }}
                            <button wire:click="clearFilter('month')" class="ml-2 hover:text-club-yellow">×</button>
                        </span>
                    @endif
    
                    @if($category)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-club-blue text-white">
                            {{ $category }}
                            <button wire:click="clearFilter('category')" class="ml-2 hover:text-club-yellow">×</button>
                        </span>
                    @endif
    
                    <button wire:click="clearAllFilters" class="text-xs text-club-blue hover:text-club-blue-light font-medium">
                        Effacer tous les filtres
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- Loading indicator -->
    <div wire:loading class="fixed top-0 left-0 right-0 bg-club-blue bg-opacity-75 z-50">
        <div class="flex justify-center items-center h-2">
            <div class="animate-pulse bg-club-yellow h-2 w-full"></div>
        </div>
    </div>

    <!-- Articles -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @if($articles->count() > 0)
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($articles as $index => $article)
                    <x-news-card-full :article="$article" :index="$index" />
                @endforeach
            </div>
    
            <!-- Pagination -->
            <div class="mt-12">
                {{ $articles->links() }}
            </div>
        @else
            <!-- Aucun résultat -->
            <div class="text-center py-16">
                <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9.5a2.5 2.5 0 00-2.5-2.5H15"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Aucun article trouvé</h3>
                <p class="text-gray-600 mb-6">Essayez de modifier vos critères de recherche ou consultez toutes les actualités.</p>
                <button wire:click="clearAllFilters" class="bg-club-blue text-white px-6 py-3 rounded-lg hover:bg-club-blue-light transition-colors">
                    Voir toutes les actualités
                </button>
            </div>
        @endif
    </div>
</div>
