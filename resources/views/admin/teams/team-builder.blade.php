<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Create a member') }}
        </h2>
    </x-slot>

    <!-- Navigation actions -->
    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex flex-row gap-4">
                <form action="{{ route('dashboard') }}" method="GET">
                    <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
                </form>
                <form action="{{ route('teams.index') }}" method="GET">
                    <x-primary-button>{{ __('Manage Teams') }}</x-primary-button>
                </form>
                <form action="{{ route('teams.create') }}" method="GET">
                    <x-primary-button>{{ __('Create new team') }}</x-primary-button>
                </form>
            </div>
        </div>
    </div>

    <!-- Team Builder parameters -->
    <div class="mt-6">
        <x-admin-block>
            <form class="flex flex-col gap-4" action="{{ route('teamBuilder.create') }}" method="POST">
                @csrf

                <div>
                    <x-input-label for="season">{{ __('Pick up a season') }}</x-input-label>
                    <x-select-input class="mt-2 w-1/2 min-w-sm" id="season" name="season_id">
                        @foreach ($seasons as $season)
                            <option value="{{ $season->id }}" @isset($selectedSeason->id) @selected($selectedSeason->id === $season->id) @endisset>
                                {{ $season->name }}
                            </option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label for="playersPerTeamSelector">{{ __('Define your players per teams') }}</x-input-label>
                    <x-text-input 
                        class="w-24 mt-2" 
                        type="number" 
                        name="playersPerTeam" 
                        id="playersPerTeamSelector"
                        min="5" 
                        step="1" 
                        value="{{ old('playersPerTeam', $playersPerTeam ?? null) }}" 
                        required 
                    />
                    <x-input-error :messages="$errors->get('playersPerTeam')" />
                </div>

                <div>
                    <x-primary-button class="mt-2">{{ __('Build teams') }}</x-primary-button>
                </div>
            </form>
        </x-admin-block>
    </div>

    <!-- Generated teams preview -->
    @isset($teamsWithPlayers)
        <div class="mt-6">
            <x-admin-block>
                <form action="{{ route('saveTeams') }}" method="POST" class="flex flex-col gap-6">
                    @csrf

                    <div class="text-2xl font-bold">
                        {{ __('Season') }} {{ $selectedSeason->name }}
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach ($teamsWithPlayers as $teamName => $players)
                            <div class="border rounded-xl shadow-sm p-4 bg-white dark:bg-gray-900">
                                <h1 class="text-center font-extrabold text-lg text-club-blue">
                                    {{ $teamName }}
                                </h1>

                                <hr class="border-dashed my-4 dark:border-gray-600">

                                <!-- League & Category selection -->
                                <div class="flex flex-col gap-2">
                                    <x-input-label for="league{{ $teamName }}">{{ __('Pick up a league') }}</x-input-label>

                                    <x-select-input name="teams[{{ $teamName }}][level_id]" id="league{{ $teamName }}" class="text-sm" required>
                                        <option selected disabled>{{ __('Level')}}</option>
                                        @foreach ($leagueLevel as $level)
                                            <option value="{{ $level->name }}">{{ $level->getLabel() }}</option>
                                        @endforeach
                                    </x-select-input>

                                    <x-select-input name="teams[{{ $teamName }}][category_id]" class="text-sm" required>
                                        <option selected disabled>{{ __('Category')}}</option>
                                        @foreach ($leagueCategory as $category)
                                            <option value="{{ $category->name }}">{{ $category->getLabel() }}</option>
                                        @endforeach
                                    </x-select-input>

                                    <x-text-input 
                                        type="text" 
                                        name="teams[{{ $teamName }}][division]" 
                                        placeholder="{{ __('5E')}}" 
                                        class="text-sm"
                                        required 
                                    />
                                </div>

                                <hr class="border-dashed my-4 dark:border-gray-600">

                                <!-- Players list -->
                                <div class="flex flex-col gap-2">
                                    @foreach ($players as $player)
                                        <div class="flex items-center justify-between text-sm p-2 rounded-md hover:bg-club-blue/10">
                                            <span class="truncate w-44">
                                                {{ $player->force_list }} | {{ $player->ranking }} | {{ $player->last_name }} {{ $player->first_name }}
                                            </span>
                                            <div class="flex items-center gap-2">
                                                <x-select-input name="teams[{{ $teamName }}][players_id][]" class="text-xs">
                                                    @foreach ($teamsWithPlayers as $team => $value)
                                                        <option value="{{ $player->id }}" @selected($team == $teamName)>{{ $team }}</option>
                                                    @endforeach
                                                </x-select-input>
                                                <input type="radio" 
                                                    name="teams[{{ $teamName }}][captain_id]" 
                                                    value="{{ $player->id }}" 
                                                    title="{{ __('Mark as captain') }}">
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                        <input type="hidden" name="season_id" value="{{ $selectedSeason->id }}">
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>
                            {{ __('Save teams compositions') }}
                        </x-primary-button>
                    </div>
                </form>
            </x-admin-block>
        </div>
    @endisset
</x-app-layout>
