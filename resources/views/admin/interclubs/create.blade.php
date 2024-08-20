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
                    <x-input-error class="mt-2" :messages="$errors->all()" />

                    <form action="{{ route('interclubs.store') }}" method="POST" class="mt-6 space-y-6">
                        @csrf

                        {{-- Club's Team --}}
                        <div>
                            <x-input-label for="club_team" :value="__('Club\'s team')" />
                            <x-select-input id="club_team" name="club_team" type="text" class="block w-full mt-1"
                                :value="old('club_team')" required autofocus autocomplete="club_team">
                                @foreach ($teams as $team)
                                    <option value="{{ $team->id }}">Otttignies {{ $team->name }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error class="mt-2" :messages="$errors->get('club_team')" />
                        </div>  

                        {{-- Competition Date --}}
                        <div>
                            <x-input-label for="start_date_time" :value="__('Competition Date')" />
                            <x-text-input id="start_date_time" name="start_date_time" type="datetime-local" min="{{ today()->format('Y-m-d\TH:i:s') }}" max="{{ now()->addMonth(6)->format('Y-m-d\TH:i:s') }}" class="block w-full mt-1"
                                :value="old('start_date_time', now()->format('Y-m-d\TH:i'))" required autofocus autocomplete="start_date_time"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('start_date_time')" />
                        </div>

                        {{-- at home? --}}
                        <div>
                            <x-input-label for="address" :value="__('Select a room if the interclub is taking place in the club')" />
                            <x-select-input id="room_id" name="room_id" class="block w-full mt-1">
                                <option value="" selected>{{ __('None') }}</option>
                                @foreach ($rooms as $room)
                                    <option value="{{ $room->id }}">{{ $room->name }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error class="mt-2" :messages="$errors->get('room_id')" />
                         </div>

                        {{-- Competition Address --}}
                        <div>
                            <x-input-label for="address" :value="__('Competition Address (Do not fill if the interclub is taking place at the club)')" />
                            <x-text-input id="address" name="address" type="text" class="block w-full mt-1"
                                :value="old('address')" autofocus autocomplete="address"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('address')" />
                        </div>

                        {{-- Opposing Team --}}
                        <div>
                            <x-input-label for="opposing_team" :value="__('Opposing team')" />
                            <x-text-input id="opposing_team" name="opposing_team" type="text" class="block w-full mt-1"
                                :value="old('opposing_team')" required autofocus autocomplete="opposing_team"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('opposing_team')" />
                        </div>    
                        
                        <div>
                            <x-primary-button>{{ __('Create new competition') }}</x-primary-button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>