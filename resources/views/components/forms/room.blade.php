<form action="{{ $room->id === null ? route('rooms.store') :  route('rooms.update', $room) }}" method="POST" class="mt-6 space-y-6">
    @csrf
    @method($room->id === null ? "POST" : "PATCH")

    {{-- Name --}}
    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="block w-full mt-1"
            :value="old('name', $room->name)" required autofocus autocomplete="name"></x-text-input>
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    {{-- Street --}}
    <div>
        <x-input-label for="street" :value="__('Street')" />
        <x-text-input id="street" name="street" type="text" class="block w-full mt-1"
            :value="old('street', $room->street)" required autofocus autocomplete="street"></x-text-input>
        <x-input-error class="mt-2" :messages="$errors->get('street')" />
    </div>

    {{-- City code --}}
    <div>
        <x-input-label for="city_code" :value="__('City Code')" />
        <x-text-input id="city_code" name="city_code" type="number" min="1000" max="9999" class="block w-full mt-1"
            :value="old('city_code', $room->city_code)" required autofocus autocomplete="city_code"></x-text-input>
        <x-input-error class="mt-2" :messages="$errors->get('city_code')" />
    </div>

    {{-- City name --}}
    <div>
        <x-input-label for="city_name" :value="__('City')" />
        <x-text-input id="city_name" name="city_name" type="text" class="block w-full mt-1"
            :value="old('city_name', $room->city_name)" required autofocus autocomplete="city_name"></x-text-input>
        <x-input-error class="mt-2" :messages="$errors->get('city_name')" />
    </div>

    {{-- Building/Site name --}}
    <div>
        <x-input-label for="building_name" :value="__('Building/Site name')" />
        <x-text-input id="building_name" name="building_name" type="text"
            class="block w-full mt-1" :value="old('building_name', $room->building_name)" required autofocus
            autocomplete="building_name"></x-text-input>
        <x-input-error class="mt-2" :messages="$errors->get('building_name')" />
    </div>

    {{-- Access Description --}}
    <div>
        <x-input-label for="access_description" :value="__('Access description')" />
        <x-textarea-input id="access_description" name="access_description" type="text"
            class="block w-full mt-1"
            autofocus>{{ old('access_description', $room->access_description) }}</x-textarea-input>
        <x-input-error class="mt-2" :messages="$errors->get('access_description')" />
    </div>

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

    <div>
        <x-primary-button>
            @if ($room->id === null)
                {{ __('Create new room') }}
            @else
                {{ __('Update room') }}
            @endif
        </x-primary-button>
    </div>

</form>