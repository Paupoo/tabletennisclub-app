<form action="{{ $table->id === null ? route('tables.store') :  route('tables.update', $table) }}" method="POST" class="mt-6 space-y-6">
    @csrf
    @method($table->id === null ? "POST" : "PATCH")

    {{-- Name --}}
    <div>
        <x-input-label for="name" :value="__('Name')" />
        <x-text-input id="name" name="name" type="text" class="block w-full mt-1"
            :value="old('name', $table->name)" placeholder="{{ __('Table name or number') }}" required autofocus autocomplete="name"></x-text-input>
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    {{-- Date of purchase --}}
    <div>
        <x-input-label for="purchased_on" :value="__('Date of Purchase (facultative)')" />
        <x-text-input id="purchased_on" name="purchased_on" type="date" class="block w-full mt-1"
            :value="old('purchased_on', $table->name)" autofocus></x-text-input>
        <x-input-error class="mt-2" :messages="$errors->get('purchased_on')" />
    </div>

    {{-- State --}}
    <div>
        <x-input-label for="state" :value="__('State (facultative)')" />
        <x-text-input id="state" name="state" list="state_list" class="block w-full mt-1"
            :value="old('state', $table->name)" autofocus>
        </x-text-input>
        <datalist id="state_list">
            <option value="New"></option>
            <option value="Used"></option>
            <option value="Degraded"></option>
            <option value="Unusable"></option>
            <option value="Unknown"></option>
        </datalist>
        <x-input-error class="mt-2" :messages="$errors->get('state')" />
    </div>

    {{-- Room --}}
    <div>
        <x-input-label for="room_id" :value="__('Room')" />
        <x-select-input id="room_id" name="room_id" class="block w-full mt-1" required autofocus>
            <option selected disable>{{ __('Select a room') }}</option>
            @foreach ($rooms as $room)
                <option @selected(old('room_id', $table->room_id) == $room->id) value="{{ $room->id }}">{{ $room->name }}</option>
            @endforeach
        </x-text-input>
        <x-input-error class="mt-2" :messages="$errors->get('room_id')" />
    </div>

    <div>
        <x-primary-button>
            @if ($table->id === null)
                {{ __('Create new table') }}
            @else
                {{ __('Update table') }}
            @endif
        </x-primary-button>
    </div>

</form>