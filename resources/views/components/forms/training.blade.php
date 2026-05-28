<div x-data="{ recurrence: '{{ old('recurrence', $training->recurrence?->name ?? App\Enums\Recurrence::NONE->name) }}' }" class="space-y-6">

    {{-- Season --}}
    <div>
        <x-form.field name="season_id" :label="__('Season')">
            <x-select-input id="season_id" name="season_id" class="block w-full mt-1" required autofocus
                autocomplete="season_id">
                <option value="" disabled @selected(!old('season_id', $training->season_id))>{{ __('Pick up a season') }}</option>
                @foreach ($seasons as $season)
                    <option value="{{ $season->id }}" @selected(old('season_id', $training->season_id) == $season->id)>
                        {{ $season->name }}
                    </option>
                @endforeach
            </x-select-input>
        </x-form.field>
    </div>

    {{-- Recurrence --}}
    @if (!$training->id)
        <div class="space-y-6">
            {{-- Recurrence selection --}}
            <div>
                <x-input-label :value="__('Choose the recurrence')" class="mb-3" />
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <label for="none" class="relative flex items-center gap-2 px-4 py-3 border rounded-lg cursor-pointer transition-colors"
                        :class="recurrence === '{{ \App\Enums\Recurrence::NONE->name }}' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-300 dark:border-gray-600 hover:border-gray-400'">
                        <x-radio-input id="none" name="recurrence"
                            value="{{ \App\Enums\Recurrence::NONE->name }}"
                            :checked="old('recurrence') === \App\Enums\Recurrence::NONE->name"
                            x-model="recurrence"
                            class="shrink-0" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('None') }}</span>
                    </label>

                    <label for="daily" class="relative flex items-center gap-2 px-4 py-3 border rounded-lg cursor-pointer transition-colors"
                        :class="recurrence === '{{ \App\Enums\Recurrence::DAILY->name }}' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-300 dark:border-gray-600 hover:border-gray-400'">
                        <x-radio-input id="daily" name="recurrence"
                            value="{{ \App\Enums\Recurrence::DAILY->name }}"
                            :checked="old('recurrence') === \App\Enums\Recurrence::DAILY->name"
                            x-model="recurrence"
                            class="shrink-0" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Daily') }}</span>
                    </label>

                    <label for="weekly" class="relative flex items-center gap-2 px-4 py-3 border rounded-lg cursor-pointer transition-colors"
                        :class="recurrence === '{{ \App\Enums\Recurrence::WEEKLY->name }}' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-300 dark:border-gray-600 hover:border-gray-400'">
                        <x-radio-input id="weekly" name="recurrence"
                            value="{{ \App\Enums\Recurrence::WEEKLY->name }}"
                            :checked="old('recurrence') === \App\Enums\Recurrence::WEEKLY->name"
                            x-model="recurrence"
                            class="shrink-0" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Weekly') }}</span>
                    </label>

                    <label for="biweekly" class="relative flex items-center gap-2 px-4 py-3 border rounded-lg cursor-pointer transition-colors"
                        :class="recurrence === '{{ \App\Enums\Recurrence::BIWEEKLY->name }}' ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-300 dark:border-gray-600 hover:border-gray-400'">
                        <x-radio-input id="biweekly" name="recurrence"
                            value="{{ \App\Enums\Recurrence::BIWEEKLY->name }}"
                            :checked="old('recurrence') === \App\Enums\Recurrence::BIWEEKLY->name"
                            x-model="recurrence"
                            class="shrink-0" />
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Biweekly') }}</span>
                    </label>
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('recurrence')" />
            </div>

            {{-- Options conditionnelles selon la récurrence --}}
            <div class="p-5 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
                {{-- Si aucune récurrence : lien vers un pack existant --}}
                <div x-show="recurrence === '{{ \App\Enums\Recurrence::NONE->name }}'" x-cloak>
                    <x-form.field name="training_pack_id" :label="__('Add to an existing training pack ?')">
                        <x-select-input id="training_pack_id" name="training_pack_id" class="block w-full mt-1">
                            <option value="">{{ __('None') }}</option>
                            @foreach ($trainingPacks as $trainingPack)
                                <option value="#" @selected(old('training_pack_id') == $trainingPack)>
                                    {{ $trainingPack }}
                                </option>
                            @endforeach
                        </x-select-input>
                    </x-form.field>
                </div>

                {{-- Si récurrence : création d'un nouveau pack --}}
                <div x-show="recurrence !== '{{ \App\Enums\Recurrence::NONE->name }}'" x-cloak>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                        {{ __('Training Pack Details') }}
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-form.field name="training_pack_name" :label="__('Name')">
                                <x-text-input id="training_pack_name" name="training_pack_name" type="text"
                                    class="block w-full mt-1"
                                    value="{{ old('training_pack_name', $training->training_pack_name ?? '') }}" />
                            </x-form.field>
                        </div>
                        <div>
                            <x-form.field name="training_pack_price" :label="__('Price')">
                                <x-text-input id="training_pack_price" name="training_pack_price" type="number"
                                    class="block w-full mt-1" step="0.01" min="0"
                                    value="{{ old('training_pack_price', $training->training_pack_price ?? '') }}" />
                            </x-form.field>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">
                        {{ __('A training pack will be created with these recurrent sessions.') }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Start date --}}
    <div>
        <x-form.field name="start_date" :label="__('Start Date')">
            <x-text-input id="start_date" name="start_date" type="date" class="block w-full mt-1"
                value="{{ old('start_date', $training->start?->format('Y-m-d')) }}" required autofocus
                autocomplete="start_date" />
        </x-form.field>
    </div>

    {{-- End date --}}
    <div x-show="recurrence !== '{{ \App\Enums\Recurrence::NONE->name }}'" x-cloak>
        <x-form.field name="end_date" :label="__('End Date')">
            <x-text-input id="end_date" name="end_date" type="date" class="block w-full mt-1"
                value="{{ old('end_date', $training->end?->format('Y-m-d')) }}" autofocus autocomplete="end_date" />
        </x-form.field>
    </div>

    {{-- Time --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <x-form.field name="start_time" :label="__('Start Time')">
                <x-text-input id="start_time" name="start_time" type="time" class="block w-full mt-1"
                    value="{{ old('start_time', $training->start?->format('H:i')) }}" required autofocus
                    autocomplete="start_time" />
            </x-form.field>
        </div>

        <div>
            <x-form.field name="end_time" :label="__('End Time')">
                <x-text-input id="end_time" name="end_time" type="time" class="block w-full mt-1"
                    value="{{ old('end_time', $training->end?->format('H:i')) }}" required autofocus
                    autocomplete="end_time" />
            </x-form.field>
        </div>
    </div>

    {{-- Room --}}
    <div>
        <x-form.field name="room_id" :label="__('Room')">
            <x-select-input id="room_id" name="room_id" class="block w-full mt-1" required autofocus
                autocomplete="room_id">
                <option value="" disabled @selected(!old('room_id', $training->room_id))>{{ __('Choose a room') }}</option>
                @foreach ($rooms as $room)
                    <option value="{{ $room->id }}" @selected(old('room_id', $training->room_id) == $room->id)>
                        {{ $room->name }}
                    </option>
                @endforeach
            </x-select-input>
        </x-form.field>
    </div>

    {{-- Type --}}
    <div>
        <x-form.field name="type" :label="__('Type')">
            <x-select-input id="type" name="type" class="block w-full mt-1" required autofocus
                autocomplete="type">
                <option value="" disabled @selected(!old('type', $training->type))>{{ __('Choose a type') }}</option>
                @foreach ($types as $type)
                    <option value="{{ $type->name }}" @selected(old('type', $training->type) == $type->name)>
                        {{ $type->value }}
                    </option>
                @endforeach
            </x-select-input>
        </x-form.field>
    </div>

    {{-- Level --}}
    <div>
        <x-form.field name="level" :label="__('Level')">
            <x-select-input id="level" name="level" class="block w-full mt-1" required autofocus
                autocomplete="level">
                <option value="" disabled @selected(!old('level', $training->level))>{{ __('Select the level') }}</option>
                @foreach ($levels as $level)
                    <option value="{{ $level->name }}" @selected(old('level', $training->level) == $level->name)>
                        {{ $level->value }}
                    </option>
                @endforeach
            </x-select-input>
        </x-form.field>
    </div>

    {{-- Trainer name --}}
    <div>
        <x-form.field name="trainer_id" :label="__('Trainer Name')">
            <x-select-input id="trainer_id" name="trainer_id" class="block w-full mt-1" autofocus
                autocomplete="trainer_id">
                <option value="" disabled @selected(!old('trainer_id', $training->trainer_id))>{{ __('Pick up trainer if any') }}</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(old('trainer_id', $training->trainer_id) == $user->id)>
                        {{ $user->last_name }} {{ $user->first_name }}
                    </option>
                @endforeach
            </x-select-input>
        </x-form.field>
    </div>
</div>
