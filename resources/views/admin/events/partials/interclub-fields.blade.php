{{-- resources/views/admin/events/partials/interclub-fields.blade.php --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Match à domicile ou extérieur --}}
    <div class="lg:col-span-2 p-4 bg-gray-50 rounded-lg">
        <label class="inline-flex items-center cursor-pointer">
            <input type="checkbox" 
                   name="is_home" 
                   value="1" 
                   class="sr-only peer" 
                   {{ old('is_home') ? 'checked' : '' }}
                   x-model="isHome"
                   @change="onHomeChange()">
            <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-hidden peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:rtl:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600">
            </div>
            <span class="ms-3 text-sm font-medium text-gray-900">
                {{ __('Playing at home?') }}
            </span>
        </label>
        <p class="text-xs text-gray-500 mt-2">
            {{ __('Check this if the match is taking place in your club') }}
        </p>
    </div>

    {{-- Salle (uniquement si à domicile) --}}
    <div class="lg:col-span-2" x-show="isHome" x-transition>
        <label for="interclub_room_id" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Room') }} *
        </label>
        <select id="interclub_room_id" 
                name="interclub_room_id" 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                :required="isHome">
            <option value="">{{ __('Select a room') }}</option>
            @foreach($rooms as $room)
                <option value="{{ $room->id }}" {{ old('interclub_room_id') == $room->id ? 'selected' : '' }}>
                    {{ $room->name }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Adresse complète (uniquement si à l'extérieur) --}}
    <div class="lg:col-span-2" x-show="!isHome" x-transition>
        <label for="interclub_address" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Full address') }} *
        </label>
        <input type="text" 
               id="interclub_address" 
               name="interclub_address" 
               value="{{ old('interclub_address') }}"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
               placeholder="{{ __('Street, number, postal code, city') }}"
               :required="!isHome">
    </div>

    {{-- Notre équipe --}}
    <div>
        <label for="visited_team_id" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Our team') }} *
        </label>
        <select id="visited_team_id" 
                name="visited_team_id" 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                required>
            <option value="" disabled selected>{{ __('Select a team') }}</option>
            @foreach($teams as $team)
                <option value="{{ $team->id }}" {{ old('visited_team_id') == $team->id ? 'selected' : '' }}>
                    {{ $team->name }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Club adverse --}}
    <div>
        <label for="opposite_club_id" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Opposing club') }} *
        </label>
        <select id="opposite_club_id" 
                name="opposite_club_id" 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                required>
            <option value="" disabled selected>{{ __('Select a club') }}</option>
            @foreach($otherClubs as $club)
                <option value="{{ $club->id }}" {{ old('opposite_club_id') == $club->id ? 'selected' : '' }}>
                    {{ $club->name }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Équipe adverse --}}
    <div>
        <label for="visiting_team_id" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Opposing team') }}
        </label>
        <select id="visiting_team_id" 
                name="visiting_team_id" 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
            <option value="">{{ __('Not yet defined') }}</option>
            {{-- Les équipes adverses seront chargées dynamiquement selon le club sélectionné --}}
        </select>
        <p class="text-xs text-gray-500 mt-1">{{ __('Optional: can be filled later') }}</p>
    </div>

    {{-- Nom d'équipe adverse (A, B, C...) --}}
    <div>
        <label for="opposite_team_name" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Opposing team name (A, B, C...)') }}
        </label>
        <input type="text" 
               id="opposite_team_name" 
               name="opposite_team_name" 
               value="{{ old('opposite_team_name') }}"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
               pattern="^[a-zA-Z]{1}$"
               maxlength="1"
               placeholder="A">
        <p class="text-xs text-gray-500 mt-1">{{ __('Single letter (A, B, C, etc.)') }}</p>
    </div>

    {{-- Nombre total de joueurs --}}
    <div>
        <label for="total_players" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Total number of players') }} *
        </label>
        <input type="number" 
               id="total_players" 
               name="total_players" 
               value="{{ old('total_players', 4) }}"
               min="1"
               max="20"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
               required>
        <p class="text-xs text-gray-500 mt-1">{{ __('Usually 4 players per team') }}</p>
    </div>

    {{-- Numéro de semaine --}}
    <div>
        <label for="week_number" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Week number') }}
        </label>
        <input type="number" 
               id="week_number" 
               name="week_number" 
               value="{{ old('week_number') }}"
               min="1"
               max="52"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
               placeholder="1">
        <p class="text-xs text-gray-500 mt-1">{{ __('Championship week (1, 2, 3...)') }}</p>
    </div>

    {{-- Ligue --}}
    <div>
        <label for="league_id" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('League') }}
        </label>
        <select id="league_id" 
                name="league_id" 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
            <option value="">{{ __('Not specified') }}</option>
            @foreach($leagues as $league)
                <option value="{{ $league->id }}" {{ old('league_id') == $league->id ? 'selected' : '' }}>
                    {{ $league->name }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Saison --}}
    <div>
        <label for="interclub_season_id" class="block text-sm font-medium text-gray-700 mb-2">
            {{ __('Season') }} *
        </label>
        <select id="interclub_season_id" 
                name="interclub_season_id" 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                required>
            <option value="" disabled selected>{{ __('Select a season') }}</option>
            @foreach($seasons as $season)
                <option value="{{ $season->id }}" {{ old('interclub_season_id') == $season->id ? 'selected' : '' }}>
                    {{ $season->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="mt-4 p-4 bg-blue-50 rounded-lg">
    <p class="text-sm text-blue-800">
        <strong>ℹ️ {{ __('Note:') }}</strong> 
        {{ __('The score and result can be filled in after the match via the edit form.') }}
    </p>
</div>