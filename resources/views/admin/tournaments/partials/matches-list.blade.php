<div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">

    <h2 class="text-xl font-bold text-gray-800 mb-6">Liste des matches</h2>
    <div class="overflow-x-auto">
        @if (count($matches) > 0)
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Joueur 1</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Joueur 2</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            {{ 'Pool' }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Statut</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Résultat</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach ($matches as $match)
                        @if ($match != null)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">{{ $loop->iteration }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-bold">{{ $match->player1->first_name }}
                                        {{ $match->player1->last_name }}</span>
                                    <div class="text-xs text-gray-500 mt-1">{{ $match->player1->ranking }}
                                        {{ $match->player1_handicap_points > 0 ? '(+ ' . $match->player1_handicap_points . ' pts)' : '' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="font-bold">{{ $match->player2->first_name }}
                                        {{ $match->player2->last_name }}</span>
                                    <div class="text-xs text-gray-500 mt-1">{{ $match->player2->ranking }}
                                        {{ $match->player2_handicap_points > 0 ? '(+ ' . $match->player2_handicap_points . ' pts)' : '' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $match->pool ? $match->pool->name : $match->round }}</td>
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

                                    @if ($match->isInProgress())
                                        <a href="{{ route('editMatch', $match) }}"
                                            class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            {{ $match->isCompleted() ? 'Modifier' : 'Saisir le score' }}
                                        </a>
                                    @elseif ($match->isCompleted())
                                        <form action="{{ route('resetMatch', $match) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900"
                                                onclick="return confirm('Êtes-vous sûr de vouloir réinitialiser ce match ?')">
                                                Réinitialiser
                                            </button>
                                        </form>
                                    @elseif($tables->count() == 0)
                                        <a href="{{ route('tablesOverview', $tournament) }}">
                                            <p class="text-gray-600">
                                                {{ __('All the tables are currently used') }}
                                            </p>
                                        </a>
                                    @else
                                        <div
                                            x-data="{ selected: '' }"
                                            class="flex items-center p-2 bg-gray-50 rounded-lg shadow-sm hover:bg-gray-100 transition">
                                            <form action="{{ route('startMatch', $match) }}" method="POST"
                                                class="flex w-full items-center space-x-2">
                                                @csrf
                                                <select x-model="selected" name="table_id" id="table_id"
                                                    class="pl-3 pr-10 py-1 text-sm border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md shadow-sm">
                                                    <option selected value="" disabled>
                                                        {{ __('Select a table') }}</option>
                                                    @foreach ($tables as $table)
                                                        @if ($table->pivot->is_table_free)
                                                            <option value="{{ $table->id }}">
                                                                {{ $table->name }}
                                                                ==>
                                                                {{ $table->room->name }}</option>
                                                        @endif
                                                    @endforeach
                                                </select>
                                                <x-ui.action-button
                                                    x-show="selected !== ''" class="shrink-0" x-cloak
                                                    variant="primary"
                                                    icon="rocket-launch"
                                                    type="submit"
                                                    tooltip="Démarrer le match"
                                                />
                                            </form>
                                        </div>
                                    @endif
                                    <x-input-error class="mt-2" :messages="$errors->get('table')" />
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="text-left text-gray-500 italic">{{ __('No match generated yet.') }}</p>
            <div class="my-6">
                <form method="POST" action="{{ route('generatePoolMatches', $tournament) }}">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Générer tous les matches de poules
                    </button>
                </form>
                <p class="text-sm text-gray-600 mt-2">
                    Cette action va générer tous les matches pour toutes les poules selon
                    l'algorithme Round
                    Robin.
                </p>
            </div>
        @endif
    </div>
</div>
