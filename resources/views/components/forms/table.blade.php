<form action="{{ $table->id === null ? route('tables.store') :  route('tables.update', $table) }}" method="POST" class="mt-6 space-y-6">
    @csrf
    @method($table->id === null ? "POST" : "PATCH")

    {{-- Name --}}
    <div>
        <x-form.field name="name" :label="__('Name')">
            <x-text-input id="name" name="name" type="text" class="block w-full mt-1"
                :value="old('name', $table->name)" :placeholder="__('Table name or number')" required autofocus autocomplete="name" />
        </x-form.field>
    </div>

    {{-- Date of purchase --}}
    <div>
        <x-form.field name="purchased_on" :label="__('Date of Purchase (facultative)')">
            <x-text-input id="purchased_on" name="purchased_on" type="date" class="block w-full mt-1"
                :value="old('purchased_on', $table->name)" autofocus />
        </x-form.field>
    </div>

    {{-- State --}}
    <div>
        <x-form.field name="state" :label="__('State (facultative)')">
            <x-text-input id="state" name="state" list="state_list" class="block w-full mt-1"
                :value="old('state', $table->name)" autofocus />
            <datalist id="state_list">
                <option value="New"></option>
                <option value="Used"></option>
                <option value="Degraded"></option>
                <option value="Out of Service"></option>
                <option value="Unknown"></option>
            </datalist>
        </x-form.field>
    </div>

    {{-- Room --}}
    <div>
        <x-form.field name="room_id" :label="__('Room')">
            <x-select-input id="room_id" name="room_id" class="block w-full mt-1" required autofocus>
                <option selected disable>{{ __('Select a room') }}</option>
                @foreach ($rooms as $room)
                    <option @selected(old('room_id', $table->room_id) == $room->id) value="{{ $room->id }}">{{ $room->name }}</option>
                @endforeach
            </x-select-input>
        </x-form.field>
    </div>

    <div>
        <x-button type="submit"
            :label="$table->id === null ? __('Create new table') : __('Update table')"
            class="btn-primary" />
    </div>

</form>
