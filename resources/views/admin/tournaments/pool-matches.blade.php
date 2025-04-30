<x-app-layout>
    

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session()->has('success'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-8" role="alert">
                    <p>{{ session()->get('success') }}</p>
                </div>
            @elseif(session()->has('error'))
                <div class="bg-red-100 border-l-4 border-Red-500 text-red-700 p-4 mb-8" role="alert">
                    <p>{{ session()->get('error') }}</p>
                </div>
            @endif
            
            <!-- Classement de la poule -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900">
                    
                        <div class="flex justify-between mt-2 my-8">
                            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                                {{ __('Matches de la poule') }} - {{ $pool->name }} ({{ $tournament->name }})
                            </h2>
                            <div>
                                <a href="{{ route('tournamentSetup', $tournament) }}" class="text-blue-600 hover:underline">
                                    &larr; Retour aux poules
                                </a>
                            </div>
                        </div>
                    
                    <h3 class="text-lg font-medium mb-4">{{ __('Classement actuel') }}</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Joueur</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Matches joués</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Victoires</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Sets gagnés</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Points marqués</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($standings as $standing)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $standing['player']->first_name }} {{ $standing['player']->last_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $standing['matches_played'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $standing['matches_won'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $standing['sets_won'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $standing['total_points'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Liste des matches -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium mb-4">{{ __('Liste des matches') }}</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        #</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Joueur 1</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Joueur 2</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Statut</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Résultat</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($matches as $match)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $match->match_order }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $match->player1->first_name }} {{ $match->player1->last_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $match->player2->first_name }} {{ $match->player2->last_name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if ($match->status === 'scheduled')
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    Programmé
                                                </span>
                                            @elseif($match->status === 'in_progress')
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    En cours
                                                </span>
                                            @else
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Terminé
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if ($match->isCompleted())
                                                @php
                                                    $player1Sets = $match->getSetsWon($match->player1_id);
                                                    $player2Sets = $match->getSetsWon($match->player2_id);
                                                @endphp
                                                <span class="font-bold">{{ $player1Sets }} -
                                                    {{ $player2Sets }}</span>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    @foreach ($match->sets as $set)
                                                        {{ $set->player1_score }}-{{ $set->player2_score }}
                                                        @if (!$loop->last)
                                                            |
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">

                                            @if($match->isInProgress())
                                            <a href="{{ route('editMatch', $match) }}"
                                                class="text-indigo-600 hover:text-indigo-900 mr-3">
                                                {{ $match->isCompleted() ? 'Modifier' : 'Saisir le score' }}
                                            </a>
                                            @elseif ($match->isCompleted())
                                                <form action="{{ route('resetMatch', $match) }}" method="POST"
                                                    class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900"
                                                        onclick="return confirm('Êtes-vous sûr de vouloir réinitialiser ce match ?')">
                                                        Réinitialiser
                                                    </button>
                                                </form>
                                            @else
                                            <div class="flex items-center p-2 bg-gray-50 rounded-lg shadow-sm hover:bg-gray-100 transition">
                                                <form action="{{ route('startMatch', $match) }}" method="GET" class="flex w-full items-center space-x-2">
                                                  <div class="flex items-center space-x-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor">
                                                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                                                    </svg>
                                                    <label for="tableNumber" class="text-sm font-medium text-gray-700">Table</label>
                                                  </div>
                                                  
                                                  <select name="tableNumber" id="tableNumber" class="pl-3 pr-10 py-1 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md shadow-sm">
                                                    @foreach($tables as $table)
                                                      <option value="{{ $table }}">{{ $table->name }}</option>
                                                    @endforeach
                                                  </select>
                                                  
                                                  <button type="submit" class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                                                    <span>Démarrer</span>
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="ml-1 h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                                      <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                    </svg>
                                                  </button>
                                                </form>
                                              </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
