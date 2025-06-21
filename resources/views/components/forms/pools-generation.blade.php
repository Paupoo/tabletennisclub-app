<form action="{{ route('tournaments.generate-pools', $tournament) }}" method="POST">
    @csrf
    <div class="mb-4 w-full max-w-2xl">
        <p class="mt-2 mb-4 text-sm text-gray-500">
            {{ __('Players will be distributed according to their ranking. Note that you can only create pools once the tournament is locked.') }}
        </p>

        {{-- Choosing what to configure --}}
        <x-select-input class="mb-2 w-full flex flex-row align-bottom"
                :disabled="$tournament->status === 'draft'">
            <option selected disabled>{{ __('Please select how you want to create your pools') }}</option>
            <option >{{ __('I want to specify the number of pools manually') }}</option>
            <option >{{ __('I want to guarantee each player to play a specific number of matches') }}</option>
            <option >{{ __('I want as many pool as there are tables') }}</option>
        </x-select-input>


        {{-- <label for="number_of_pools" class="block text-sm font-medium text-gray-700 mb-2">Nombre de pools à créer
            :</label>
        <div class="relative">
            <x-select-input name="number_of_pools" id="number_of_pools"
                class="block w-full appearance-none px-3 py-2 pr-8 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-blue-500 focus:border-blue-500 text-gray-500"
                >
                @for ($i = 2; $i <= 8; $i++)
                    <option value="{{ $i }}">{{ $i }} pools
                    </option>
                @endfor
            </x-select-input>
            <input type="hidden" name="minMatches" value=0>
        </div> --}}
    </div>

    <x-primary-button type="submit" :disabled="$tournament->status === 'draft'">
        {{ __('Generate Pools') }}
    </x-primary-button>
</form>
{{-- <hr class="text-gray-500 opacity-50 my-4">

<form action="{{ route('tournaments.generate-pools', $tournament) }}" method="POST">
    @csrf
    <div class="mb-4 w-full max-w-2xl">
        <label for="number_of_pools" class="block text-sm font-medium text-gray-700 mb-2">Nombre
            minimum de matches joués&nbsp;:</label>
        <div class="relative">
            <select name="number_of_pools" id="number_of_pools"
                class="block w-full appearance-none px-3 py-2 pr-8 border border-gray-300 rounded-md shadow-xs focus:outline-hidden focus:ring-blue-500 focus:border-blue-500 text-gray-500">
                @for ($i = 2; $i <= 8; $i++)
                    <option value="{{ $i }}">{{ $i }} matches
                    </option>
                @endfor
            </select>
            <input type="hidden" name="minMatches" value=1>
        </div>
    </div>
    <x-primary-button type="submit"
        class="w-full md:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md transition duration-200">
        {{ __('Generate pools') }}
    </x-primary-button>
</form> --}}
