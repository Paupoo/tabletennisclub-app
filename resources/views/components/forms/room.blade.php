<form action="{{ $room->id === null ? route('rooms.store') :  route('rooms.update', $room) }}" method="POST" class="mt-6 space-y-6 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    @csrf
    @method($room->id === null ? "POST" : "PATCH")

    {{-- Section Identification --}}
    <div class="bg-white p-4 sm:p-6 rounded-lg shadow-sm border border-gray-200">
        <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Room Information') }}</h3>
        
        {{-- Name --}}
        <div class="mb-6">
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="block w-full mt-1"
                :value="old('name', $room->name)" placeholder="{{ __('Demeester -1') }}" required autofocus autocomplete="name"></x-text-input>
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>
    </div>

    {{-- Section Adresse --}}
    <div class="bg-white p-4 sm:p-6 rounded-lg shadow-sm border border-gray-200">
        <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Address') }}</h3>
        
        {{-- Street --}}
        <div class="mb-6">
            <x-input-label for="street" :value="__('Street')" />
            <x-text-input id="street" name="street" type="text" class="block w-full mt-1"
                :value="old('street', $room->street)" placeholder="{{ __('Rue de l\'invasion, 80') }}" required autofocus autocomplete="street"></x-text-input>
            <x-input-error class="mt-2" :messages="$errors->get('street')" />
        </div>

        {{-- City code et City name sur la même ligne en desktop --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <x-input-label for="city_code" :value="__('City Code')" />
                <x-text-input id="city_code" name="city_code" type="number" min="1000" max="9999" class="block w-full mt-1"
                    :value="old('city_code', $room->city_code)" placeholder="{{ __('1340')}}" required autofocus autocomplete="city_code"></x-text-input>
                <x-input-error class="mt-2" :messages="$errors->get('city_code')" />
            </div>

            <div>
                <x-input-label for="city_name" :value="__('City')" />
                <x-text-input id="city_name" name="city_name" type="text" class="block w-full mt-1"
                    :value="old('city_name', $room->city_name)" placeholder="Ottignies" required autofocus autocomplete="city_name"></x-text-input>
                <x-input-error class="mt-2" :messages="$errors->get('city_name')" />
            </div>
        </div>

        {{-- Building/Site name --}}
        <div class="mb-6">
            <x-input-label for="building_name" :value="__('Building/Site name')" />
            <x-text-input id="building_name" name="building_name" type="text"
                class="block w-full mt-1" :value="old('building_name', $room->building_name)" placeholder="{{ __('Centre sportif Jean Demeeter') }}" required autofocus
                autocomplete="building_name"></x-text-input>
            <x-input-error class="mt-2" :messages="$errors->get('building_name')" />
        </div>

        {{-- Access Description --}}
        <div>
            <x-input-label for="access_description" :value="__('Access description')" />
            <x-textarea-input id="access_description" name="access_description" type="text"
                class="block w-full mt-1 min-h-[100px] sm:min-h-[120px]" placeholder="{{ __('To access the room, just follow the main path at the end of the parking...') }}"
                autofocus>{{ old('access_description', $room->access_description) }}</x-textarea-input>
            <x-input-error class="mt-2" :messages="$errors->get('access_description')" />
        </div>
    </div>

    {{-- Section Capacités --}}
    <div class="bg-white p-4 sm:p-6 rounded-lg shadow-sm border border-gray-200">
        <h3 class="text-lg font-medium text-gray-900 mb-4">{{ __('Capacities') }}</h3>
        <p class="text-sm font-light text-gray-800 mb-4">{{ __('Set the maximum number of table per activity') }}</p>
        
        {{-- Capacités sur la même ligne en desktop --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {{-- Training capacity --}}
            <div>
                <x-input-label for="capacity_for_trainings" :value="__('Training capacity')" />
                <x-text-input id="capacity_for_trainings" name="capacity_for_trainings" type="number"
                    class="block w-full mt-1" :value="old('capacity_for_trainings', $room->capacity_for_trainings)" required autofocus
                    autocomplete="capacity_for_trainings"></x-text-input>
                <x-input-error class="mt-2" :messages="$errors->get('capacity_for_trainings')" />
            </div>

            {{-- Matches capacity --}}
            <div>
                <x-input-label for="capacity_for_interclubs" :value="__('Matches capacity')" />
                <x-text-input id="capacity_for_interclubs" name="capacity_for_interclubs" type="number"
                    class="block w-full mt-1" :value="old('capacity_for_interclubs', $room->capacity_for_interclubs)" required autofocus
                    autocomplete="capacity_for_interclubs"></x-text-input>
                <x-input-error class="mt-2" :messages="$errors->get('capacity_for_interclubs')" />
            </div>
        </div>
    </div>

    {{-- Section Bouton --}}
    <div class="flex justify-end pt-4">
        <x-primary-button class="sm:w-auto px-8 py-3 text-base sm:text-sm bg-club-blue">
            @if ($room->id === null)
                {{ __('Create new room') }}
            @else
                {{ __('Update room') }}
            @endif
        </x-primary-button>
    </div>

</form>