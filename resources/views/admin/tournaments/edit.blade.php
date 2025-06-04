<x-app-layout>
    <x-slot name="header">
        {{-- Header --}}
        <div class="flex flex-row gap-2 items-center">
            <x-admin.title :title="$tournament->name" />
            <x-tournament.status-badge :status="$tournament->status" />
            <x-admin.action-menu :tournament="$tournament" :statusesAllowed="$statusesAllowed"/>
        </div>
    </x-slot>
    <x-admin-block>
        <div class="border-b pb-4 mb-6">
            <h3 class="text-2xl font-bold text-gray-800">{{ __('Tournament Configuration') }}</h3>
        </div>

        <!-- Informations sur le tournoi -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
            <h4 class="text-lg font-medium mb-4 text-gray-800">{{ __('Main parameters') }}</h4>
            <p class="mt-2 mb-4 text-sm text-gray-500">
                {{ __('Please define your tournament parameters here.') }}
            </p>
            <x-forms.tournament :rooms="$rooms" :tournament="$tournament" />
        </div>

        <!-- Formulaire de génération des pools -->
        <div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
            <h4 class="text-lg font-medium mb-4 text-gray-800">{{ __('Pools parameters') }}</h4>

            <x-forms.pools-generation :tournament="$tournament" />


        </div>



        <div class="g-white border border-gray-200 rounded-lg p-6 mb-8">
            <div class="flex justify-between mb-6">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Configuration de la phase finale') }} - {{ $tournament->name }}
                </h2>
                <div>
                    <a href="{{ route('tournamentShow', $tournament) }}" class="text-blue-600 hover:underline">
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

    </x-admin-block>

</x-app-layout>
