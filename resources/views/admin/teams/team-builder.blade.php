<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Create teams') }}
        </h2>
    </x-slot>

    <!-- Navigation actions -->
    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start space-y-4 sm:space-y-0">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ __('Team Builder') }}</h1>
                        <p class="mt-1 text-sm text-gray-600">{{ __('Generate balanced teams automatically for the season') }}</p>
                    </div>
                    
                    <div class="flex flex-wrap gap-2">
                        <form action="{{ route('dashboard') }}" method="GET">
                            <x-primary-button type="submit" class="!bg-gray-100 !text-gray-700 hover:!bg-gray-200">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                                </svg>
                                {{ __('Dashboard') }}
                            </x-primary-button>
                        </form>
                        <form action="{{ route('teams.index') }}" method="GET">
                            <x-primary-button type="submit">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                {{ __('Manage Teams') }}
                            </x-primary-button>
                        </form>
                        <form action="{{ route('teams.create') }}" method="GET">
                            <x-primary-button type="submit">
                                {{ __('Create new team') }}
                            </x-primary-button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Builder parameters -->
    <div class="mt-6">
        <x-admin-block>
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-1">{{ __('Generation Parameters') }}</h2>
                <p class="text-sm text-gray-600">{{ __('Configure the settings to generate balanced teams') }}</p>
            </div>

            <form class="space-y-6" action="{{ route('teamBuilder.create') }}" method="POST">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <x-input-label for="season">{{ __('Pick up a season') }}</x-input-label>
                        <x-select-input class="mt-2 w-full" id="season" name="season_id">
                            <option value="">{{ __('Choose a season') }}</option>
                            @foreach ($seasons as $season)
                                <option value="{{ $season->id }}" @isset($selectedSeason->id) @selected($selectedSeason->id === $season->id) @endisset>
                                    {{ $season->name }}
                                </option>
                            @endforeach
                        </x-select-input>
                        @error('season_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <x-input-label for="playersPerTeamSelector">{{ __('Define your players per teams') }}</x-input-label>
                        <x-text-input 
                            class="w-full mt-2" 
                            type="number" 
                            name="playersPerTeam" 
                            id="playersPerTeamSelector"
                            min="5" 
                            max="12"
                            step="1" 
                            value="{{ old('playersPerTeam', $playersPerTeam ?? 6) }}" 
                            required 
                        />
                        <p class="mt-1 text-xs text-gray-500">{{ __('Between 5 and 12 players recommended') }}</p>
                        <x-input-error :messages="$errors->get('playersPerTeam')" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <x-primary-button class="inline-flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        {{ __('Build teams') }}
                    </x-primary-button>
                </div>
            </form>
        </x-admin-block>
    </div>

    <!-- Generated teams preview -->
    @isset($teamsWithPlayers)
        <div class="mt-6">
            <x-admin-block>
                <!-- En-tête des équipes générées -->
                <div class="mb-6">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-4 sm:space-y-0">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">{{ __('Generated Teams') }}</h2>
                            <p class="text-sm text-gray-600">
                                {{ count($teamsWithPlayers) }} {{ __('teams created for season') }} {{ $selectedSeason->name }}
                            </p>
                        </div>
                        
                        <button 
                            form="saveTeamsForm"
                            type="submit"
                            class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            {{ __('Save all teams') }}
                        </button>
                    </div>
                </div>

                <form id="saveTeamsForm" action="{{ route('saveTeams') }}" method="POST" class="space-y-6">
                    @csrf
                    <input type="hidden" name="season_id" value="{{ $selectedSeason->id }}">

                    <!-- Grille des équipes -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                        @foreach ($teamsWithPlayers as $teamName => $players)
                            <div class="bg-white rounded-lg shadow-lg overflow-hidden border border-gray-200">
                                <!-- En-tête de l'équipe -->
                                <div class="bg-gradient-to-r from-club-blue to-blue-600 p-4">
                                    <h3 class="text-lg font-bold text-white text-center">{{ $teamName }}</h3>
                                    <p class="text-blue-100 text-sm text-center">{{ count($players) }} {{ __('players') }}</p>
                                </div>

                                <!-- Configuration de l'équipe -->
                                <div class="p-4 border-b border-gray-200 space-y-4">
                                    <div>
                                        <x-input-label for="league{{ $teamName }}" class="text-xs">{{ __('Pick up a league') }}</x-input-label>
                                        <x-select-input name="teams[{{ $teamName }}][level_id]" id="league{{ $teamName }}" class="text-sm mt-1" required>
                                            <option selected disabled>{{ __('Level')}}</option>
                                            @foreach ($leagueLevel as $level)
                                                <option value="{{ $level->name }}">{{ $level->getLabel() }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>

                                    <div>
                                        <x-input-label class="text-xs">{{ __('Category') }}</x-input-label>
                                        <x-select-input name="teams[{{ $teamName }}][category_id]" class="text-sm mt-1" required>
                                            <option selected disabled>{{ __('Category')}}</option>
                                            @foreach ($leagueCategory as $category)
                                                <option value="{{ $category->name }}">{{ $category->getLabel() }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </div>

                                    <div>
                                        <x-input-label class="text-xs">{{ __('Division') }}</x-input-label>
                                        <x-text-input 
                                            type="text" 
                                            name="teams[{{ $teamName }}][division]" 
                                            placeholder="{{ __('5E')}}" 
                                            class="text-sm mt-1"
                                            required 
                                        />
                                    </div>
                                </div>

                                <!-- Liste des joueurs -->
                                <div class="p-4">
                                    <h4 class="text-sm font-medium text-gray-900 mb-3">{{ __('Team composition') }}</h4>
                                    <div class="space-y-2">
                                        @foreach ($players as $player)
                                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center space-x-3">
                                                        <!-- Badge de force -->
                                                        <div class="flex-shrink-0 w-8 h-8 bg-club-blue rounded-full flex items-center justify-center">
                                                            <span class="text-xs font-medium text-white">{{ $player->last_name[0] . $player->first_name[0] }}</span>
                                                        </div>
                                                        <div class="min-w-0 flex-1">
                                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                                {{ $player->last_name }} {{ $player->first_name }}
                                                            </p>
                                                            <p class="text-xs text-gray-500">{{ $player->ranking }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="flex items-center space-x-3">
                                                    <!-- Sélecteur d'équipe -->
                                                    <x-select-input name="teams[{{ $teamName }}][players_id][]" class="text-xs w-20">
                                                        @foreach ($teamsWithPlayers as $team => $value)
                                                            <option value="{{ $player->id }}" @selected($team == $teamName)>{{ $team }}</option>
                                                        @endforeach
                                                    </x-select-input>
                                                    
                                                    <!-- Radio bouton capitaine amélioré -->
                                                    <div class="relative">
                                                        <input 
                                                            type="radio" 
                                                            name="teams[{{ $teamName }}][captain_id]" 
                                                            value="{{ $player->id }}" 
                                                            id="captain_{{ $teamName }}_{{ $player->id }}"
                                                            class="sr-only"
                                                        >
                                                        <label 
                                                            for="captain_{{ $teamName }}_{{ $player->id }}"
                                                            class="relative flex items-center justify-center w-8 h-8 rounded-full border-2 transition-all cursor-pointer border-gray-300 bg-white text-gray-400 hover:border-yellow-300 hover:bg-yellow-50 hover:text-yellow-600"
                                                            title="{{ __('Mark as captain') }}"
                                                        >
                                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                                            </svg>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- <!-- Statistiques de l'équipe -->
                                    <div class="mt-4 pt-4 border-t border-gray-200">
                                        <div class="grid grid-cols-2 gap-4 text-center">
                                            <div class="bg-blue-50 rounded-lg p-2">
                                                <div class="text-lg font-semibold text-blue-600">
                                                    {{ $players->sum('force_list') }}
                                                </div>
                                                <div class="text-xs text-blue-700">{{ __('Total strength') }}</div>
                                            </div>
                                            <div class="bg-green-50 rounded-lg p-2">
                                                <div class="text-lg font-semibold text-green-600">
                                                    {{ round($players->avg('force_list'), 1) }}
                                                </div>
                                                <div class="text-xs text-green-700">{{ __('Average') }}</div>
                                            </div>
                                        </div>
                                    </div> --}}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </form>
            </x-admin-block>
        </div>
    @else
        <!-- État vide -->
        <div class="mt-6">
            <x-admin-block>
                <div class="text-center py-12">
                    <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('No teams generated yet') }}</h3>
                    <p class="text-gray-600 mb-4">{{ __('Configure the parameters above and click "Build teams" to get started.') }}</p>
                </div>
            </x-admin-block>
        </div>
    @endisset

    <style>
        /* Amélioration du style du radio bouton capitaine */
        input[type="radio"]:checked + label {
            @apply border-yellow-400 bg-yellow-50 text-yellow-600;
        }
        
        input[type="radio"]:checked + label::after {
            content: '';
            position: absolute;
            top: -2px;
            right: -2px;
            width: 12px;
            height: 12px;
            background-color: #fbbf24;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        input[type="radio"]:checked + label::before {
            content: '✓';
            position: absolute;
            top: -2px;
            right: -2px;
            width: 12px;
            height: 12px;
            color: white;
            font-size: 8px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
        }

        .bg-club-blue {
            background-color: #2563eb;
        }
        
        .hover\:bg-club-blue-light:hover {
            background-color: #3b82f6;
        }
    </style>

    <script>
        // Améliorer la visibilité du capitaine avec JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Gérer l'état visuel des boutons radio capitaine
            const radioButtons = document.querySelectorAll('input[type="radio"][name*="captain_id"]');
            
            radioButtons.forEach(radio => {
                radio.addEventListener('change', function() {
                    // Réinitialiser tous les autres boutons de la même équipe
                    const teamName = this.name.match(/teams\[(.*?)\]/)[1];
                    const teamRadios = document.querySelectorAll(`input[name="teams[${teamName}][captain_id]"]`);
                    
                    teamRadios.forEach(r => {
                        const label = r.nextElementSibling;
                        if (r === this) {
                            label.classList.add('border-yellow-400', 'bg-yellow-50', 'text-yellow-600');
                            label.classList.remove('border-gray-300', 'bg-white', 'text-gray-400');
                        } else {
                            label.classList.remove('border-yellow-400', 'bg-yellow-50', 'text-yellow-600');
                            label.classList.add('border-gray-300', 'bg-white', 'text-gray-400');
                        }
                    });
                });
            });
        });
    </script>
</x-app-layout>