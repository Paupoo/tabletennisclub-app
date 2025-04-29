<x-app-layout>


    <div class="container mx-auto px-4 py-8">
        <!-- En-tête du tournoi -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="p-6">
                <h1 class="text-xl font-bold text-gray-700 mb-8">Tournaments</h1>

                <!-- Section Ajouter un tournoi -->
                <div :class="formOpen ? 'w-full' : 'w-72'" class="ml-auto mb-4 bg-white rounded-lg shadow-lg overflow-hidden" x-data="{ formOpen: false }">
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
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4"
                                x-data="{ startDate: '', endDate: '' }">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nom du
                                        tournoi</label>
                                    <input type="text" name="name" id="name" placeholder="Nom du tournoi"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                </div>

                                <div>
                                    <label for="startDate" class="block text-sm font-medium text-gray-700 mb-1" >Date de début</label>
                                    <input 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                        type="datetime-local"
                                        name="startDate"
                                        id="startDate"
                                        x-model="startDate"
                                        @input="endDate = startDate">
                                </div>

                                <div>
                                    <label for="endDate" class="block text-sm font-medium text-gray-700 mb-1">Date de fin</label>
                                    <input 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                        type="datetime-local"
                                        name="endDate"
                                        id="endDate"
                                        x-model="endDate"
                                        >
                                </div>

                                <div>
                                    <label for="room_ids" class="block text-sm font-medium text-gray-700 mb-1">Salle</label>
                                    <select multiple name="room_ids[]" id="room_ids"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                        @foreach($rooms as $room)
                                        <option value="{{ $room->id }}">{{ $room->name }} - max {{ $room->capacitycapacity_for_interclubs / 2 }} tables</option>
                                        @endforeach
                                    </select>
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

                <livewire:tournaments-table>
            </div>
        </div>
    </div>
</x-app-layout>
