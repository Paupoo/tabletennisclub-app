    {{-- resources/views/admin/events/partials/training-fields.blade.php --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Niveau --}}
    <div>
        <label for="training_level" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Training level') }} *
        </label>
        <select id="training_level" 
                name="training_level" 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                required>
            <option value="" disabled selected>{{ __('Select a level') }}</option>
            @foreach(\App\Enums\TrainingLevel::cases() as $level)
                <option value="{{ $level->name }}" {{ old('training_level') === $level->name ? 'selected' : '' }}>
                    {{ __($level->name) }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Type d'entraînement --}}
    <div>
        <label for="training_type" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Training type') }} *
        </label>
        <select id="training_type" 
                name="training_type" 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                required>
            <option value="" disabled selected>{{ __('Select a type') }}</option>
            @foreach(\App\Enums\TrainingType::cases() as $trainingType)
                <option value="{{ $trainingType->name }}" {{ old('training_type') === $trainingType->name ? 'selected' : '' }}>
                    {{ __($trainingType->name) }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Salle --}}
    <div>
        <label for="room_id" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Room') }} *
        </label>
        <select id="room_id" 
                name="room_id" 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                required>
            <option value="" disabled selected>{{ __('Select a room') }}</option>
            @foreach($rooms as $room)
                <option value="{{ $room->id }}" {{ old('room_id') == $room->id ? 'selected' : '' }}>
                    {{ $room->name }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Entraîneur --}}
    <div>
        <label for="trainer_id" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Trainer') }}
        </label>
        <select id="trainer_id" 
                name="trainer_id" 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
            <option value="">{{ __('No trainer assigned') }}</option>
            @foreach($trainers as $trainer)
                <option value="{{ $trainer->id }}" {{ old('trainer_id') == $trainer->id ? 'selected' : '' }}>
                    {{ $trainer->fullName }}
                </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">{{ __('Leave empty if not yet assigned') }}</p>
    </div>

    {{-- Saison --}}
    <div class="lg:col-span-2">
        <label for="season_id" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Season') }} *
        </label>
        <select id="season_id" 
                name="season_id" 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                required>
            <option value="" disabled selected>{{ __('Select a season') }}</option>
            @foreach($seasons as $season)
                <option value="{{ $season->id }}" {{ old('season_id') == $season->id ? 'selected' : '' }}>
                    {{ $season->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="mt-4 p-4 bg-blue-50 rounded-lg">
    <p class="text-sm text-blue-800">
        <strong>ℹ️ {{ __('Note:') }}</strong> 
        {{ __('The start and end times defined above will be used for this training session.') }}
    </p>
</div>