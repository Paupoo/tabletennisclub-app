<x-app-layout>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="flex justify-between m-4">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        {{ __('Saisir les résultats du match') }}
                    </h2>
                    <div>
                        @if(isset($pool))
                        <a href="{{ route('showPoolMatches', $pool) }}" class="text-blue-600 hover:underline">
                            Retour aux matches de la poule
                        </a>
                        @else
                        <a href="{{ route('knockoutBracket', $tournament) }}" class="text-blue-600 hover:underline">
                            Retour aux matches de la phase finale
                        </a>
                        @endif
                    </div>
                </div>
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium mb-6">
                        Match #{{ $match->match_order }}: {{ $match->player1->first_name }} {{ $match->player1->last_name }} vs {{ $match->player2->first_name }} {{ $match->player2->last_name }}
                    </h3>

                    <form method="POST" action="{{ route('updateMatch', $match) }}">
                        @csrf
                        @method('PUT')

                        <div class="space-y-6">
                            <!-- En-tête du tableau des sets -->
                            <div class="grid grid-cols-7 gap-4 mb-2">
                                <div class="col-span-1">Set</div>
                                <div class="col-span-3 text-center">{{ $match->player1->name }}</div>
                                <div class="col-span-3 text-center">{{ $match->player2->name }}</div>
                            </div>

                            <!-- Sets (default 5 sets maximum) -->
                            @for ($i = 0; $i < 5; $i++)
                                @php
                                    $set = $match->sets->where('set_number', $i+1)->first();
                                    $player1Score = $set ? $set->player1_score : '';
                                    $player2Score = $set ? $set->player2_score : '';
                                @endphp
                                <div class="grid grid-cols-7 gap-4 items-center">
                                    <div class="col-span-1">Set {{ $i+1 }}</div>
                                    <div class="col-span-3">
                                        <input type="number" min="0" name="sets[{{ $i }}][player1_score]" value="{{ old('sets.'.$i.'.player1_score', $player1Score) }}" 
                                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" >
                                        @error('sets.' . $i . '.player1_score')
                                            <div class="text-red-700 p-1 mb-1" role="alert">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-span-3">
                                        <input type="number" min="0" name="sets[{{ $i }}][player2_score]" value="{{ old('sets.'.$i.'.player2_score', $player2Score) }}" 
                                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" >
                                              @error('sets.' . $i . '.player2_score')
                                              <div class="text-red-700 p-1 mb-1" role="alert">{{ $message }}</div>
                                          @enderror
                                    </div>
                                </div>
                            @endfor

                            <div class="flex items-center justify-end mt-4">
                                <x-primary-button class="ml-4">
                                    {{ __('Enregistrer les résultats') }}
                                </x-primary-button>
                            </div>
                        </div>
                    </form>

                    <div class="mt-6">
                        
                        @if(session()->has('success'))
                            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-8"
                                role="alert">
                                <p>{{ session()->get('success') }}</p>
                            </div>
                        @elseif(session()->has('error'))
                            <div class="bg-red-100 border-l-4 border-Red-500 text-red-700 p-4 mb-8" role="alert">
                                <p>{{ session()->get('error') }}</p>
                            </div>
                        @endif
                        <h4 class="text-md font-medium mb-2">Règles de saisie des scores :</h4>
                        <ul class="list-disc list-inside text-sm text-gray-600">
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