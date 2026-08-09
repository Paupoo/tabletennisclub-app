<div>
    @if($events->isNotEmpty() || $activeFiltersCount > 0)
        <x-public.filter-bar>
            <x-slot:filters>
                <div class="flex items-center gap-2">
                    <label for="seasonId" class="text-sm font-medium text-gray-600">{{ __('Season:') }}</label>
                    <x-public.filter-select wire:model.live="seasonId" id="seasonId">
                        <option value="0">{{ __('All seasons') }}</option>
                        @foreach($seasons as $season)
                            <option value="{{ $season->id }}">{{ $season->name }}</option>
                        @endforeach
                    </x-public.filter-select>
                </div>

                <div class="flex items-center gap-2">
                    <label for="type" class="text-sm font-medium text-gray-600">{{ __('Type:') }}</label>
                    <x-public.filter-select wire:model.live="type" id="type">
                        <option value="">{{ __('All types') }}</option>
                        @foreach($eventTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-public.filter-select>
                </div>
            </x-slot:filters>

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

                        @if($type && isset($eventTypes[$type]))
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-club-blue text-white">
                                {{ $eventTypes[$type] }}
                                <button wire:click="clearFilter('type')" class="ml-2 hover:text-club-yellow">×</button>
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

    {{-- Events grid --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 mb-8">
        @if($events->isNotEmpty())
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($events as $event)
                    <x-public.event-card :event="$event" />
                @endforeach
            </div>
        @elseif($activeFiltersCount > 0)
            <div class="flex flex-col items-center justify-center gap-6 rounded-2xl bg-gray-50 px-6 py-20 text-center">
                <span class="text-6xl">🔍</span>
                <div class="max-w-md">
                    <h3 class="mb-2 text-xl font-semibold text-gray-900">{{ __('No events found') }}</h3>
                    <p class="text-gray-500">{{ __('Try adjusting your filters.') }}</p>
                </div>
                <button wire:click="clearAllFilters" class="rounded-lg bg-club-blue px-8 py-3 font-semibold text-white transition-colors hover:bg-club-blue-light">
                    {{ __('View all events') }}
                </button>
            </div>
        @else
            <div class="flex flex-col items-center justify-center gap-6 rounded-2xl bg-gray-50 px-6 py-20 text-center">
                <span class="text-6xl">🏓</span>
                <div class="max-w-md">
                    <h2 class="mb-2 text-2xl font-bold text-gray-900">{{ __("It's break time!") }}</h2>
                    <p class="text-gray-500">{{ __("The club is taking a short breather. Meanwhile, take a look at what happened last season or what's coming next.") }}</p>
                </div>
                @if($previousSeason || $nextSeason)
                    <div class="flex flex-col gap-3 sm:flex-row">
                        @if($previousSeason)
                            <button wire:click="viewSeason({{ $previousSeason->id }})" class="rounded-lg border-2 border-club-blue px-8 py-3 font-semibold text-club-blue transition-colors hover:bg-club-blue hover:text-white">
                                {{ __('View last season') }}
                            </button>
                        @endif
                        @if($nextSeason)
                            <button wire:click="viewSeason({{ $nextSeason->id }})" class="rounded-lg border-2 border-club-blue px-8 py-3 font-semibold text-club-blue transition-colors hover:bg-club-blue hover:text-white">
                                {{ __('View next season') }}
                            </button>
                        @endif
                    </div>
                @endif
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

    {{-- Call to Action --}}
    @if($events->isNotEmpty())
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
            <div class="bg-club-blue rounded-lg p-8 text-white text-center">
                <h2 class="text-3xl font-bold mb-4">{{ __("Don't Miss Out!") }}</h2>
                <p class="text-xl mb-6 opacity-90">
                    {{ __('Join our events and become part of the club community. All levels welcome!') }}
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('home') }}#join" class="bg-club-yellow text-club-blue px-8 py-3 rounded-lg font-semibold hover:bg-club-yellow-light transition-colors">
                        {{ __('Become a member') }}
                    </a>
                    <a href="{{ route('home') }}#contact" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-club-blue transition-colors">
                        {{ __('Contact us') }}
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
