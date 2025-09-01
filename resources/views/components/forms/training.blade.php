{{-- Season --}}
<div>
    <x-input-label for="season_id" :value="__('Season')" />
    <x-select-input id="season_id" name="season_id" class="block w-full mt-1" required autofocus autocomplete="season_id">
        <option value="" disabled @selected(! old('season_id', $training->season_id))>{{ __('Pick up a season') }}</option>
        @foreach ($seasons as $season)
            <option value="{{ $season->id }}" @selected(old('season_id', $training->season_id) == $season->id)>
                {{ $season->name }}
            </option>
        @endforeach
    </x-select-input>
    <x-input-error class="mt-2" :messages="$errors->get('season_id')" />
</div>

{{-- Start date --}}
<div>
    <x-input-label for="start_date" :value="__('Start Date')" />
    <x-text-input id="start_date" name="start_date" type="date"
        class="block w-full mt-1"
        value="{{ old('start_date', $training->start?->format('Y-m-d')) }}"
        required autofocus autocomplete="start_date" />
    <x-input-error class="mt-2" :messages="$errors->get('start_date')" />
</div>

{{-- Recurrence --}}
@if(!$training->id)
<div class="flex flex-col">
    <fieldset class="flex flex-row flex-wrap">
        <div class="flex flex-auto gap-2">
            <x-radio-input id="none" name="recurrence" value="{{ \App\Enums\Recurrence::NONE->name }}" :checked="old('recurrence') === \App\Enums\Recurrence::NONE->name" />
            <x-input-label for="none" :value="__('None')" />
        </div>
        <div class="flex flex-auto gap-2">
            <x-radio-input id="daily" name="recurrence" value="{{ \App\Enums\Recurrence::DAILY->name }}" :checked="old('recurrence') === \App\Enums\Recurrence::DAILY->name" />
            <x-input-label for="daily" :value="__('Daily')" />
        </div>
        <div class="flex flex-auto gap-2">
            <x-radio-input id="weekly" name="recurrence" value="{{ \App\Enums\Recurrence::WEEKLY->name }}" :checked="old('recurrence') === \App\Enums\Recurrence::WEEKLY->name" />
            <x-input-label for="weekly" :value="__('Weekly')" />
        </div>
        <div class="flex flex-auto gap-2">
            <x-radio-input id="biweekly" name="recurrence" value="{{ \App\Enums\Recurrence::BIWEEKLY->name }}" :checked="old('recurrence') === \App\Enums\Recurrence::BIWEEKLY->name" />
            <x-input-label for="biweekly" :value="__('Biweekly')" />
        </div>
    </fieldset>
    <div>
        <x-input-error class="mt-2" :messages="$errors->get('recurrence')" />
    </div>
</div>
@endif

{{-- End date --}}
<div>
    <x-input-label for="end_date" :value="__('End Date')" />
    <x-text-input id="end_date" name="end_date" type="date"
        class="block w-full mt-1"
        value="{{ old('end_date', $training->end?->format('Y-m-d')) }}"
        autofocus autocomplete="end_date" />
    <x-input-error class="mt-2" :messages="$errors->get('end_date')" />
</div>

{{-- Time --}}
<div class="flex gap-x-6">
    {{-- Start --}}
    <div>
        <x-input-label for="start_time" :value="__('Between')" />
        <x-text-input id="start_time" name="start_time" type="time"
            class="block w-full mt-1"
            value="{{ old('start_time', $training->start?->format('H:i')) }}"
            required autofocus autocomplete="start_time" />
        <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
    </div>

    {{-- End --}}
    <div>
        <x-input-label for="end_time" :value="__('And')" />
        <x-text-input id="end_time" name="end_time" type="time"
            class="block w-full mt-1"
            value="{{ old('end_time', $training->end?->format('H:i')) }}"
            required autofocus autocomplete="end_time" />
        <x-input-error class="mt-2" :messages="$errors->get('end_time')" />
    </div>
</div>

{{-- Room --}}
<div>
    <x-input-label for="room_id" :value="__('Room')" />
    <x-select-input id="room_id" name="room_id" class="block w-full mt-1" required autofocus autocomplete="room_id">
        <option value="" disabled @selected(! old('room_id', $training->room_id))>{{ __('Choose a room') }}</option>
        @foreach ($rooms as $room)
            <option value="{{ $room->id }}" @selected(old('room_id', $training->room_id) == $room->id)>
                {{ $room->name }}
            </option>
        @endforeach
    </x-select-input>
    <x-input-error class="mt-2" :messages="$errors->get('room_id')" />
</div>

{{-- Type --}}
<div>
    <x-input-label for="type" :value="__('Type')" />
    <x-select-input id="type" name="type" class="block w-full mt-1" required autofocus autocomplete="type">
        <option value="" disabled @selected(! old('type', $training->type))>{{ __('Choose a type') }}</option>
        @foreach ($types as $type)
            <option value="{{ $type->name }}" @selected(old('type', $training->type) == $type->name)>
                {{ $type->value }}
            </option>
        @endforeach
    </x-select-input>
    <x-input-error class="mt-2" :messages="$errors->get('type')" />
</div>

{{-- Level --}}
<div>
    <x-input-label for="level" :value="__('Level')" />
    <x-select-input id="level" name="level" class="block w-full mt-1" required autofocus autocomplete="level">
        <option value="" disabled @selected(! old('level', $training->level))>{{ __('Select the level') }}</option>
        @foreach ($levels as $level)
            <option value="{{ $level->name }}" @selected(old('level', $training->level) == $level->name)>
                {{ $level->value }}
            </option>
        @endforeach
    </x-select-input>
    <x-input-error class="mt-2" :messages="$errors->get('level')" />
</div>

{{-- Trainer name --}}
<div>
    <x-input-label for="trainer_id" :value="__('Trainer Name')" />
    <x-select-input id="trainer_id" name="trainer_id" class="block w-full mt-1" autofocus autocomplete="trainer_id">
        <option value="" disabled @selected(! old('trainer_id', $training->trainer_id))>{{ __('Pick up trainer if any') }}</option>
        @foreach ($users as $user)
            <option value="{{ $user->id }}" @selected(old('trainer_id', $training->trainer_id) == $user->id)>
                {{ $user->last_name }} {{ $user->first_name }}
            </option>
        @endforeach
    </x-select-input>
    <x-input-error class="mt-2" :messages="$errors->get('trainer_id')" />
</div>