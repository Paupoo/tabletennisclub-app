<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Create a member') }}
        </h2>
    </x-slot>

    <div class="pt-6">
        <div class="relative mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex flex-row justify-start gap-4">
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

    <div class="mt-6">

        <x-admin-block>
            <form class="flex flex-col" action="{{ route('teamBuilder.create') }}" method="POST">
                @csrf
                <x-input-label for="season">{{ __('Pick up a season') }}</x-input-label>
                <x-select-input class="mt-2 w-1/2 w-min-sm" id="season" name="season_id">
                    @foreach ($seasons as $season)
                        <option value="{{ $season->id }}" @isset($selectedSeason->id)
                            @selected($selectedSeason->id === $season->id)
                        @endisset>{{ $season->name }} </option>
                        
                    @endforeach
                </x-select-input>

                <x-input-label class="mt-2"
                    for="playersPerTeamSelector">{{ __('Define your players per teams') }}</x-input-label>
                <x-text-input class="w-20 h-8 mt-2" type="number" name="playersPerTeam" id="playersPerTeamSelector"
                    min="5" step="1" value="{{ old('playersPerTeam', isset($playersPerTeam) ? $playersPerTeam : null) }}" required></x-text-input>
                <x-input-error class="" :messages="$errors->get('playersPerTeam')" />
                <x-primary-button class="mt-4 w-36">{{ __('Build teams') }}</x-primary-button>
            </form>
        </x-admin-block>

    </div>

    @isset($teamsWithPlayers)
        <div class="mt-6 w-fit m-auto">
            <x-admin-block>
                <form action="{{ route('saveTeams') }}" method="POST" class="flex flex-col gap-6">
                    @csrf
                    <div class="text-4xl font-bold">{{ __('Season') }} {{ $selectedSeason->name }}</div>
                    <div class="grid max-sm:grid-cols-1 grid-cols-3 gap-4 w-full mx-auto">

                        @foreach ($teamsWithPlayers as $teamName => $players)
                            <div class="border-2 p-2 border-indigo-400 w-fit rounded-lg border-grey-300">

                                <h1 class="text-center font-extrabold text-xl">{{ $teamName }}</h1>
                                
                                <hr class="border-2 border-dashed my-4 border-indigo-500">
                                
                                <div class="flex flex-col">
                                    <label for="league{{ $teamName}}">{{ __('Pick up a league') }}</label>
                                    <div class="flex flex-col gap-2">
                                        <select name="teams[{{ $teamName }}][level_id]" id="league{{ $teamName}}" class="text-xs w-full py-2 rounded-md border-indigo-500" required>
                                            <option selected disabled>{{ __('Level')}}</option>
                                            @foreach ($leagueLevel as $level)
                                                <option value="{{ $level->name }}">{{ $level->value}}</option>
                                            @endforeach
                                        </select>
                                        <select name="teams[{{ $teamName }}][category_id]" id="league{{ $teamName}}" class="text-xs w-full py-2 rounded-md border-indigo-500" required>
                                            <option selected disabled>{{ __('Category')}}</option>
                                            @foreach ($leagueCategory as $category)
                                                <option value="{{ $category->name}}">{{ $category->value }}</option>
                                            @endforeach
                                        </select>
                                        <input type="text" name="teams[{{ $teamName }}][division]" id="league{{ $teamName}}" class="text-xs w-full py-2 rounded-md border-indigo-500" placeholder="{{ __('5E')}}" required>
                                    </div>
                                </div>

                                <hr class="border-2 border-dashed my-4 border-indigo-500">
                                
                                @foreach ($players as $player)
                                    <div class="grid grid-flow-col gap-2 text-sm mt-2 hover:bg-indigo-300 rounded p-1">
                                        <span class="text-left w-40">{{ $player->force_list }} | {{ $player->last_name }}
                                            {{ $player->first_name }}</span>
                                        <select name="teams[{{ $teamName }}][players_id][]" id=""
                                            class="rounded border-none text-xs hover:bg-indigo-100">
                                            @foreach ($teamsWithPlayers as $team => $value)
                                                <option value="{{ $player->id }}" @selected($team == $teamName)>{{ $team }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <input type="radio" name="teams[{{ $teamName }}][captain_id]" id="" value="{{ $player->id }}">
                                    </div>
                                @endforeach

                            </div>
                        @endforeach
                        <input type="hidden" name="season_id" value="{{ $selectedSeason->id }}">
                    </div>
                    <div>
                        <x-primary-button>{{ __('Save teams compositions') }}</x-primary-button>
                    </div>
                </form>
            </x-admin-block>
        </div>
    @endisset
</x-app-layout>
