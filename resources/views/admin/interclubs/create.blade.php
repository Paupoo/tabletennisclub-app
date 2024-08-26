<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Create a competition') }}
        </h2>
    </x-slot>

    <x-admin-block>
        <div class="flex gap-4">
            <form action="{{ route('dashboard') }}" method="GET">
                <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
            </form>
            <form action="{{ route('interclubs.index') }}" method="GET">
                <x-primary-button>{{ __('Manage competitiones') }}</x-primary-button>
            </form>
        </div>
    </x-admin-block>



    <div class="pt-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                @if (session('success'))
                    <x-notification-success>{{ session('success') }}</x-notification-success>
                @endif
                <div class="w-full p-6 text-gray-900 dark:text-gray-100 lg:w-1/2">
                    <header>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('Create a new competition') }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('Create a new competition from this form.') }}
                        </p>
                    </header>


                    <form action="{{ route('interclubs.store') }}" method="POST" class="mt-6 space-y-6">
                        @csrf

                        {{-- Season picker --}}
                        <fieldset class="border border-gray-400 rounded-md p-2 bg-gray-50">
                            <legend class="bg-gray-700 text-gray-50 rounded-t-lg px-2">{{ __('Select a season') }}
                            </legend>
                            <x-input-label for="" :value="__('Select a season.')" />
                            <x-select-input id="" name="" class="block w-full mt-1" disabled>
                                <option value="" selected>{{ __('To do') }}</option>
                                
                            </x-select-input>
                            <x-input-error class="mt-2" :messages="$errors->get('')" />
                        </fieldset>

                        <fieldset class="border border-gray-400 rounded-md p-2 bg-gray-50 space-y-2">
                            <legend class="bg-gray-700 text-gray-50 rounded-t-lg px-2">{{ __('Interclub details') }}
                            </legend>

                            <div>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_visited" value="1" class="sr-only peer" @checked(old('is_visited')) autofocus>
                                    <div
                                        class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600">
                                    </div>
                                    <span
                                        class="ms-3 text-sm font-medium text-gray-900 dark:text-gray-300">{{ __('Playing Home ?') }}</span>
                                </label>
                                <x-input-error class="mt-2" :messages="$errors->get('is_visited')" />

                            </div>

                            {{-- Club's Team --}}
                            <div>
                                <x-input-label for="team_id" :value="__('Club\'s team')" />
                                <x-select-input id="team_id" name="team_id" type="text" class="block w-full mt-1"
                                    required autofocus autocomplete="team_id">
                                    <option value="" selected disabled>Select a team</option>
                                    @foreach ($teams as $team)
                                        <option value="{{ $team->id }}" @selected(old('team_id') == $team->id)>Ottignies
                                            {{ $team->name }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error class="mt-2" :messages="$errors->get('team_id')" />
                            </div>

                            {{-- Competition Date --}}
                            <div>
                                <x-input-label for="start_date_time" :value="__('Competition Date')" />
                                <x-text-input id="start_date_time" name="start_date_time" type="datetime-local"
                                    min="{{ today()->format('Y-m-d\TH:i:s') }}"
                                    max="{{ now()->addMonth(12)->format('Y-m-d\TH:i:s') }}" class="block w-full mt-1"
                                    :value="old('start_date_time', now()->format('Y-m-d\TH:i'))" required autofocus autocomplete="start_date_time"></x-text-input>
                                <x-input-error class="mt-2" :messages="$errors->get('start_date_time')" />
                            </div>

                            {{-- Opposite Club --}}
                            <div>
                                <x-input-label for="opposite_club_id" :value="__('Select the other club')" />
                                <x-select-input id="opposite_club_id" name="opposite_club_id" class="block w-full mt-1">
                                    <option value="" selected disabled>{{ __('None') }}</option>
                                    @foreach ($otherClubs as $club)
                                        <option value="{{ $club->id }}" @selected(old('opposite_club_id') == $club->id)>
                                            {{ $club->name }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error class="mt-2" :messages="$errors->get('opposite_club_id')" />
                            </div>

                            {{-- Opposite team name --}}
                            <div>
                                <x-input-label for="opposite_team_name" :value="__('Team name (A, B, C...)')" />
                                <x-text-input id="opposite_team_name" name="opposite_team_name" value="{{ old('opposite_team_name')}}"
                                    class="block w-full mt-1" pattern="^[a-zA-Z]{1}$" />
                                <x-input-error class="mt-2" :messages="$errors->get('opposite_team_name')" />
                            </div>
                        </fieldset>

                        {{-- at home? --}}
                        <fieldset class="border border-gray-400 rounded-md p-2 bg-gray-50">
                            <legend class="bg-gray-700 text-gray-50 rounded-t-lg px-2">{{ __('Playing Home?') }}
                            </legend>
                            <x-input-label for="room_id" :value="__('Select a room if the interclub is taking place in the club')" />
                            <x-select-input id="room_id" name="room_id" class="block w-full mt-1">
                                <option value="" selected>{{ __('None') }}</option>
                                @foreach ($rooms as $room)
                                    <option value="{{ $room->id }}" @selected(old('room_id') == $room->id)>
                                        {{ $room->name }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error class="mt-2" :messages="$errors->get('room_id')" />
                        </fieldset>
                        <div>
                            <x-primary-button>{{ __('Create new competition') }}</x-primary-button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
