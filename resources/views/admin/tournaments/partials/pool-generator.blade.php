<!-- Formulaire de génération des pools -->
<div class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
    <h4 class="text-lg font-medium mb-4 text-gray-800">Générer les pools</h4>
    <form action="{{ route('tournaments.generate-pools', $tournament) }}"
        method="POST">
        @csrf
        <div class="mb-4 w-full max-w-2xl">
            <p class="mt-2 mb-4 text-sm text-gray-500">
                Les joueurs seront distribués selon leur classement. Vous pouvez
                définir un
                nombre de poules ou un nombre minimum de matches joués en cas
                d'élimination à la
                fin de la phase de poules.
            </p>
            <label for="number_of_pools"
                class="block text-sm font-medium text-gray-700 mb-2">Nombre de
                pools à créer
                :</label>
            <div class="relative">
                <select name="number_of_pools" id="number_of_pools"
                    class="block w-full appearance-none px-3 py-2 pr-8 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-500">
                    @for ($i = 2; $i <= 8; $i++)
                        <option value="{{ $i }}">
                            {{ $i }} pools
                        </option>
                    @endfor
                </select>
                <input type="hidden" name="minMatches" value=0>
                <div
                    class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                    <svg class="h-4 w-4 fill-current"
                        xmlns="http://www.w3.org/2000/svg"
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

    <form action="{{ route('tournaments.generate-pools', $tournament) }}"
        method="POST">
        @csrf
        <div class="mb-4 w-full max-w-2xl">
            <label for="number_of_pools"
                class="block text-sm font-medium text-gray-700 mb-2">Nombre
                minimum de matches joués&nbsp;:</label>
            <div class="relative">
                <select name="number_of_pools" id="number_of_pools"
                    class="block w-full appearance-none px-3 py-2 pr-8 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-gray-500">
                    @for ($i = 2; $i <= 8; $i++)
                        <option value="{{ $i }}">
                            {{ $i }} matches
                        </option>
                    @endfor
                </select>
                <input type="hidden" name="minMatches" value=0>
                <div
                    class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
                    <svg class="h-4 w-4 fill-current"
                        xmlns="http://www.w3.org/2000/svg"
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