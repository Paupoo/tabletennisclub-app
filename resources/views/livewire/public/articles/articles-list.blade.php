<div >
    <!-- Filtres -->
    @if($articles->total() > 0 || $activeFiltersCount > 0)
    <div class="bg-white border-b sticky top-16 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center space-y-4 lg:space-y-0">
                <!-- Filtres par date -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-4 sm:space-y-0 sm:space-x-4">
                    <div class="flex items-center space-x-2">
                        <label for="year" class="text-sm font-medium text-gray-700">{{ __('Year:') }}</label>
                        <select wire:model.live="year" id="year"
                                class="px-3 text-xs pr-8 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                            <option value="">{{ __('All years') }}</option>
                            @foreach($years as $yearOption)
                                <option value="{{ $yearOption }}">{{ $yearOption }}</option>
                            @endforeach
                        </select>
                    </div>
    
                    <div class="flex items-center space-x-2">
                        <label for="month" class="text-sm font-medium text-gray-700">{{ __('Month:') }}</label>
                        <select wire:model.live="month" id="month"
                                class="text-xs px-3 py-2 pr-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                            <option value="">{{ __('All months') }}</option>
                            @foreach($months as $monthValue => $monthName)
                                <option value="{{ $monthValue }}">{{ $monthName }}</option>
                            @endforeach
                        </select>
                    </div>
    
                    <div class="flex items-center space-x-2">
                        <label for="category" class="text-sm font-medium text-gray-700">{{ __('Category:') }}</label>
                        <select wire:model.live="category" id="category"
                                class="text-xs px-3 py-2 pr-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                            <option value="">{{ __('All categories') }}</option>
                            @foreach($categories as $categoryOption)
                                <option value="{{ $categoryOption }}">{{ $categoryOption }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
    
                <!-- Résultats et tri -->
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">
                        {{ trans_choice(':count article found|:count articles found', $articles->total()) }}
                    </span>
                    <select wire:model.live="sort"
                            class="text-xs px-3 py-2 pr-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent text-sm">
                        <option value="desc">{{ __('Most recent') }}</option>
                        <option value="asc">{{ __('Oldest') }}</option>
                    </select>
                </div>
            </div>
    
            <!-- Filtres actifs -->
            @if($activeFiltersCount > 0)
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="text-sm text-gray-600">{{ __('Active filters:') }}</span>

                    @if($year)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-club-blue text-white">
                            {{ __('Year:') }} {{ $year }}
                            <button wire:click="clearFilter('year')" class="ml-2 hover:text-club-yellow">×</button>
                        </span>
                    @endif

                    @if($month)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-club-blue text-white">
                            {{ __('Month:') }} {{ $months[$month] }}
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
                        {{ __('Clear all filters') }}
                    </button>
                </div>
            @endif
        </div>
    </div>
    @endif

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
                    <x-public.news-card-full :article="$article" :index="$index" />
                @endforeach
            </div>
    
            <!-- Pagination -->
            <div class="mt-12">
                {{ $articles->links() }}
            </div>
        @elseif($activeFiltersCount > 0)
            <!-- Filtres actifs sans résultat -->
            <div class="flex flex-col items-center justify-center gap-6 rounded-2xl bg-gray-50 px-6 py-20 text-center">
                <span class="text-6xl">🔍</span>
                <div class="max-w-md">
                    <h3 class="mb-2 text-xl font-semibold text-gray-900">{{ __('No articles found') }}</h3>
                    <p class="text-gray-500">{{ __('Try adjusting your search criteria or browse all news.') }}</p>
                </div>
                <button wire:click="clearAllFilters" class="rounded-lg bg-club-blue px-8 py-3 font-semibold text-white transition-colors hover:bg-club-blue-light">
                    {{ __('View all news') }}
                </button>
            </div>
        @else
            <!-- Aucun article en base -->
            <div class="flex flex-col items-center justify-center gap-6 rounded-2xl bg-gray-50 px-6 py-20 text-center">
                <span class="text-6xl">📰</span>
                <div class="max-w-md">
                    <h2 class="mb-2 text-2xl font-bold text-gray-900">{{ __('No articles published yet') }}</h2>
                    <p class="text-gray-500">{{ __("Club news coming soon — check back, there's always something happening!") }}</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('home') }}#join" class="rounded-lg bg-club-yellow px-8 py-3 font-semibold text-club-blue transition-colors hover:bg-club-yellow-light">
                        {{ __('Become a member') }}
                    </a>
                    <a href="{{ route('home') }}#contact" class="rounded-lg border-2 border-club-blue px-8 py-3 font-semibold text-club-blue transition-colors hover:bg-club-blue hover:text-white">
                        {{ __('Contact us') }}
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
