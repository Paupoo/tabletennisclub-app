<x-app-layout>


    <div class="container mx-auto px-4 py-8">
        <!-- En-tête du tournoi -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="p-6">
                <h1 class="text-xl font-bold text-gray-700 mb-8">Tournaments</h1>

                <!-- Section Ajouter un tournoi -->
                <div class="mb-8 bg-white rounded-lg shadow-lg overflow-hidden" x-data="{ formOpen: false }">
                    <div class="flex items-center justify-between bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-800">Ajouter un tournoi</h2>
                        <button @click="formOpen = !formOpen"
                            class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            title="Ajouter un tournoi">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                fill="currentColor" x-show="!formOpen">
                                <path fill-rule="evenodd"
                                    d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                    clip-rule="evenodd" />
                            </svg>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                fill="currentColor" x-show="formOpen">
                                <path fill-rule="evenodd"
                                    d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>

                    <div x-show="formOpen" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform -translate-y-2"
                        x-transition:enter-end="opacity-100 transform translate-y-0"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 transform translate-y-0"
                        x-transition:leave-end="opacity-0 transform -translate-y-2" class="p-6">
                        <form action="{{ route('createTournament') }}" method="post" class="space-y-4">
                            @csrf
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nom du
                                        tournoi</label>
                                    <input type="text" name="name" id="name" placeholder="Nom du tournoi"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="startDate" class="block text-sm font-medium text-gray-700 mb-1">Date de
                                        début</label>
                                    <input type="datetime-local" name="startDate" id="startDate"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="endDate" class="block text-sm font-medium text-gray-700 mb-1">Date de
                                        fin</label>
                                    <input type="datetime-local" name="endDate" id="endDate"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="maxUsers" class="block text-sm font-medium text-gray-700 mb-1">Nombre
                                        maximum de joueurs</label>
                                    <input type="number" name="maxUsers" id="maxUsers"
                                        placeholder="Nombre max. de joueurs"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Prix
                                        d'inscription (€)</label>
                                    <input type="number" name="price" id="price" placeholder="Prix"
                                        step="0.01"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20"
                                        fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Créer le tournoi
                                </button>
                            </div>
                        </form>
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

                <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
                    <div class="p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">{{ __('Tournaments') }}</h2>

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
                                            {{ __('Status') }}</th>
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
                                                    <div class="font-medium text-gray-900"><a
                                                            href="{{ route('tournamentShow', $tournament) }}">{{ $tournament->name }}</a>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <div class="text-gray-900">
                                                        {{ $tournament->start_date->format('d M Y\ \a\t\ H:i') }}</div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">{{ $tournament->price }} €
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    {{ $tournament->total_users }} / {{ $tournament->max_users }}
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <div x-data="{ showForm: false }"
                                                        class="flex justify-center items-center">
                                                        <div class="flex items-center space-x-4">
                                                            <!-- Status display -->
                                                            <span
                                                                class="px-2 py-1 text-sm font-medium rounded-md
                                                                    @if ($tournament->status == 'draft') bg-gray-100 text-gray-700
                                                                    @elseif($tournament->status == 'open') bg-blue-100 text-blue-700
                                                                    @elseif($tournament->status == 'pending') bg-purple-100 text-purple-700
                                                                    @elseif($tournament->status == 'closed') bg-green-100 text-green-700 @endif">
                                                                @if ($tournament->status == 'draft')
                                                                    {{ __('Unpublished') }}
                                                                @elseif($tournament->status == 'open')
                                                                    {{ __('Published') }}
                                                                @elseif($tournament->status == 'pending')
                                                                    {{ __('Started') }}
                                                                @elseif($tournament->status == 'closed')
                                                                    {{ __('Closed') }}
                                                                @endif
                                                            </span>

                                                            {{-- <!-- Toggle button for status change form -->
                                                            <button type="button" 
                                                                    @click="showForm = !showForm"
                                                                    class="inline-flex items-center p-1 border border-transparent rounded-full text-indigo-600 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                                     class="h-5 w-5"
                                                                     viewBox="0 0 20 20" fill="currentColor">
                                                                    <path fill-rule="evenodd"
                                                                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z"
                                                                          clip-rule="evenodd" />
                                                                </svg>
                                                            </button>
                                                        
                                                            <!-- Status change form (hidden by default) -->
                                                            <form x-show="showForm" action="{{ route('updateStatusTournament', $tournament) }}" method="POST" class="flex items-center space-x-2">
                                                                @csrf
                                                                @method('PATCH')
                                                                <select name="status" id="status-{{ $tournament->id }}"
                                                                        class="pl-3 pr-10 py-1 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 rounded-md shadow-sm">
                                                                    <option value="draft" {{ $tournament->status == 'draft' ? 'selected' : '' }}>{{ __('Unpublish') }}</option>
                                                                    <option value="open" {{ $tournament->status == 'open' ? 'selected' : '' }}>{{ __('Publish') }}</option>
                                                                    <option value="pending" {{ $tournament->status == 'pending' ? 'selected' : '' }}>{{ __('Start') }}</option>
                                                                    <option value="closed" {{ $tournament->status == 'closed' ? 'selected' : '' }}>{{ __('Close') }}</option>
                                                                </select>
                                                                <button type="submit"
                                                                        class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                                                                    <span>{{ __('Change') }}</span>
                                                                    <svg xmlns="http://www.w3.org/2000/svg"
                                                                         class="ml-1 h-4 w-4" viewBox="0 0 20 20"
                                                                         fill="currentColor">
                                                                        <path fill-rule="evenodd"
                                                                              d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z"
                                                                              clip-rule="evenodd" />
                                                                    </svg>
                                                                </button> --}}
                                                            </form>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <div class="flex flex-row justify-end gap-2">
                                                        @if($tournament->status === 'open')
                                                        <a href="{{ route('unpublishTournament', $tournament) }}">
                                                            <button
                                                                class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-gray-500 hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 mr-2"
                                                                title="{{ __('Set as draft') }}">
                                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                                    class="h-5 w-5" viewBox="0 0 20 20"
                                                                    fill="currentColor">
                                                                    <path
                                                                        d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                                                </svg>
                                                            </button>
                                                        </a>
                                                        @endif

                                                        @if($tournament->status === 'draft')
                                                        <a href="{{ route('publishTournament', $tournament) }}">
                                                            <button
                                                                class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-2"
                                                                title="{{ __('Publish and open registrations') }}">
                                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                                    class="h-5 w-5" viewBox="0 0 20 20"
                                                                    fill="currentColor">
                                                                    <path
                                                                        d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" />
                                                                </svg>
                                                            </button>
                                                        </a>
                                                        @endif

                                                        <!-- Update -->
                                                        <a href="{{ route('tournamentShow', $tournament) }}">
                                                            <button
                                                                class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-amber-500 hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 mr-2"
                                                                title="{{ __('Edit tournament') }}">
                                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                                    class="h-5 w-5" viewBox="0 0 20 20"
                                                                    fill="currentColor">
                                                                    <path
                                                                        d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" />
                                                                    <path fill-rule="evenodd"
                                                                        d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"
                                                                        clip-rule="evenodd" />
                                                                </svg>
                                                            </button>
                                                        </a>
                                                        <!-- Delete -->
                                                        <a href="{{ route('deleteTournament', $tournament) }}">
                                                            <button
                                                                class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                                                title="{{ __('Delete') }}">
                                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                                    class="h-5 w-5" viewBox="0 0 20 20"
                                                                    fill="currentColor">
                                                                    <path fill-rule="evenodd"
                                                                        d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                                                        clip-rule="evenodd" />
                                                                </svg>
                                                            </button>
                                                        </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="6" class="px-4 py-4 text-center text-gray-500 italic">
                                                {{ __('No tournament found.') }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <!-- Légende des statuts -->
                        <div class="mb-6 bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <h3 class="text-sm font-medium text-gray-700 mb-3">{{ __('Legend') }}</h3>
                            <div class="flex flex-wrap gap-4">
                                <div class="flex items-center">
                                    <div class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 mr-2"
                                        title="{{ __('Set as draft') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                            fill="currentColor">
                                            <path
                                                d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                        </svg>
                                    </div>
                                    <span class="text-sm text-gray-600">{{ __('Draft') }}</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-amber-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 mr-2"
                                        title="{{ __('Edit tournament') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                            fill="currentColor">
                                            <path
                                                d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" />
                                            <path fill-rule="evenodd"
                                                d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <span class="text-sm text-gray-600">{{ __('Edit') }}</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-2"
                                        title="{{ __('Publish and open registrations') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                            fill="currentColor">
                                            <path
                                                d="M7 3a1 1 0 000 2h6a1 1 0 100-2H7zM4 7a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM2 11a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2H4a2 2 0 01-2-2v-4z" />
                                        </svg>
                                    </div>
                                    <span class="text-sm text-gray-600">{{ __('Publish') }}</span>
                                </div>

                                <div class="flex items-center">
                                    <div
                                        class="inline-flex items-center p-2 border border-transparent rounded-full text-white bg-red-600 hover:bg-red-600 mr-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                            fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <span class="text-sm text-gray-600">{{ __('Delete') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
