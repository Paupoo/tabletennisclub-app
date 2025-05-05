<x-app-layout>
    <div class="container mx-auto px-4 py-8">
        <!-- En-tête du tournoi -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="p-6">
                <div class="flex flex-col md:flex-row md:justify-between md:items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-800 mb-4 md:mb-0">{{ $tournament->name }}</h1>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="{{ route('tournamentsIndex') }}"
                            class="inline-block px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-md transition duration-200 text-center">
                            &larr; Retour
                        </a>
                        <a href="{{ route('startTournament', $tournament) }}"
                            class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition duration-200 text-center">
                            Démarrer le tournoi
                        </a>
                        <a href="{{ route('closeTournament', $tournament) }}"
                            class="inline-block px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md transition duration-200 text-center">
                            Clôturer le tournoi
                        </a>
                        <a href="{{ route('knockoutBracket', $tournament) }}"
                            class="inline-block px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md transition duration-200 text-center">
                            Phase finale (show)
                        </a>
                    </div>
                </div>

                <!-- Informations sur le tournoi -->
                <div class="bg-gray-50 rounded-lg p-5">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="flex items-center">
                            <div class="rounded-full bg-purple-100 p-3 mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="text-gray-500">
                                <p class="text-sm text-gray-500">Date de début</p>
                                <p class="font-bold text-lg">{{ $tournament->start_date->format('d/m/Y \a\t H:i') }}</p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="rounded-full bg-blue-100 p-3 mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <div class="text-gray-500">
                                <p class="text-sm">Joueurs inscrits</p>
                                <p class="font-bold text-lg">{{ $tournament->total_users }}</p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="rounded-full bg-green-100 p-3 mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                </svg>
                            </div>
                            <div class="text-gray-500">
                                <p class="text-sm">Maximum joueurs</p>
                                <p class="font-bold text-lg">{{ $tournament->max_users }}</p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="rounded-full bg-green-100 p-3 mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                </svg>
                            </div>
                            <div class="text-gray-500">
                                <p class="text-sm">{{ __('Rooms') }}</p>
                                @foreach ($tournament->rooms as $room)
                                    <p class="font-bold text-lg">{{ $room->name }}</p>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="rounded-full bg-green-100 p-3 mr-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                </svg>
                            </div>
                            <div class="text-gray-500">
                                <p class="text-sm">{{ __('Total tables') }}</p>
                                <p class="font-bold text-lg">{{ $tournament->tables->count() }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages de succès -->
        @if (session()->has('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-8" role="alert">
                <p>{{ session()->get('success') }}</p>
            </div>
        @elseif (session()->has('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-8" role="alert">
                <p>{{ session()->get('error') }}</p>
            </div>
        @endif

        <!-- Système de navigation par onglets -->
        <div class="mb-8 bg-white rounded-lg shadow-lg overflow-hidden" x-data="{ activeTab: 'registered' }">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <button id="tab-registered" @click="activeTab = 'registered'"
                        :class="{ 'border-blue-500 text-blue-600 font-semibold': activeTab === 'registered', 'text-gray-500 border-transparent': activeTab !== 'registered' }"
                        class="tab-button px-6 py-4 border-b-2 font-medium leading-5 transition duration-150 ease-in-out">
                        Joueurs inscrits
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800">
                            {{ $tournament->users()->count() }}
                        </span>
                    </button>
                    <button id="tab-unregistered" @click="activeTab = 'unregistered'"
                        :class="{ 'border-blue-500 text-blue-600 font-semibold': activeTab === 'unregistered', 'text-gray-500 border-transparent': activeTab !== 'unregistered' }"
                        class="tab-button px-6 py-4 border-b-2 font-medium leading-5 hover:text-gray-700 hover:border-gray-300 transition duration-150 ease-in-out">
                        Joueurs non-inscrits
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">
                            {{ $unregisteredUsers->count() }}
                        </span>
                    </button>
                    <button id="tab-pools" @click="activeTab = 'pools'"
                        :class="{ 'border-blue-500 text-blue-600 font-semibold': activeTab === 'pools', 'text-gray-500 border-transparent': activeTab !== 'pools' }"
                        class="tab-button px-6 py-4 border-b-2 font-medium leading-5 hover:text-gray-700 hover:border-gray-300 transition duration-150 ease-in-out">
                        Liste des poules
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">
                            {{ $tournament->pools->count() }}
                        </span>
                    </button>
                    <button id="tab-matches" @click="activeTab = 'matches'"
                        :class="{ 'border-blue-500 text-blue-600 font-semibold': activeTab === 'matches', 'text-gray-500 border-transparent': activeTab !== 'matches' }"
                        class="tab-button px-6 py-4 border-b-2 font-medium leading-5 hover:text-gray-700 hover:border-gray-300 transition duration-150 ease-in-out">
                        Liste des matches
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">
                            {{ count($matches) }}
                        </span>
                    </button>
                    <button id="tab-tables" @click="activeTab = 'tables'"
                        :class="{ 'border-blue-500 text-blue-600 font-semibold': activeTab === 'tables', 'text-gray-500 border-transparent': activeTab !== 'tables' }"
                        class="tab-button px-6 py-4 border-b-2 font-medium leading-5 hover:text-gray-700 hover:border-gray-300 transition duration-150 ease-in-out">
                        Liste des tables
                        <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-800">
                            {{ count($tournament->tables()->get()) }}
                        </span>
                    </button>
                    <button id="tab-config" @click="activeTab = 'config'"
                        :class="{ 'border-blue-500 text-blue-600 font-semibold': activeTab === 'config', 'text-gray-500 border-transparent': activeTab !== 'config' }"
                        class="tab-button px-6 py-4 border-b-2 font-medium leading-5 transition duration-150 ease-in-out">
                        Configuration
                        {{-- <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800">
                {{ $tournament->users()->count() }}
            </span> --}}
                    </button>
                </nav>
            </div>

            <x-admin-block2>
                <!-- Contenu de l'onglet: Joueurs inscrits -->
                <div id="content-config" x-show="activeTab === 'config'" class="tab-content">
                    <div class="container mx-auto px-4 py-8">
                        <div class="flex justify-center">
                            <div class="w-full max-w-8xl">
                                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                                    <div class="p-6">

                                        <div class="border-b pb-4 mb-6">
                                            <h3 class="text-2xl font-bold text-gray-800">{{ __('Configuration') }}
                                            </h3>
                                        </div>

                                        <!-- Informations sur le tournoi -->
                                        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
                                            <h4 class="text-lg font-medium mb-4 text-gray-800">{{ __('Parameters') }}
                                            </h4>
                                            <p class="mt-2 mb-4 text-sm text-gray-500">
                                                Veuillez définir ici les différents paramètres de votre tournoi.
                                            </p>
                                            <x-forms.tournament :rooms="$rooms" :tournament="$tournament" />
                                        </div>

                                        @include('admin.tournaments.partials.pool-generator')

                                        <!-- Messages de succès -->
                                        @if (session()->has('success'))
                                            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-8"
                                                role="alert">
                                                <p>{{ session()->get('success') }}</p>
                                            </div>
                                        @elseif (session()->has('error'))
                                            <div class="bg-red-100 border-l-4 border-Red-500 text-red-700 p-4 mb-8"
                                                role="alert">
                                                <p>{{ session()->get('error') }}</p>
                                            </div>
                                        @endif

                                        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
                                            <!-- Affichage des pools existantes -->
                                            @if ($tournament->pools->count() > 0)
                                                <div class="mt-8">
                                                    <h2 class="text-xl font-bold mb-6 text-gray-800">Pools existantes
                                                    </h2>
                                                    <!-- Bouton pour générer les matches -->
                                                    <div class="my-6">
                                                        <h3 class="text-lg font-medium mb-2">Génération des matches
                                                        </h3>
                                                        <form method="POST"
                                                            action="{{ route('generatePoolMatches', $tournament) }}">
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
                                                    <form
                                                        action="{{ route('tournament.updatePoolPlayers', $tournament->id) }}"
                                                        method="POST">
                                                        @csrf
                                                        @method('PUT')
                                                        <div
                                                            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                                            @foreach ($tournament->pools as $pool)
                                                                <div
                                                                    class="bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm">
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
                                                                    <ul class="divide-y divide-gray-200">
                                                                        @forelse ($pool->users->sortBy('ranking') as $user)
                                                                            <li class="px-4 py-3">
                                                                                <div
                                                                                    class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
                                                                                    <div
                                                                                        class="flex items-center mb-2 sm:mb-0">
                                                                                        <span
                                                                                            class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-800 font-medium text-sm mr-3">
                                                                                            {{ $loop->iteration }}
                                                                                        </span>
                                                                                        <div>
                                                                                            <p
                                                                                                class="font-medium text-gray-500">
                                                                                                {{ $user->first_name }}
                                                                                                {{ $user->last_name }}
                                                                                            </p>
                                                                                            <p
                                                                                                class="text-sm text-gray-500">
                                                                                                Rank:
                                                                                                {{ $user->ranking }}
                                                                                            </p>
                                                                                        </div>
                                                                                    </div>
                                                                                    <select
                                                                                        name="player_moves[{{ $user->id }}]"
                                                                                        class="mt-2 sm:mt-0 block w-full sm:w-auto pl-3 pr-10 py-2 text-base text-gray-500 border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                                                        <option value="">Déplacer
                                                                                            vers...</option>
                                                                                        @foreach ($tournament->pools as $targetPool)
                                                                                            @if ($targetPool->id != $pool->id)
                                                                                                <option
                                                                                                    value="{{ $targetPool->id }}">
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
                                            <label for="starting_round"
                                                class="block text-sm font-medium text-gray-700">Phase de
                                                départ</label>
                                            <select id="starting_round" name="starting_round"
                                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                <option value="round_16">16ème de finale (16 joueurs)</option>
                                                <option value="round_8">8ème de finale (8 joueurs)</option>
                                                <option value="round_4">Quart de finale (4 joueurs)</option>
                                            </select>
                                            <p class="mt-2 text-sm text-gray-500">
                                                Sélectionnez la phase à partir de laquelle vous souhaitez démarrer le
                                                tableau final.
                                                Les joueurs seront sélectionnés en fonction de leurs résultats dans les
                                                poules.
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
                </div>

                <!-- Contenu de l'onglet: Joueurs inscrits -->
                <div id="content-registered" x-show="activeTab === 'registered'" class="tab-content">
                    <h2 class="text-xl font-bold text-gray-800 mb-6">Joueurs inscrits</h2>

                    <div class="overflow-x-auto mb-8">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                        #</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                        Joueur</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                        Classement</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                        Date d'inscription</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                        Paiement</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @if (count($tournament->users()->get()) > 0)
                                    @foreach ($tournament->users()->get() as $user)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-800 font-medium text-sm">
                                                    {{ $loop->iteration }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="font-medium text-gray-900">{{ $user->first_name }}
                                                    {{ $user->last_name }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-gray-900">{{ $user->ranking }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">31/03/2025</td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-{{ $user->pivot->has_paid ? 'green' : 'red' }}-100 text-{{ $user->pivot->has_paid ? 'green' : 'red' }}-800">
                                                    @if ($user->pivot->has_paid)
                                                        Payé
                                                    @else
                                                        Paiement en attente
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div>
                                                    <a
                                                        href="{{ route('tournamentToggleHasPaid', [$tournament, $user]) }}">
                                                        @if (!$user->pivot->has_paid)
                                                            <!-- Marquer comme payé -->
                                                            <button
                                                                class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                                                title="Marquer comme payé">
                                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                                    class="h-5 w-5" viewBox="0 0 20 20"
                                                                    fill="currentColor">
                                                                    <path fill-rule="evenodd"
                                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                                        clip-rule="evenodd" />
                                                                </svg>
                                                            </button>
                                                        @else
                                                            <!-- Marquer comme impayé -->
                                                            <button
                                                                class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-yellow-500 hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500"
                                                                title="Marquer comme impayé">
                                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                                    class="h-5 w-5" viewBox="0 0 20 20"
                                                                    fill="currentColor">
                                                                    <path fill-rule="evenodd"
                                                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                                        clip-rule="evenodd" />
                                                                </svg>
                                                            </button>
                                                        @endif
                                                    </a>

                                                    <!-- Désinscrire -->
                                                    <a
                                                        href="{{ route('tournamentUnregister', [$tournament, $user]) }}">
                                                        <button
                                                            class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                                            title="Désinscrire">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5"
                                                                viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd"
                                                                    d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                                                    clip-rule="evenodd" />
                                                            </svg>
                                                        </button>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="6" class="px-4 py-4 text-center text-gray-500 italic">Aucun
                                            joueur
                                            inscrit pour le moment.</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Contenu de l'onglet: Joueurs non-inscrits -->
                <div id="content-unregistered" x-show="activeTab === 'unregistered'" class="tab-content" x-cloak>

                    <h2 class="text-xl font-bold text-gray-800 mb-6">Joueurs non-inscrits</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                        Joueur</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                        Classement</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @if (count($unregisteredUsers) > 0)
                                    @foreach ($unregisteredUsers as $user)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="font-medium text-gray-900">{{ $user->first_name }}
                                                    {{ $user->last_name }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <div class="text-gray-900">{{ $user->ranking }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <a href="{{ route('tournamentRegister', [$tournament, $user]) }}"
                                                    class="inline-flex items-center px-3 py-1 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                    Inscrire
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr>
                                        <td colspan="3" class="px-4 py-4 text-center text-gray-500 italic">Aucun
                                            joueur
                                            trouvé.</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Contenu de l'onglet: Liste des poules -->
                <div id="content-pools" x-show="activeTab === 'pools'" class="tab-content" x-cloak>
                    <div class="flex flex-col gap-2 mb-2">
                        <div class="overflow-x-auto">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">Liste des poules</h2>
                        @if ($tournament->pools->count() > 0 && ($tournament->status != 'pending' || $tournament->status != 'closed'))
                            {{-- Bouton pour effacer les poules --}}
                            <form method="GET" action="{{ route('erasePools', $tournament) }}">
                                @csrf
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    {{ __('Erase all pools') }}
                                </button>
                            </form>
                            <p class="text-sm text-gray-600 mt-2">
                                Cette action va supprimer toutes les poules.
                            </p>

                            <!-- Bouton pour générer les matches -->
                            @if($tournament->matches->count() == 0)
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
                                                    <div class="border-t pt-2">
                                                        <p class="text-sm text-gray-600">
                                                            <span class="font-medium">Joueurs:</span>
                                                            {{ $pool->users->count() }}
                                                        </p>
                                                        <p class="text-sm text-gray-600">
                                                            <span class="font-medium">Matches:</span>
                                                            {{ $pool->tournamentMatches->count() }}
                                                        </p>
                                                    </div>
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
                        @else
                            <p class="text-left text-gray-500 italic">{{ __('No pools generated yet.') }}</p>
                            @include('admin.tournaments.partials.pool-generator')
                        @endif
                        </div>
                    </div>
                    
                </div>

                <!-- Contenu de l'onglet: Liste des matches -->
                <div id="content-matches" x-show="activeTab === 'matches'" class="tab-content" x-cloak>
                    <h2 class="text-xl font-bold text-gray-800 mb-6">Liste des matches</h2>
                    <div class="overflow-x-auto">
                        @if (count($matches) > 0)
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
                                            {{ 'Pool' }}</th>
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
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $loop->iteration }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $match->player1->first_name }}
                                                {{ $match->player1->last_name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $match->player2->first_name }}
                                                {{ $match->player2->last_name }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $match->pool->name }}</td>
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
                                                    <form action="{{ route('resetMatch', $match) }}" method="POST"
                                                        class="inline">
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
                                                        class="flex items-center p-2 bg-gray-50 rounded-lg shadow-sm hover:bg-gray-100 transition">
                                                        <form action="{{ route('startMatch', $match) }}"
                                                            method="POST" class="flex w-full items-center space-x-2">
                                                            @csrf
                                                            <div class="flex items-center space-x-2">
                                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                                    class="h-5 w-5 text-indigo-500"
                                                                    viewBox="0 0 20 20" fill="currentColor">
                                                                    <path fill-rule="evenodd"
                                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z"
                                                                        clip-rule="evenodd" />
                                                                </svg>
                                                                <label for="table_id"
                                                                    class="text-sm font-medium text-gray-700">Table</label>
                                                            </div>

                                                            <select name="table_id" id="table_id"
                                                                class="pl-3 pr-10 py-1 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md shadow-sm">
                                                                <option selected value="" disabled>
                                                                    {{ __('Select a table') }}</option>
                                                                @foreach ($tables as $table)
                                                                    @if($table->pivot->is_table_free)
                                                                    <option value="{{ $table->id }}">
                                                                        {{ $table->name }}
                                                                        ==>
                                                                        {{ $table->room->name }}</option>
                                                                    @endif
                                                                @endforeach
                                                            </select>

                                                            <button type="submit"
                                                                class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                                                                <span>Démarrer</span>
                                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                                    class="ml-1 h-4 w-4" viewBox="0 0 20 20"
                                                                    fill="currentColor">
                                                                    <path fill-rule="evenodd"
                                                                        d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z"
                                                                        clip-rule="evenodd" />
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                @endif
                                                <x-input-error class="mt-2" :messages="$errors->get('table')" />
                                            </td>
                                        </tr>
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

                <!-- Contenu de l'onglet: Liste des tables -->
                <div id="content-tables" x-show="activeTab === 'tables'" class="tab-content" x-cloak>
                    <h2 class="text-xl font-bold text-gray-800 mb-6">Liste des matches</h2>
                    <div class="max-w-7xl mx-auto p-6">
                        <h1 class="text-2xl font-bold text-white mb-6">État des tables</h1>

                        <!-- Filtres et recherche -->
                        <div class="flex flex-wrap gap-4 mb-6">
                            <button
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">Toutes</button>
                            <button
                                class="px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-100 transition">Disponibles</button>
                            <button
                                class="px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-100 transition">Occupées</button>
                            <div class="ml-auto">
                                <input type="text" placeholder="Rechercher..."
                                    class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            </div>
                        </div>
                        <!-- Grille des tables avec espacement et responsive améliorés -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">

                            @foreach ($tables as $table)
                                @if ($table->pivot->is_table_free)
                                    <!-- Table 1 - Disponible -->
                                    <div
                                        class="group relative rounded-xl border border-green-400 bg-gradient-to-br from-green-50 to-green-100 p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
                                        <div class="absolute top-3 right-3">
                                            <span class="flex h-3 w-3">
                                                <span
                                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                                <span
                                                    class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                                            </span>
                                        </div>

                                        <div class="flex items-center space-x-4">
                                            <div
                                                class="flex items-center justify-center w-12 h-12 rounded-full bg-green-500 text-white font-bold text-xl shadow-inner">
                                                {{ $table->name }}
                                            </div>
                                            <div class="flex flex-col">
                                                <span
                                                    class="text-green-700 font-semibold text-lg">{{ __('Free') }}</span>
                                                {{-- <span class="text-green-600 text-sm">Libre depuis {{ round($table->pivot->match_ended_at->diffInMinutes(now())) }} min</span> --}}
                                            </div>
                                        </div>

                                        <div class="mt-4 flex justify-end">
                                            <button
                                                class="px-3 py-1 bg-green-500 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                                {{ __('Book') }}
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    {{-- Duration --}}
                                    @php
                                        $expected_match_duration = 20;
                                        $duration = round($table->pivot->match_started_at->diffInMinutes(now()));
                                        $percent = min(100, ($duration / $expected_match_duration) * 100);
                                    @endphp

                                    <!-- Table 2 - Occupée -->
                                    <div
                                        class="group relative rounded-xl border border-{{ $percent < 100 ? 'gray' : 'red' }}-400 bg-gradient-to-br from-{{ $percent < 100 ? 'gray' : 'red' }}-50 to-{{ $percent < 100 ? 'gray' : 'red' }}-100 p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
                                        <div class="absolute top-3 right-3">
                                            <span class="flex h-3 w-3">
                                                <span
                                                    class="relative inline-flex rounded-full h-3 w-3 bg-{{ $percent < 100 ? 'gray' : 'red' }}-500"></span>
                                            </span>
                                        </div>

                                        <div class="flex items-center space-x-4">
                                            <div
                                                class="flex items-center justify-center w-12 h-12 rounded-full bg-{{ $percent < 100 ? 'gray' : 'red' }}-500 text-white font-bold text-xl shadow-inner">
                                                {{ $table->name }}
                                            </div>
                                            <div class="flex flex-col">
                                                <span
                                                    class="text-{{ $percent < 100 ? 'gray' : 'red' }}-700 font-semibold text-lg">Occupée</span>
                                                <span
                                                    class="text-{{ $percent < 100 ? 'gray' : 'red' }}-600 text-sm">Depuis
                                                    {{ $duration }} min</span>
                                            </div>
                                        </div>

                                        <div class="mt-4 bg-white rounded-lg p-4 shadow-sm">
                                            <div class="flex items-center justify-between mb-2">
                                                <div class="flex items-center">
                                                    <svg class="w-4 h-4 text-gray-600 mr-1" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24"
                                                        xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                                        </path>
                                                    </svg>
                                                    @php
                                                        $match = $table->match->first();
                                                    @endphp
                                                    <span
                                                        class="text-gray-800 font-medium text-sm">{{ $match->player1->first_name }}
                                                        {{ $match->player1->last_name }}</span>
                                                </div>
                                                <span class="text-gray-500 text-xs">VS</span>
                                                <div class="flex items-center">
                                                    <span
                                                        class="text-gray-800 font-medium text-sm">{{ $match->player2->first_name }}
                                                        {{ $match->player2->last_name }}</span>
                                                    <svg class="w-4 h-4 text-gray-600 ml-1" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24"
                                                        xmlns="http://www.w3.org/2000/svg">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                                        </path>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="flex justify-center items-center mt-2">
                                                <div class="w-full bg-gray-200 rounded-full h-2">
                                                    <div class="bg-{{ $percent < 100 ? 'gray' : 'red' }}-500 h-2 rounded-full"
                                                        style="width: {{ $percent }}%"></div>
                                                </div>
                                            </div>
                                            <div class="mt-4 flex justify-end">
                                                <a href="{{ route('editMatch', $match) }}">
                                                    <button type="submit"
                                                        class="px-3 py-1 bg-blue-500 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                                        {{ __('Encode results') }}
                                                    </button>
                                                </a>
                                            </div>
                                        </div>

                                    </div>
                                @endif
                            @endforeach

                        </div>
                    </div>
                </div>
            </x-admin-block2>
        </div>
    </div>


    <style>
        [x-cloak] {
            display: none !important;
        }

        /* Animations pour les tables */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .grid>div {
            animation: fadeIn 0.6s ease-out forwards;
        }

        .grid>div:nth-child(1) {
            animation-delay: 0.05s;
        }

        .grid>div:nth-child(2) {
            animation-delay: 0.1s;
        }

        .grid>div:nth-child(3) {
            animation-delay: 0.15s;
        }

        .grid>div:nth-child(4) {
            animation-delay: 0.2s;
        }

        .grid>div:nth-child(5) {
            animation-delay: 0.25s;
        }

        .grid>div:nth-child(6) {
            animation-delay: 0.3s;
        }

        .grid>div:nth-child(7) {
            animation-delay: 0.35s;
        }

        .grid>div:nth-child(8) {
            animation-delay: 0.4s;
        }
    </style>
</x-app-layout>
