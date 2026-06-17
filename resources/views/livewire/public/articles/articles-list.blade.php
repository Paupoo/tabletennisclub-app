<div>
    @if($articles->total() > 0 || $activeFiltersCount > 0)
        <x-public.filter-bar :count="trans_choice(':count article found|:count articles found', $articles->total())">
            <x-slot:filters>
                <div class="flex items-center gap-2">
                    <label for="season" class="text-sm font-medium text-gray-600">{{ __('Season:') }}</label>
                    <x-public.filter-select wire:model.live="seasonId" id="season">
                        <option value="0">{{ __('All seasons') }}</option>
                        @foreach($seasons as $season)
                            <option value="{{ $season->id }}">{{ $season->name }}</option>
                        @endforeach
                    </x-public.filter-select>
                </div>

                <div class="flex items-center gap-2">
                    <label for="category" class="text-sm font-medium text-gray-600">{{ __('Category:') }}</label>
                    <x-public.filter-select wire:model.live="category" id="category">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach($categories as $categoryOption)
                            <option value="{{ $categoryOption }}">{{ $categoryOption }}</option>
                        @endforeach
                    </x-public.filter-select>
                </div>
            </x-slot:filters>

            <x-slot:sort>
                <x-public.filter-select wire:model.live="sort">
                    <option value="desc">{{ __('Most recent') }}</option>
                    <option value="asc">{{ __('Oldest') }}</option>
                </x-public.filter-select>
            </x-slot:sort>

            @if($activeFiltersCount > 0)
                <x-slot:chips>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="text-sm text-gray-600">{{ __('Active filters:') }}</span>

                        @if($seasonId !== $defaultSeasonId && $seasons->firstWhere('id', $seasonId))
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-club-blue text-white">
                                {{ $seasons->firstWhere('id', $seasonId)->name }}
                                <button wire:click="clearFilter('seasonId')" class="ml-2 hover:text-club-yellow">×</button>
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
                </x-slot:chips>
            @endif
        </x-public.filter-bar>
    @endif

    {{-- Articles --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @if($articles->count() > 0)
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($articles as $index => $article)
                    <x-public.news-card-full :article="$article" :index="$index" />
                @endforeach
            </div>

            <div class="mt-12">
                {{ $articles->links() }}
            </div>
        @elseif($activeFiltersCount > 0)
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
