<x-app-layout>


    <div class="container mx-auto px-4 py-8">
        <!-- En-tête du tournoi -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="p-6">
                <h1 class="text-xl font-bold text-gray-700 mb-8">Tournaments</h1>

                <form action="{{ route('createTournament') }}" method="post">
                    @csrf
                    <input type="text" name="name" id="" placeholder="name">
                    <input type="datetime-local" name="startDate" id="">
                    <input type="number" name="maxUsers" placeholder="maxUsers" id="">
                    <input type="number" name="price" placeholder="price" id="" step="0.1">
                    <button type="submit">Create</button>
                </form>

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
                                            Name</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                            Start Date</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                            Price</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                            Total Players</th>
                                        <th
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase tracking-wider">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @if (count($tournaments) > 0)
                                        @foreach ($tournaments as $tournament)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <span
                                                        class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-blue-100 text-blue-800 font-medium text-sm">
                                                        {{ $loop->iteration }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <div class="font-medium text-gray-900"><a href="{{ route('tournamentShow', $tournament) }}">{{ $tournament->name }}</a></div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <div class="text-gray-900">
                                                        {{ $tournament->start_date->format('d M Y\ \a\t\ H:i') }}</div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">{{ $tournament->price }} €</td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    {{ $tournament->total_users }} / {{ $tournament->max_users }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <div>
                                                        <!-- Delete -->
                                                        <a href="{{ route('deleteTournament', $tournament) }}" <button
                                                            class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                                            title="{{ __('Delete') }}">
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
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
