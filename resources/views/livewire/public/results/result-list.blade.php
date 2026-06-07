<div>
    <x-public.filter-bar>
        <x-slot:filters>
            <div class="flex items-center gap-2">
                <label for="seasonId" class="text-sm font-medium text-gray-600">{{ __('Season:') }}</label>
                <x-public.filter-select wire:model.live="seasonId" id="seasonId">
                    @foreach($seasons as $season)
                        <option value="{{ $season->id }}">{{ $season->name }}</option>
                    @endforeach
                </x-public.filter-select>
            </div>

            @if($availableCategories->isNotEmpty())
                <div class="flex items-center gap-2">
                    <label for="category" class="text-sm font-medium text-gray-600">{{ __('Category:') }}</label>
                    <x-public.filter-select wire:model.live="category" id="category">
                        <option value="">{{ __('All') }}</option>
                        @foreach($availableCategories as $cat)
                            <option value="{{ $cat['value'] }}">{{ $cat['label'] }}</option>
                        @endforeach
                    </x-public.filter-select>
                </div>
            @endif

            @if($availableDivisions->isNotEmpty())
                <div class="flex items-center gap-2">
                    <label for="division" class="text-sm font-medium text-gray-600">{{ __('Division:') }}</label>
                    <x-public.filter-select wire:model.live="division" id="division">
                        <option value="">{{ __('All') }}</option>
                        @foreach($availableDivisions as $div)
                            <option value="{{ $div }}">{{ $div }}</option>
                        @endforeach
                    </x-public.filter-select>
                </div>
            @endif

            @if($availableTeams->isNotEmpty())
                <div class="flex items-center gap-2">
                    <label for="teamId" class="text-sm font-medium text-gray-600">{{ __('Team:') }}</label>
                    <x-public.filter-select wire:model.live="teamId" id="teamId">
                        <option value="0">{{ __('All') }}</option>
                        @foreach($availableTeams as $team)
                            <option value="{{ $team['id'] }}">{{ $team['name'] }}</option>
                        @endforeach
                    </x-public.filter-select>
                </div>
            @endif
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

                    @if($category)
                        @php $activeCat = $availableCategories->firstWhere('value', $category); @endphp
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-club-blue text-white">
                            {{ $activeCat ? $activeCat['label'] : $category }}
                            <button wire:click="clearFilter('category')" class="ml-2 hover:text-club-yellow">×</button>
                        </span>
                    @endif

                    @if($division)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-club-blue text-white">
                            {{ __('Division:') }} {{ $division }}
                            <button wire:click="clearFilter('division')" class="ml-2 hover:text-club-yellow">×</button>
                        </span>
                    @endif

                    @if($teamId > 0)
                        @php $selectedTeam = $availableTeams->firstWhere('id', $teamId); @endphp
                        @if($selectedTeam)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-club-blue text-white">
                                {{ __('Team:') }} {{ $selectedTeam['name'] }}
                                <button wire:click="clearFilter('teamId')" class="ml-2 hover:text-club-yellow">×</button>
                            </span>
                        @endif
                    @endif

                    <button wire:click="clearAllFilters" class="text-xs text-club-blue hover:text-club-blue-light font-medium">
                        {{ __('Clear all filters') }}
                    </button>
                </div>
            </x-slot:chips>
        @endif
    </x-public.filter-bar>

    {{-- Results content --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16 pt-8">
        @forelse($teamsByCategory as $group)
            @php
                $catStyles = [
                    'MEN'      => ['border' => 'border-blue-200',  'bg' => 'bg-blue-50',  'text' => 'text-blue-700',  'dot' => 'bg-blue-500'],
                    'WOMEN'    => ['border' => 'border-pink-200',  'bg' => 'bg-pink-50',  'text' => 'text-pink-700',  'dot' => 'bg-pink-500'],
                    'VETERANS' => ['border' => 'border-amber-200', 'bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'dot' => 'bg-amber-500'],
                ];
                $style = $catStyles[$group['category']] ?? $catStyles['MEN'];
            @endphp

            <div class="flex items-center gap-4 mb-8 mt-10 first:mt-0">
                <span class="inline-flex items-center gap-2 rounded-full {{ $style['bg'] }} border {{ $style['border'] }} px-5 py-2 shadow-xs">
                    <span class="h-2.5 w-2.5 rounded-full {{ $style['dot'] }}"></span>
                    <span class="text-base font-bold {{ $style['text'] }} tracking-wide">{{ $group['label'] }}</span>
                </span>
                <div class="flex-1 border-t {{ $style['border'] }}"></div>
            </div>

            @foreach($group['teams'] as $team)
                <x-public.team-results :team="$team" />
            @endforeach
        @empty
            <div class="text-center py-12 bg-gray-50 rounded-lg">
                @if($activeFiltersCount > 0)
                    <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('No results match your filters') }}</h3>
                    <p class="text-gray-600 mb-4">{{ __('Try adjusting your filters.') }}</p>
                    <button wire:click="clearAllFilters" class="rounded-lg bg-club-blue px-6 py-2 text-sm font-semibold text-white transition-colors hover:bg-club-blue-light">
                        {{ __('Clear all filters') }}
                    </button>
                @else
                    <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('No results available') }}</h3>
                    <p class="text-gray-600">{{ __('Results will be published after the first competitions.') }}</p>
                @endif
            </div>
        @endforelse
    </div>
</div>
