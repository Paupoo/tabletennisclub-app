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
                        <a href="{{ route('tournamentSetup', $tournament) }}"
                            class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition duration-200 text-center">
                            Configurer le tournoi
                        </a>
                        <a href="{{ route('tournamentSetup', $tournament) }}"
                            class="inline-block px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md transition duration-200 text-center">
                            Démarrer le tournoi
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
                                <p class="font-bold text-lg">{{ $tournament->start_date->format('H/m/Y \a\t H:m') }}</p>
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

        <!-- Section des joueurs -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="p-6">
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
                                            <div class="font-medium text-gray-900">{{ $user->first_name }} {{ $user->last_name }}</div>
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
                                                <a href="{{ route('tournamentToggleHasPaid', [$tournament, $user]) }}">
                                                    @if(!$user->pivot->has_paid)
                                                    <!-- Marquer comme payé -->
                                                    <button class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500" title="Marquer comme payé">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                    @else
                                                    <!-- Marquer comme impayé -->
                                                    <button class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-yellow-500 hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500" title="Marquer comme impayé">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                    @endif
                                                </a>
                                                
                                                <!-- Désinscrire -->
                                                <a href="{{ route('tournamentUnregister', [$tournament, $user]) }}"
                                                    <button class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500" title="Désinscrire">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                        </svg>
                                                    </button>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-center text-gray-500 italic">Aucun joueur
                                        inscrit pour le moment.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

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
                                            <div class="font-medium text-gray-900">{{ $user->first_name }} {{ $user->last_name }}</div>
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
                                    <td colspan="3" class="px-4 py-4 text-center text-gray-500 italic">Aucun joueur
                                        trouvé.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
