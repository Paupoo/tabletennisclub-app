<!-- resources/views/tournament/knockout-setup.blade.php -->
<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between mb-6">
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                            {{ __('Configuration de la phase finale') }} - {{ $tournament->name }}
                        </h2>
                        <div>
                            <a href="{{ route('tournamentSetup', $tournament) }}" class="text-blue-600 hover:underline">
                                &larr; Retour au tournoi
                            </a>
                        </div>
                    </div>

                    <form action="{{ route('configureKnockout', $tournament) }}" method="POST">
                        @csrf
                        <div class="mb-6">
                            <label for="starting_round" class="block text-sm font-medium text-gray-700">Phase de départ</label>
                            <select id="starting_round" name="starting_round" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
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
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Configurer la phase finale
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>