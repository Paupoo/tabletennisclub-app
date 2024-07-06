<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Edit a room') }}
        </h2>
    </x-slot>

    <x-admin-block>
        <div class="flex gap-4">
            <form action="{{ route('dashboard') }}" method="GET">
                <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
            </form>
            <form action="{{ route('rooms.index') }}" method="GET">
                <x-primary-button>{{ __('Manage Rooms') }}</x-primary-button>
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
                            {{ __('Edit \'' . $room->name) . '\'' }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __("Rooms are used for trainings and for competition. Their addresses are also shown on the public website.") }}
                        </p>
                    </header>

                    <form action="{{ route('rooms.update', $room->id) }}" method="POST" class="mt-6 space-y-6">
                        @csrf
                        @method('PUT')

                        {{-- Name --}}
                        <div>
                            <x-input-label for="name" :value="__('Name')" />
                            <x-text-input id="name" name="name" type="text" class="block w-full mt-1"
                                :value="old('name', $room->name)" required autofocus autocomplete="name"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        {{-- Street --}}
                        <div>
                            <x-input-label for="street" :value="__('Street')" />
                            <x-text-input id="street" name="street" type="text" class="block w-full mt-1"
                                :value="old('street', $room->street)" required autofocus autocomplete="street"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('street')" />
                        </div>

                        {{-- City code --}}
                        <div>
                            <x-input-label for="city_code" :value="__('City Code')" />
                            <x-text-input id="city_code" name="city_code" type="text" class="block w-full mt-1"
                                :value="old('city_code', $room->city_code)" required autofocus autocomplete="city_code"   ></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('city_code')" />
                        </div>
                        
                        {{-- City name --}}
                        <div>
                            <x-input-label for="city_name" :value="__('City')" />
                            <x-text-input id="city_name" name="city_name" type="text" class="block w-full mt-1"
                                :value="old('city_name', $room->city_name)" required autofocus autocomplete="city_name"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('city_name')" />
                        </div>
                        
                        {{-- Building/Site name --}}
                        <div>
                            <x-input-label for="building_name" :value="__('Building/Site name')" />
                            <x-text-input id="building_name" name="building_name" type="text" class="block w-full mt-1"
                                :value="old('building_name', $room->building_name)" required autofocus autocomplete="building_name"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('building_name')" />
                        </div>

                        {{-- Access Description --}}
                        <div>
                            <x-input-label for="access_description" :value="__('Access description')" />
                            <x-textarea-input id="access_description" name="access_description" type="text" class="block w-full mt-1"
                                    autofocus>{{ old('access_description', $room->access_description) }}</x-textarea-input>
                            <x-input-error class="mt-2" :messages="$errors->get('access_description')" />
                        </div>
                                                      
                        {{-- Training capacity --}}
                        <div>
                            <x-input-label for="capacity_trainings" :value="__('Training capacity')" />
                            <x-text-input id="capacity_trainings" name="capacity_trainings" type="text" class="block w-full mt-1"
                                :value="old('capacity_trainings', $room->capacity_trainings)" required autofocus autocomplete="capacity_trainings"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('capacity_trainings')" />
                        </div>
                        
                        {{-- Matches capacity --}}
                        <div>
                            <x-input-label for="capacity_matches" :value="__('Matches capacity')" />
                            <x-text-input id="capacity_matches" name="capacity_matches" type="text" class="block w-full mt-1"
                                :value="old('capacity_matches', $room->capacity_matches)" required autofocus autocomplete="capacity_matches"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('capacity_matches')" />
                        </div>

                        <div>
                            <x-primary-button>{{ __('Save change') }}</x-primary-button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>