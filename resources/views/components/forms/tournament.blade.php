<form action="{{ $tournament->id === null ? route('createTournament') : route('updateTournament', $tournament) }}" method="post" class="space-y-4">
    @csrf
    @method($tournament->id === null ? "POST" : "PUT")
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4"
        x-data="{ start_date: '{{ old('start_date', $tournament->start_date) }}', end_date: '{{ old('end_date', $tournament->end_date) }}' }">
        
        {{-- Name --}}
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nom du
                tournoi</label>
            <input type="text" name="name" id="name" placeholder="Nom du tournoi"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                value="{{ old('name', $tournament->name)}}"
                >
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        {{-- Start date --}}
        <div>
            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1" >Date de début</label>
            <input 
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                type="datetime-local"
                name="start_date"
                id="start_date"
                x-model="start_date"
                @input="end_date = start_date"
                >
            <x-input-error class="mt-2" :messages="$errors->get('start_date')" />
        </div>

        {{-- End date --}}
        <div>
            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">Date de fin</label>
            <input 
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                type="datetime-local"
                name="end_date"
                id="end_date"
                x-model="end_date"
                >
            <x-input-error class="mt-2" :messages="$errors->get('end_date')" />
        </div>

        {{-- Rooms --}}
        <div>
            <label for="room_ids" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Rooms') }}</label>
            <select multiple name="room_ids[]" id="room_ids"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                @foreach($rooms as $room)
                @if($room->total_playable_tables > 0)
                <option value="{{ $room->id }}"
                    {{ collect(old('room_ids', $tournament->rooms->pluck('id')))->contains($room->id) ? 'selected' : '' }}
                    >
                        {{ __($room->name . " ==> " . $room->total_playable_tables . " tables available") }}
                </option>
                @endif
                @endforeach
            </select>
            <x-input-error class="mt-2" :messages="$errors->get('room_ids')" />
        </div>

        {{-- Max Users --}}
        <div>
            <label for="max_users" class="block text-sm font-medium text-gray-700 mb-1">Nombre
                maximum de joueurs</label>
            <input type="number" name="max_users" id="max_users"
                value="{{ old('max_users', $tournament->max_users) }}"
                placeholder="Nombre max. de joueurs"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            <x-input-error class="mt-2" :messages="$errors->get('max_users')" />
        </div>

        {{-- Price --}}
        <div>
            <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Prix
                d'inscription (€)</label>
            <input type="number"
                name="price"
                id="price"
                placeholder="{{ __('Price') }}"
                step="0.01"
                value="{{ old('price', $tournament->price)}}"
                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                <x-input-error class="mt-2" :messages="$errors->get('price')" />
        </div>
    </div>

    <div class="mt-6 flex justify-end">
        <button type="submit"
            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            @if($tournament->id === null)
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20"
                fill="currentColor">
                <path fill-rule="evenodd"
                    d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                    clip-rule="evenodd" />
            </svg>
            @endif
            {{ $tournament->id === null ? __('Create tournament') : __('Update Tournament') }}
        </button>
    </div>
</form>