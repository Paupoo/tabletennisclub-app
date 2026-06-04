{{-- First Name --}}
<div>
    <x-form.field name="first_name" :label="__('First Name')">
        <x-text-input id="first_name" name="first_name" type="text" class="block w-full mt-1" :value="old('first_name', $user?->first_name)" required
            autofocus autocomplete="first_name" />
    </x-form.field>
</div>

{{-- Last Name --}}
<div>
    <x-form.field name="last_name" :label="__('Last Name')">
        <x-text-input id="last_name" name="last_name" type="text" class="block w-full mt-1" :value="old('last_name', $user?->last_name)" required
            autofocus autocomplete="last_name" />
    </x-form.field>
</div>

{{-- Gender --}}
<div>
    <x-form.field name="gender" :label="__('Gender')">
        <x-select-input id="gender" name="gender" class="block w-full mt-1" required autofocus>
            @foreach ($sexes as $sex)
                <option value="{{ $sex }}" @selected(old('gender', $user?->sex) === $sex)>{{ $sex }}</option>
            @endforeach
        </x-select-input>
    </x-form.field>
</div>

{{-- Email --}}
<div>
    <x-form.field name="email" :label="__('Email')">
        <x-text-input id="email" name="email" type="email" class="block w-full mt-1" :value="old('email', $user?->email)" required
            autofocus autocomplete="email" />
    </x-form.field>
</div>

{{-- Phone Number --}}
<div>
    <x-form.field name="phone_number" :label="__('Phone Number')">
        <x-text-input id="phone_number" name="phone_number" type="text" class="block w-full mt-1"
            placeholder="0470123456" :value="old('phone_number', $user?->phone_number)" autofocus autocomplete="false" />
    </x-form.field>
</div>

{{-- Birthday --}}
<div>
    <x-form.field name="birthdate" :label="__('Birth date')">
        <x-text-input id="birthdate" name="birthdate" type="date" class="block w-full mt-1" :value="old('birthdate', $user?->birthdate?->format('Y-m-d'))" autofocus
            autocomplete="false" />
    </x-form.field>
</div>

{{-- Street --}}
<div>
    <x-form.field name="street" :label="__('Street')">
        <x-text-input id="street" name="street" type="text" class="block w-full mt-1" :value="old('street', $user?->street)"
            placeholder="Rue du pont neuf, 80" autofocus autocomplete="false" />
    </x-form.field>
</div>

{{-- City Code --}}
<div>
    <x-form.field name="city_code" :label="__('Post Code')">
        <x-text-input id="city_code" name="city_code" type="text" class="block w-full mt-1" :value="old('city_code', $user?->city_code)"
            placeholder="1340" autofocus autocomplete="false" />
    </x-form.field>
</div>

{{-- City Name --}}
<div>
    <x-form.field name="city_name" :label="__('City')">
        <x-text-input id="city_name" name="city_name" type="text" class="block w-full mt-1" :value="old('city_name', $user?->city_name)"
            placeholder="Rue du pont neuf, 80" autofocus autocomplete="false" />
    </x-form.field>
</div>

{{-- Competition --}}
<fieldset class="border p-2 space-y-8">
    <legend class="text-lg font-bold">Competition</legend>
    <div>
        <x-form.field name="is_competitor" :label="__('Plays in competiton')">
            <input id="is_competitor" name="is_competitor" type="checkbox" class="block mt-1" @checked(old('is_competitor', $user?->is_competitor))
                autofocus />
        </x-form.field>
    </div>

    {{-- Licence --}}
    <div>
        <x-form.field name="licence" :label="__('Licence')">
            <x-text-input id="licence" name="licence" type="number" class="block w-full mt-1" :min="1"
                :max="999999" :value="old('licence', $user?->licence)" autofocus autocomplete="licence" />
        </x-form.field>
    </div>

    {{-- Ranking --}}
    <div>
        <x-form.field name="ranking" :label="__('Ranking')">
            <x-select-input id="ranking" name="ranking" class="block w-full mt-1" autofocus autocomplete="ranking" required>
                @foreach ($rankings as $item)
                    <option value="{{ $item }}" @selected(old('ranking', $user?->ranking) === $item)>{{ $item }}</option>
                @endforeach
            </x-select-input>
        </x-form.field>
    </div>

    {{-- Team --}}
    <div>
        <x-form.field name="team_id" :label="__('Team')">
            <x-select-input id="team_id" name="team_id" type="text" class="block w-full mt-1" autofocus
                autocomplete="team_id">
                <option value="" selected>{{ __('None') }}</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}" @selected(old('team_id', $user?->team_id) === (string) $team->id)>
                        {{ $team?->season?->name . ' | ' . $team->league?->level . ' | ' . $team?->league->division . ' | ' . $team->name }}
                    </option>
                @endforeach
            </x-select-input>
        </x-form.field>
    </div>
</fieldset>

{{-- Role --}}
<div class="flex gap-4">
    <div class="flex gap-4">
        <x-form.field name="is_active" :label="__('Is active')">
            <input id="is_active" name="is_active" type="checkbox" class="block mt-1" @checked(old('is_active', $user?->is_active))
                autofocus />
        </x-form.field>
    </div>
    <div class="flex gap-4">
        <x-form.field name="is_admin" :label="__('Is admin')">
            <input id="is_admin" name="is_admin" type="checkbox" class="block mt-1" @checked(old('is_admin', $user?->is_admin))
                autofocus />
        </x-form.field>
    </div>
    <div class="flex gap-4">
        <x-form.field name="is_committee_member" :label="__('Is committee member')">
            <input id="is_committee_member" name="is_committee_member" type="checkbox" class="block mt-1"
                @checked(old('is_committee_member', $user?->is_committee_member)) autofocus />
        </x-form.field>
    </div>
</div>
