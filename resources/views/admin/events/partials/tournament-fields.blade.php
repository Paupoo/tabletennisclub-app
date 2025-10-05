{{-- resources/views/admin/events/partials/tournament-fields.blade.php --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Date de début spécifique (peut différer de event_date) --}}
    <div>
        <label for="tournament_start_date" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Tournament start date') }}
        </label>
        <input type="datetime-local" 
               id="tournament_start_date" 
               name="tournament_start_date" 
               value="{{ old('tournament_start_date') }}"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
        <p class="text-xs text-gray-500 mt-1">{{ __('Leave empty to use the event date and time') }}</p>
    </div>

    {{-- Date de fin --}}
    <div>
        <label for="tournament_end_date" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Tournament end date') }}
        </label>
        <input type="datetime-local" 
               id="tournament_end_date" 
               name="tournament_end_date" 
               value="{{ old('tournament_end_date') }}"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
        <p class="text-xs text-gray-500 mt-1">{{ __('For multi-day tournaments') }}</p>
    </div>

    {{-- Nombre maximum de participants --}}
    <div>
        <label for="tournament_max_users" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Maximum participants') }} *
        </label>
        <input type="number" 
               id="tournament_max_users" 
               name="tournament_max_users" 
               value="{{ old('tournament_max_users', 32) }}"
               min="2"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
               required>
        <p class="text-xs text-gray-500 mt-1">{{ __('Recommended: 8, 16, 32, 64 for brackets') }}</p>
    </div>

    {{-- Prix d'inscription --}}
    <div>
        <label for="tournament_price" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Registration price') }} *
        </label>
        <div class="relative">
            <input type="number" 
                   id="tournament_price" 
                   name="tournament_price" 
                   value="{{ old('tournament_price', 0) }}"
                   min="0"
                   step="0.01"
                   class="w-full px-4 py-2 pr-8 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                   required>
            <span class="absolute right-3 top-2.5 text-gray-500">€</span>
        </div>
        <p class="text-xs text-gray-500 mt-1">{{ __('Enter 0 if free') }}</p>
    </div>

    {{-- Statut du tournoi --}}
    <div>
        <label for="tournament_status" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Tournament status') }} *
        </label>
        <select id="tournament_status" 
                name="tournament_status" 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                required>
            @foreach(\App\Enums\TournamentStatusEnum::cases() as $status)
                <option value="{{ $status->value }}" {{ old('tournament_status', 'DRAFT') === $status->value ? 'selected' : '' }}>
                    {{ __($status->value) }}
                </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">{{ __('Independent from the general event status') }}</p>
    </div>

    {{-- Points de handicap --}}
    <div>
        <label class="flex items-center space-x-3 cursor-pointer p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
            <input type="checkbox" 
                   name="has_handicap_points" 
                   value="1"
                   {{ old('has_handicap_points') ? 'checked' : '' }}
                   class="rounded border-gray-300 text-club-blue focus:ring-club-blue w-5 h-5">
            <div>
                <span class="text-sm font-medium text-gray-700">
                    {{ __('Enable handicap points') }}
                </span>
                <p class="text-xs text-gray-500 mt-1">
                    {{ __('Players will receive handicap points based on their level') }}
                </p>
            </div>
        </label>
    </div>
</div>

<div class="mt-6 p-4 bg-blue-50 rounded-lg">
    <h4 class="text-sm font-semibold text-blue-900 mb-2">{{ __('Tournament information') }}</h4>
    <ul class="text-sm text-blue-800 space-y-1 list-disc list-inside">
        <li>{{ __('Participants will be able to register via the public page') }}</li>
        <li>{{ __('The current number of participants will be updated automatically') }}</li>
        <li>{{ __('You can manage brackets and results later') }}</li>
    </ul>
</div>