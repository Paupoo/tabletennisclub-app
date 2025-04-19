<x-app-layout>
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
                        <div class="bg-gray-50 rounded-lg p-5 mb-8">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="flex items-center bg-red-500">
                                    <div class="rounded-full bg-blue-100 p-3 mr-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                    </div>
                                    <div class="text-gray-500">
                                        <p class="text-sm">Joueurs inscrits</p>
                                        <p class="font-bold text-lg ">{{ $tournament->users->count() }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center text-gray-500 bg-green-500">
                                    <div class="rounded-full bg-green-100 p-3 mr-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Maximum joueurs</p>
                                        <form class="flex gap-4"
                                            action="{{ route('tournamentSetMaxPlayers', $tournament) }}" method="GET">
                                            <input
                                                class="block px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-500"
                                                type="number" name="max_users" min="10" step="1"
                                                value="{{ $tournament->max_users }}">
                                            </input>
                                            <button type="submit"
                                                class="w-full md:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition duration-200">
                                                Confirmer
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <div class="flex items-center text-gray-500 bg-orange-500">
                                    <div class="rounded-full bg-purple-100 p-3 mr-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Date du tournoi</p>
                                        <form class="flex gap-4"
                                            action="{{ route('tournamentSetStartTime', $tournament) }}" method="GET">
                                            <input
                                                class="block w-fit px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-500"
                                                type="datetime-local" name="start_date"
                                                value="{{ $tournament->start_date->format('Y-m-d\TH:i') }}"
                                                min="{{ now()->format('Y-m-d\TH:i') }}">
                                            </input>
                                            <button type="submit"
                                                class="w-full md:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition duration-200">
                                                Confirmer
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <!-- Statut du tournoi -->
                                <div class="flex items-center text-gray-500 bg-purple-500">
                                    <div
                                        class="rounded-full 
            @if ($tournament->status == 'draft') bg-gray-100
            @elseif($tournament->status == 'open') bg-blue-100
            @elseif($tournament->status == 'pending') bg-purple-100
            @elseif($tournament->status == 'closed') bg-green-100 @endif p-3 mr-3">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            class="h-6 w-6 
                @if ($tournament->status == 'draft') text-gray-600
                @elseif($tournament->status == 'open') text-blue-600
                @elseif($tournament->status == 'pending') text-purple-600
                @elseif($tournament->status == 'closed') text-green-600 @endif"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-500">Statut du tournoi</p>
                                        <form" class="flex flex-col gap-2">

                                            <div x-show="open" class="flex gap-2">
                                                <form action="{{ route('tournamentSetStatus', $tournament) }}"
                                                    method="POST" class="flex gap-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <select name="status"
                                                        class="block px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-500">
                                                        <option value="draft"
                                                            {{ $tournament->status == 'draft' ? 'selected' : '' }}>Non
                                                            publié</option>
                                                        <option value="open"
                                                            {{ $tournament->status == 'open' ? 'selected' : '' }}>
                                                            Publié</option>
                                                        <option value="pending"
                                                            {{ $tournament->status == 'pending' ? 'selected' : '' }}>En
                                                            cours</option>
                                                        <option value="closed"
                                                            {{ $tournament->status == 'closed' ? 'selected' : '' }}>
                                                            Terminé</option>
                                                    </select>
                                                    <button type="submit"
                                                        class="w-full md:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition duration-200">
                                                        Confirmer
                                                    </button>
                                                </form>
                                            </div>
                                            </form>
                                    </div>
                                </div>
                            </div>
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

                        <!-- Affichage des pools existantes -->
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
