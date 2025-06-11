<div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
    <!-- Affichage des pools existantes -->
    @if ($tournament->pools->count() > 0)
        <div class="mt-4">
            <h2 class="text-xl font-bold mb-6 text-gray-800">{{ __('Pools list') }}
            </h2>
            <!-- Bouton pour générer les matches -->
            <div class="my-6">
                <h3 class="text-lg font-medium mb-2">Génération des matches
                </h3>
                <form method="POST" action="{{ route('generatePoolMatches', $tournament) }}">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Générer tous les matches de poules
                    </button>
                </form>
                <p class="text-sm text-gray-600 mt-2">
                    Cette action va générer tous les matches pour toutes les
                    poules selon
                    l'algorithme Round
                    Robin.
                </p>
            </div>
            <form action="{{ route('tournament.updatePoolPlayers', $tournament->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($tournament->pools as $pool)
                        <div x-data class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                            <div class="bg-gray-50 px-4 py-3 border-b">
                                <h3 class="text-lg font-medium text-gray-800">
                                    {{ $pool->name }}
                                </h3>
                            </div>
                            <div class="mt-4">
                                <a href="{{ route('showPoolMatches', $pool) }}"
                                    class="m-4 text-blue-600 hover:underline">
                                    Voir les matches de la poule
                                    {{ $pool->name }}
                                </a>
                            </div>
                            <ul x-sort x-sort:group="pools" class="divide-y divide-gray-200">
                                @forelse ($pool->users as $user)
                                    <li x-sort:item="{{ $loop->iteration }}" class="px-4 py-3">
                                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                                            <div class="flex items-center mb-2 sm:mb-0">
                                                <span x-sort:handle
                                                    class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-800 font-medium text-sm mr-3">
                                                    {{ $loop->iteration }}
                                                </span>
                                                <div>
                                                    <p class="font-medium text-gray-500">
                                                        {{ $user->first_name }}
                                                        {{ $user->last_name }}
                                                    </p>
                                                    <p class="text-sm text-gray-500">
                                                        Rank:
                                                        {{ $user->ranking }}
                                                    </p>
                                                </div>
                                            </div>
                                            <select name="player_moves[{{ $user->id }}]"
                                                class="mt-2 sm:mt-0 block w-full sm:w-auto pl-3 pr-10 py-2 text-base text-gray-500 border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                <option value="">Déplacer
                                                    vers...</option>
                                                @foreach ($tournament->pools as $targetPool)
                                                    @if ($targetPool->id != $pool->id)
                                                        <option value="{{ $targetPool->id }}">
                                                            {{ $targetPool->name }}
                                                        </option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </div>
                                    </li>
                                @empty
                                    <li class="px-4 py-3 text-gray-500 italic">
                                        Aucun joueur dans
                                        cette pool</li>
                                @endforelse
                            </ul>
                        </div>
                    @endforeach
                </div>
                <div class="mt-8 flex justify-end">
                    <button type="submit"
                        class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-md shadow-sm transition duration-200">
                        Enregistrer les modifications
                    </button>
                </div>
        </div>
    @else
        {{ __('No pool created yet, please configure the tournament.') }}
    @endif
</div>
