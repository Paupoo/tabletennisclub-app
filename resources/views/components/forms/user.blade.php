@props([
    'member' => new App\Models\User(),
    'rankings' => [],
    'teams' => [],
    ])

    
{{-- First Name --}}
<div>
    <x-input-label for="first_name" :value="__('First Name')" />
    <x-text-input id="first_name" name="first_name" type="text" class="block w-full mt-1"
        :value="old('first_name', $member->first_name)" required autofocus autocomplete="first_name"></x-text-input>
    <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
</div>

{{-- Last Name --}}
<div>
    <x-input-label for="last_name" :value="__('Last Name')" />
    <x-text-input id="last_name" name="last_name" type="text" class="block w-full mt-1"
        :value="old('last_name', $member->last_name)" required autofocus autocomplete="last_name"></x-text-input>
    <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
</div>

{{-- Email --}}
<div>
    <x-input-label for="email" :value="__('Email')" />
    <x-text-input id="email" name="email" type="email" class="block w-full mt-1"
        :value="old('email', $member->email)" required autofocus autocomplete="email"></x-text-input>
    <x-input-error class="mt-2" :messages="$errors->get('email')" />
</div>

{{-- Password --}}
<div>
    <x-input-label for="password" :value="__('Password')" />
    <x-text-input id="password" name="password" type="password" class="block w-full mt-1"
        :value="old('password')" autofocus autocomplete="false"></x-text-input>
    <x-input-error class="mt-2" :messages="$errors->get('password')" />
</div>

{{-- Confirm Password --}}
<div class="mt-4">
    <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

    <x-text-input id="password_confirmation" class="block w-full mt-1" type="password"
        name="password_confirmation" autocomplete="new-password" />

    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
</div>

{{-- Competition --}}
<div>
    <x-input-label for="is_competitor" :value="__('Plays in competiton')" />
    <input id="is_competitor" name="is_competitor" type="checkbox" class="block mt-1"
        @checked(old('is_competitor', $member->is_competitor)) autofocus></input>
    <x-input-error class="mt-2" :messages="$errors->get('is_competitor')" />
</div>

{{-- Licence --}}
<div>
    <x-input-label for="licence" :value="__('Licence')" />
    <x-text-input id="licence" name="licence" type="number" class="block w-full mt-1"
        :min="1" :max="999999" :value="old('licence', $member->licence)" autofocus
        autocomplete="licence"></x-text-input>
    <x-input-error class="mt-2" :messages="$errors->get('licence')" />
</div>

{{-- Ranking --}}
<div>
    <x-input-label for="ranking" :value="__('Ranking')" />
    <x-select-input id="ranking" name="ranking" class="block w-full mt-1"
        autofocus autocomplete="ranking" required>
        @foreach ($rankings as $item)
            <option value="{{ $item }}" @selected(old('ranking', $member->ranking?->value) === $item)>{{ $item }}</option>
        @endforeach
    </x-select-input>
    <x-input-error class="mt-2" :messages="$errors->get('ranking')" />
</div>

{{-- Team --}}
<div>
    <x-input-label for="team_id" :value="__('Team')" />
    <x-select-input id="team_id" name="team_id" type="text" class="block w-full mt-1"
         autofocus autocomplete="team_id">
        <option value="" selected>{{ __('None') }}</option>

        @foreach ($teams as $team)
        <option value="{{ $team->id }}" @selected(old('team_id', $member->team_id) === (string) $team->id)>
            {{ $team?->league?->start_year . ' - ' . $team?->league?->end_year . ' | ' . $team->name . ' | ' . $team?->league?->division }}
    </option>
        @endforeach
    </x-select-input>
    <x-input-error class="mt-2" :messages="$errors->get('team_id')" />
</div>

{{-- Role --}}
<div class="flex gap-4">
    <div class="flex gap-4">
        <x-input-label for="is_admin" :value="__('Is active')" />
        <input id="is_active" name="is_active" type="checkbox" class="block mt-1"
            @checked(old('is_active', $member->is_active)) autofocus></input>
        <x-input-error class="mt-2" :messages="$errors->get('is_active')" />
    </div>
    <div class="flex gap-4">
        <x-input-label for="is_admin" :value="__('Is admin')" />
        <input id="is_admin" name="is_admin" type="checkbox" class="block mt-1"
            @checked(old('is_admin', $member->is_admin)) autofocus></input>
        <x-input-error class="mt-2" :messages="$errors->get('is_admin')" />
    </div>
    <div class="flex gap-4">
        <x-input-label for="is_comittee_member" :value="__('Is comittee member')" />
        <input id="is_comittee_member" name="is_comittee_member" type="checkbox" class="block mt-1"
            @checked(old('is_comittee_member', $member->is_comittee_member)) autofocus></input>
        <x-input-error class="mt-2" :messages="$errors->get('is_comittee_member')" />
    </div>
</div>