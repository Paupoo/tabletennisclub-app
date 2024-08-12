@props([
    'seasons' => [],
    'league_categories' => [],
    'league_levels' => [],
    'users' => [],
    'team' => new App\Models\Team(),
    'team_names' => [],
    'attachedUsers' => [],
])
<div class="flex flew-row flex-wrap gap-2">

    <div>
        <x-input-label for="season" :value="__('Select a season')" />
        <x-select-input class="block w-fit mt-1" id="season" name="season_id" required autofocus>
            @foreach ($seasons as $season)
            <option value="{{ $season->id }}" @selected(today()->format('Y') >= $season->end_year)>{{ $season->name }}</option>
            @endforeach
        </x-select-input>
        <x-input-error class="mt-2" :messages="$errors->get('season')" />
    </div>
    
    <div>
        <x-input-label for="category" :value="__('Select a category')" />
        <x-select-input class="block w-fit mt-1" id="category" name="category" required autofocus>
            @foreach ($league_categories as $category)
            <option value="{{ $category->name }}" @selected($category->name === $team->league?->category)>{{ $category->value }}</option>
            @endforeach
        </x-select-input>
        <x-input-error class="mt-2" :messages="$errors->get('category')" />
    </div>
    
    <div>
        <x-input-label for="level" :value="__('Select a level')" />
        <x-select-input class="block w-fit mt-1" id="level" name="level" required autofocus>
            @foreach ($league_levels as $level)
            <option value="{{ $level->name }}">{{ $level->value }}</option>
            @endforeach
        </x-select-input>
        <x-input-error class="mt-2" :messages="$errors->get('level')" />
    </div>
    
    <div>
        <x-input-label for="division" :value="__('Division')" />
        <x-text-input class="block w-fit mt-1" id="division" name="division" placeholder="5E" value="{{ old('division', $team->league?->division) }}" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('division')" />
    </div>
</div>

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

    <x-input-error class="my-2" :messages="$errors->get('players')" />

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
