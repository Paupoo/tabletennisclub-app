<x-app-layout>
    <x-slot name="header">
        {{-- Header --}}
        <div class="flex flex-row gap-2 items-center">
            <x-admin.title :title="$tournament->name" />
            <x-tournament.status-badge :status="$tournament->status" />
            <x-admin.action-menu :tournament="$tournament" />
        </div>
    </x-slot>
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-center">
            <div class="w-full max-w-8xl">
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="p-6">
                        <a href="{{ route('tournamentShow', $tournament) }}"
                            class="inline-block mb-6 px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md transition duration-200">
                            &larr; Retour
                        </a>
                        <div class="border-b pb-4 mb-6">
                            <h3 class="text-2xl font-bold text-gray-800">Gérer les Pools - {{ $tournament->name }}</h3>
                        </div>

                        <!-- Informations sur le tournoi -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
                            <h4 class="text-lg font-medium mb-4 text-gray-800">{{ __('Parameters') }}</h4>
                            <p class="mt-2 mb-4 text-sm text-gray-500">
                                Veuillez définir ici les différents paramètres de votre tournoi.
                            </p>
                        <x-forms.tournament :rooms="$rooms" :tournament="$tournament"/>
                        </div>

                        <!-- Formulaire de génération des pools -->
                        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
                            <h4 class="text-lg font-medium mb-4 text-gray-800">Générer les pools</h4>
                            <form action="{{ route('tournaments.generate-pools', $tournament) }}" method="POST">
                                @csrf
                                <div class="mb-4 w-full max-w-2xl">
                                    <p class="mt-2 mb-4 text-sm text-gray-500">
                                        Les joueurs seront distribués selon leur classement. Vous pouvez définir un
                                        nombre de poules ou un nombre minimum de matches joués en cas d'élimination à la
                                        fin de la phase de poules.
                                    </p>
                                    <label for="number_of_pools"
                                        class="block text-sm font-medium text-gray-700 mb-2">Nombre de pools à créer
                                        :</label>
                                    <div class="relative">
                                        <select name="number_of_pools" id="number_of_pools"
                                            class="block w-full appearance-none px-3 py-2 pr-8 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-500">
                                            @for ($i = 2; $i <= 8; $i++)
                                                <option value="{{ $i }}">{{ $i }} pools
                                                </option>
                                            @endfor
                                        </select>
                                        <input type="hidden" name="minMatches" value=0>
                                        <div
                                            class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                            <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20">
                                                <path
                                                    d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit"
                                    class="w-full mb-4 md:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition duration-200">Générer
                                    les pools</button>
                            </form>
                            <hr class="text-gray-500 opacity-50 my-4">

                            <form action="{{ route('tournaments.generate-pools', $tournament) }}" method="POST">
                                @csrf
                                <div class="mb-4 w-full max-w-2xl">
                                    <label for="number_of_pools"
                                        class="block text-sm font-medium text-gray-700 mb-2">Nombre
                                        minimum de matches joués&nbsp;:</label>
                                    <div class="relative">
                                        <select name="number_of_pools" id="number_of_pools"
                                            class="block w-full appearance-none px-3 py-2 pr-8 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-500">
                                            @for ($i = 2; $i <= 8; $i++)
                                                <option value="{{ $i }}">{{ $i }} matches
                                                </option>
                                            @endfor
                                        </select>
                                        <input type="hidden" name="minMatches" value=1>
                                        <div
                                            class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                                            <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20">
                                                <path
                                                    d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit"
                                    class="w-full md:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition duration-200">Générer
                                    les pools</button>
                            </form>


                        </div>

                        <!-- Messages de succès -->
                        @if (session()->has('success'))
                            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-8"
                                role="alert">
                                <p>{{ session()->get('success') }}</p>
                            </div>
                        @elseif (session()->has('error'))
                            <div class="bg-red-100 border-l-4 border-Red-500 text-red-700 p-4 mb-8" role="alert">
                                <p>{{ session()->get('error') }}</p>
                            </div>
                        @endif
                        
                        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">                            <!-- Affichage des pools existantes -->
                            @if ($tournament->pools->count() > 0)
                                <div class="mt-8">
                                    <h2 class="text-xl font-bold mb-6 text-gray-800">Pools existantes</h2>
                                    <!-- Bouton pour générer les matches -->
                                    <div class="my-6">
                                        <h3 class="text-lg font-medium mb-2">Génération des matches</h3>
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
                                    <form action="{{ route('tournament.updatePoolPlayers', $tournament->id) }}"
                                        method="POST">
                                        @csrf
                                        @method('PUT')
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                            @foreach ($tournament->pools as $pool)
                                                <div
                                                    class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
                                                    <div class="bg-gray-50 px-4 py-3 border-b">
                                                        <h3 class="text-lg font-medium text-gray-800">{{ $pool->name }}
                                                        </h3>
                                                    </div>
                                                    <div class="mt-4">
                                                        <a href="{{ route('showPoolMatches', $pool) }}"
                                                            class="m-4 text-blue-600 hover:underline">
                                                            Voir les matches de la poule {{ $pool->name }}
                                                        </a>
                                                    </div>
                                                    <ul class="divide-y divide-gray-200">
                                                        @forelse ($pool->users->sortBy('ranking') as $user)
                                                            <li class="px-4 py-3">
                                                                <div
                                                                    class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                                                                    <div class="flex items-center mb-2 sm:mb-0">
                                                                        <span
                                                                            class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-800 font-medium text-sm mr-3">
                                                                            {{ $loop->iteration }}
                                                                        </span>
                                                                        <div>
                                                                            <p class="font-medium text-gray-500">
                                                                                {{ $user->first_name }}
                                                                                {{ $user->last_name }}</p>
                                                                            <p class="text-sm text-gray-500">Rank:
                                                                                {{ $user->ranking }}</p>
                                                                        </div>
                                                                    </div>
                                                                    <select name="player_moves[{{ $user->id }}]"
                                                                        class="mt-2 sm:mt-0 block w-full sm:w-auto pl-3 pr-10 py-2 text-base text-gray-500 border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                                        <option value="">Déplacer vers...</option>
                                                                        @foreach ($tournament->pools as $targetPool)
                                                                            @if ($targetPool->id != $pool->id)
                                                                                <option value="{{ $targetPool->id }}">
                                                                                    {{ $targetPool->name }}</option>
                                                                            @endif
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                            </li>
                                                        @empty
                                                            <li class="px-4 py-3 text-gray-500 italic">Aucun joueur dans
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
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-center">
            <div class="w-full max-w-8xl">
                <div class="bg-white rounded-lg shadow-lg overflow-hidden p-6 text-gray-900">
                    <div class="flex justify-between mb-6">
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                            {{ __('Configuration de la phase finale') }} - {{ $tournament->name }}
                        </h2>
                        <div>
                            <a href="{{ route('tournamentShow', $tournament) }}"
                                class="text-blue-600 hover:underline">
                                &larr; Retour au tournoi
                            </a>
                        </div>
                    </div>

                    <form action="{{ route('configureKnockout', $tournament) }}" method="POST">
                        @csrf
                        <div class="mb-6">
                            <label for="starting_round" class="block text-sm font-medium text-gray-700">Phase de
                                départ</label>
                            <select id="starting_round" name="starting_round"
                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="round_16">16ème de finale (16 joueurs)</option>
                                <option value="round_8">8ème de finale (8 joueurs)</option>
                                <option value="round_4">Quart de finale (4 joueurs)</option>
                            </select>
                            <p class="mt-2 text-sm text-gray-500">
                                Sélectionnez la phase à partir de laquelle vous souhaitez démarrer le tableau final.
                                Les joueurs seront sélectionnés en fonction de leurs résultats dans les poules.
                            </p>
                        </div>

                        <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
                            <button type="submit"
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Configurer la phase finale
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
