{{-- First Name --}}
<div>
    <x-input-label for="first_name" :value="__('First Name')" />
    <x-text-input id="first_name" name="first_name" type="text" class="block w-full mt-1" :value="old('first_name', $user?->first_name)" required
        autofocus autocomplete="first_name"></x-text-input>
    <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
</div>

{{-- Last Name --}}
<div>
    <x-input-label for="last_name" :value="__('Last Name')" />
    <x-text-input id="last_name" name="last_name" type="text" class="block w-full mt-1" :value="old('last_name', $user?->last_name)" required
        autofocus autocomplete="last_name"></x-text-input>
    <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
</div>

{{-- Gender --}}
<div>
    <x-input-label for="gender" :value="__('Gender')" />
    <x-select-input id="gender" name="gender" class="block w-full mt-1" required autofocus>
        @foreach ($genders as $gender)
            <option value="{{ $gender->value }}" 
                @selected(old('gender', $user->gender?->value) === $gender->value)>
                {{ $gender->getLabel() }}
            </option>
        @endforeach
    </x-select-input>
    <x-input-error class="mt-2" :messages="$errors->get('gender')" />
</div>


{{-- Email --}}
<div>
    <x-input-label for="email" :value="__('Email')" />
    <x-text-input id="email" name="email" type="email" class="block w-full mt-1" :value="old('email', $user?->email)" required
        autofocus autocomplete="email"></x-text-input>
    <x-input-error class="mt-2" :messages="$errors->get('email')" />
</div>

{{-- Password --}}
{{-- @if (!$user->id) --}}
@if('x' === 'y')
    <div>
        <x-input-label for="password" :value="__('Password')" />
        <x-text-input id="password" name="password" type="password" class="block w-full mt-1" :value="old('password')"
            autofocus autocomplete="false"></x-text-input>
        <x-input-error class="mt-2" :messages="$errors->get('password')" />
    </div>


    {{-- Confirm Password --}}
    <div class="mt-4">
        <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

        <x-text-input id="password_confirmation" class="block w-full mt-1" type="password" name="password_confirmation"
            autocomplete="new-password" />

        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
    </div>
@endif

{{-- Phone Number --}}
<div>
    <x-input-label for="phone_number" :value="__('Phone Number')" />
    <x-text-input id="phone_number" name="phone_number" type="text" class="block w-full mt-1"
        placeholder="0470123456" :value="old('phone_number', $user?->phone_number)" autofocus autocomplete="false"></x-text-input>
    <x-input-error class="mt-2" :messages="$errors->get('phone_number')" />
</div>

{{-- Birthday --}}
<div>
    <x-input-label for="birthdate" :value="__('Birth date')" />
    <x-text-input id="birthdate" name="birthdate" type="date" class="block w-full mt-1" :value="old('birthdate', $user?->birthdate?->format('Y-m-d'))" autofocus
        autocomplete="false"></x-text-input>
    <x-input-error class="mt-2" :messages="$errors->get('birthdate')" />
</div>

{{-- Street --}}
<div>
    <x-input-label for="street" :value="__('Street')" />
    <x-text-input id="street" name="street" type="text" class="block w-full mt-1" :value="old('street', $user?->street)"
        placeholder="Rue du pont neuf, 80" autofocus autocomplete="false"></x-text-input>
    <x-input-error class="mt-2" :messages="$errors->get('street')" />
</div>

{{-- City Code --}}
<div>
    <x-input-label for="city_code" :value="__('Post Code')" />
    <x-text-input id="city_code" name="city_code" type="text" class="block w-full mt-1" :value="old('city_code', $user?->city_code)"
        placeholder="1340" autofocus autocomplete="false"></x-text-input>
    <x-input-error class="mt-2" :messages="$errors->get('city_code')" />
</div>

{{-- City Name --}}
<div>
    <x-input-label for="city_name" :value="__('City')" />
    <x-text-input id="city_name" name="city_name" type="text" class="block w-full mt-1" :value="old('city_name', $user?->city_name)"
        placeholder="Rue du pont neuf, 80" autofocus autocomplete="false"></x-text-input>
    <x-input-error class="mt-2" :messages="$errors->get('city_name')" />
</div>

{{-- Competition --}}
<fieldset class="border p-2 space-y-8">
    <legend class="text-lg font-bold">Competition</legend>
    <div>
        <x-input-label for="is_competitor" :value="__('Plays in competiton')" />
        <input id="is_competitor" name="is_competitor" type="checkbox" class="block mt-1" @checked(old('is_competitor', $user?->is_competitor))
            autofocus></input>
        <x-input-error class="mt-2" :messages="$errors->get('is_competitor')" />
    </div>

    {{-- Licence --}}
    <div>
        <x-input-label for="licence" :value="__('Licence')" />
        <x-text-input id="licence" name="licence" type="number" class="block w-full mt-1" :min="1"
            :max="999999" :value="old('licence', $user?->licence)" autofocus autocomplete="licence"></x-text-input>
        <x-input-error class="mt-2" :messages="$errors->get('licence')" />
    </div>

    {{-- Ranking --}}
    <div>
        <x-input-label for="ranking" :value="__('Ranking')" />
        <x-select-input id="ranking" name="ranking" class="block w-full mt-1" autofocus autocomplete="ranking"
            required>
            @foreach ($rankings as $item)
                <option value="{{ $item }}" @selected(old('ranking', $user?->ranking) === $item)>{{ $item }}</option>
            @endforeach
        </x-select-input>
        <x-input-error class="mt-2" :messages="$errors->get('ranking')" />
    </div>

    {{-- Team --}}
    <div>
        <x-input-label for="team_id" :value="__('Team')" />
        <x-select-input id="team_id" name="team_id" type="text" class="block w-full mt-1" autofocus
            autocomplete="team_id">
            <option value="" selected>{{ __('None') }}</option>

            @foreach ($teams as $team)
                <option value="{{ $team->id }}" @selected(old('team_id', $user?->team_id) === (string) $team->id)>
                    {{ $team?->season?->name . ' | ' . $team->league?->level->getLabel() . ' | ' . $team?->league->division . ' | ' . $team->name }}
                </option>
            @endforeach
        </x-select-input>
        <x-input-error class="mt-2" :messages="$errors->get('team_id')" />
    </div>
</fieldset>

{{-- Role --}}
<div class="flex gap-4">
    <div class="flex gap-4">
        <x-input-label for="is_admin" :value="__('Is active')" />
        <input id="is_active" name="is_active" type="checkbox" class="block mt-1" @checked(old('is_active', $user?->is_active))
            autofocus></input>
        <x-input-error class="mt-2" :messages="$errors->get('is_active')" />
    </div>
    <div class="flex gap-4">
        <x-input-label for="is_admin" :value="__('Is admin')" />
        <input id="is_admin" name="is_admin" type="checkbox" class="block mt-1" @checked(old('is_admin', $user?->is_admin))
            autofocus></input>
        <x-input-error class="mt-2" :messages="$errors->get('is_admin')" />
    </div>
    <div class="flex gap-4">
        <x-input-label for="is_committee_member" :value="__('Is committee member')" />
        <input id="is_committee_member" name="is_committee_member" type="checkbox" class="block mt-1"
            @checked(old('is_committee_member', $user?->is_committee_member)) autofocus></input>
        <x-input-error class="mt-2" :messages="$errors->get('is_committee_member')" />
    </div>
</div>
