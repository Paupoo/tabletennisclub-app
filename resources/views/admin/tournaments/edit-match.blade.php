<x-app-layout :breadcrumbs="$breadcrumbs">
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-lg rounded-lg">
                <!-- Header avec titre et bouton retour -->
                <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
                    <h2 class="font-bold text-xl text-gray-800">
                        {{ __('Saisir les résultats du match') }}
                    </h2>
                    <div>
                        @if(isset($pool))
                        <a href="{{ route('showPoolMatches', $pool) }}" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-200 transition ease-in-out duration-150">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Retour aux matches de la poule
                        </a>
                        @else
                        <a href="{{ route('knockoutBracket', $tournament) }}" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-200 transition ease-in-out duration-150">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Retour aux matches de la phase finale
                        </a>
                        @endif
                    </div>
                </div>
                
                <div class="p-6 text-gray-900">
                    <!-- En-tête du match -->
                    <div class="bg-amber-100 p-4 rounded-lg shadow-sm mb-6">
                        <div class="text-sm text-gray-600 mb-2">Match #{{ $match->match_order }}</div>
                        <div class="flex flex-col md:flex-row items-start justify-between">
                            <div class="flex-1 text-center md:text-right mb-4 md:mb-0">
                                <div class="text-xl font-bold text-gray-800">
                                    {{ $match->player1->first_name }} {{ $match->player1->last_name }}
                                </div>
                                <div class="text-sm text-gray-600">
                                    Classement: {{ $match->player1->ranking }}
                                    @if($match->player1_handicap_points > 0)
                                    <div class="flex flex-row justify-end items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="12" y1="3" x2="12" y2="21" />
                                            <line x1="4" y1="7" x2="20" y2="7" />
                                            <line x1="9" y1="21" x2="15" y2="21" />
                                            <circle cx="7" cy="14" r="3" />
                                            <circle cx="17" cy="14" r="3" />
                                            <line x1="7" y1="7" x2="7" y2="11" />
                                            <line x1="17" y1="7" x2="17" y2="11" />
                                        </svg>
                                        <span class="text-green-600 font-medium ml-1">+{{ $match->player1_handicap_points }} pts</span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="flex-shrink-0 mx-4 my-2">
                                <div class="bg-gray-200 text-gray-700 rounded-full w-12 h-12 flex items-center justify-center font-bold text-lg">VS</div>
                            </div>
                            
                            <div class="flex-1 text-center md:text-left">
                                <div class="text-xl font-bold text-gray-800">
                                    {{ $match->player2->first_name }} {{ $match->player2->last_name }}
                                </div>
                                <div class="text-sm text-gray-600">
                                    Classement: {{ $match->player2->ranking }}
                                    @if($match->player2_handicap_points > 0)
                                    <div class="flex flex-row justify-start items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="12" y1="3" x2="12" y2="21" />
                                            <line x1="4" y1="7" x2="20" y2="7" />
                                            <line x1="9" y1="21" x2="15" y2="21" />
                                            <circle cx="7" cy="14" r="3" />
                                            <circle cx="17" cy="14" r="3" />
                                            <line x1="7" y1="7" x2="7" y2="11" />
                                            <line x1="17" y1="7" x2="17" y2="11" />
                                        </svg>
                                        <span class="text-green-600 font-medium ml-1">+{{ $match->player2_handicap_points }} pts</span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulaire de saisie des scores -->
                    <form method="POST" action="{{ route('updateMatch', $match) }}">
                        @csrf
                        @method('PUT')

                        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                            <!-- En-tête du tableau des sets -->
                            <div class="grid grid-cols-7 gap-4 bg-gray-50 p-4 border-b">
                                <div class="col-span-1 font-medium">Set</div>
                                <div class="col-span-3 text-center font-medium">{{ $match->player1->first_name }}</div>
                                <div class="col-span-3 text-center font-medium">{{ $match->player2->first_name }}</div>
                            </div>

                            <!-- Sets (default 5 sets maximum) -->
                            @for ($i = 0; $i < 5; $i++)
                                @php
                                    $set = $match->sets->where('set_number', $i+1)->first();
                                    $player1Score = $set ? $set->player1_score : '';
                                    $player2Score = $set ? $set->player2_score : '';
                                @endphp
                                <div class="grid grid-cols-7 gap-4 items-center p-4 {{ $i % 2 == 0 ? 'bg-white' : 'bg-gray-50' }} border-b">
                                    <div class="col-span-1 font-medium">Set {{ $i+1 }}</div>
                                    <div class="col-span-3">
                                        <input type="number" min="0" name="sets[{{ $i }}][player1_score]" value="{{ old('sets.'.$i.'.player1_score', $player1Score) }}" 
                                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                              placeholder="{{ $match->player1_handicap_points > 0 ? '+ ' . $match->player1_handicap_points . ' handicap points': '' }}" >
                                        @error('sets.' . $i . '.player1_score')
                                            <div class="text-red-700 text-sm mt-1" role="alert">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-span-3">
                                        <input type="number" min="0" name="sets[{{ $i }}][player2_score]" value="{{ old('sets.'.$i.'.player2_score', $player2Score) }}" 
                                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                              placeholder="{{ $match->player2_handicap_points > 0 ? '+ ' . $match->player2_handicap_points . ' handicap points' : '' }}" >
                                              @error('sets.' . $i . '.player2_score')
                                              <div class="text-red-700 text-sm mt-1" role="alert">{{ $message }}</div>
                                          @enderror
                                    </div>
                                </div>
                            @endfor
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <x-primary-button>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ __('Enregistrer les résultats') }}
                            </x-primary-button>
                        </div>
                    </form>

                    <!-- Règles de saisie -->
                    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="text-md font-medium text-blue-800 mb-2 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Règles de saisie des scores
                        </h4>
                        <ul class="list-disc list-inside text-sm text-blue-700 space-y-1 pl-1">
                            <li>Le gagnant doit avoir au moins 11 points</li>
                            <li>Le gagnant doit avoir 2 points d'écart avec le perdant</li>
                            <li>Un match se joue au meilleur des 5 sets (premier joueur à gagner 3 sets)</li>
                            <li>Vous devez saisir les scores de tous les sets joués</li>
                            <li>Les sets non joués peuvent rester vides</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>