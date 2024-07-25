@props([
    'team' => new App\Models\Team(),
    'team_names' => [],
    'leagues' => [],
    'users' => [],
    'attachedUsers' => [],
])

{{-- League --}}
<div>
    <x-input-label for="league_id" :value="__('Select a league')" />
    <x-select-input id="league_id" name="league_id" class="block w-full mt-1" :value="old('league_id')" required autofocus>
        <option value="" disabled @selected(old('name') === null)>{{ __('Select a league') }}</option>
        @foreach ($leagues as $league)
            <option value="{{ $league->id }}" @selected(old('name', $team->league?->id) === $league->id)>
                {{ $league->start_year }}-{{ $league->end_year }} | {{ $league->level }}
                | {{ $league->category }} | {{ $league->division }}</option>
        @endforeach

    </x-select-input>
    <x-input-error class="mt-2" :messages="$errors->get('league_id')" />
</div>

{{-- Letter --}}
<div>
    <x-input-label for="name" :value="__('Select a letter')" />
    <x-select-input id="name" name="name" type="text" class="block w-full mt-1" required autofocus
        autocomplete="name">
        <option value="" disabled @selected(old('name') === null)>{{ __('Select a team') }}</option>
        @foreach ($team_names as $team_name)
            <option value="{{ $team_name->name }}" @selected(old('name', $team?->name) === $team_name->name)>{{ $team_name->name }}</option>
        @endforeach
    </x-select-input>
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

{{-- Players --}}
<div>
    <h3 class="mb-6">{{ __('Select players') }}</h3>

    <table class="table-auto w-fit border border-collapse p-4">
        <thead class="table-header-group">
            <tr class="table-row">
                <th class="table-cell text-left w-64">{{ __('Players') }} (#{{ $team->users->count() }})</th>
                <th class="table-cell w-40">{{ __('Add/Remove') }}</th>
                <th class="table-cell w-28">{{ __('Is captain') }}</th>
            </tr>
        </thead>
        <tbody class="table-row-group">
            @if (count($users) === 0)

                <tr>
                    <td>{{ __('There are no competitor without team.') }}</td>
                </tr>
            @else
                @foreach ($users as $user)
                
                    <tr class="table-row hover:bg-gray-100">
                        <td class="table-cell text-left">
                            <x-input-label for="test" :value="$user->ranking . ' | ' . $user->first_name . ' ' . $user->last_name" />
                        </td>
                        <td class="table-cell text-right w-fit">
                            <x-checkbox-input id="player{{ $loop->iteration }}" name="players[]"
                                :value="$user->id" :checked="in_array($user->id, $attachedUsers)"></x-checkbox-input>
                            <x-input-error class="mt-2" :messages="$errors->get('players.' . $loop->index)" />

                        </td>
                        <td class="table-cell text-right w-fit">
                            <x-radio-input name="captain_id" :value="$user->id" :checked="$user->id === $team->captain_id"></x-checkbox-input>
                        </td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</div>
