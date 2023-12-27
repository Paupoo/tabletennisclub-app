<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Create a training') }}
        </h2>
    </x-slot>

    <x-admin-block>
        <div class="flex gap-4">
            <form action="{{ route('dashboard') }}" method="GET">
                <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
            </form>
            <form action="{{ route('trainings.index') }}" method="GET">
                <x-primary-button>{{ __('Manage Trainings') }}</x-primary-button>
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
                            {{ __('Create training') }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('Here you can create training sessions.') }}
                        </p>
                    </header>

                    <form action="/test2" method="GET">
                    <input type="date" name="start">
                    <input type="date" name="end">
                    <input type="number" name="day">
                    <input type="submit">
                    </form>

                    <form action="{{ route('trainings.store') }}" method="POST" class="mt-6 space-y-6">
                        @csrf

                        {{-- Start --}}
                        <div>
                            <x-input-label for="start" :value="__('Start')" />
                            <x-text-input id="start" name="start" type="datetime-local" class="block w-full mt-1"
                                :value="old('start')" required autofocus autocomplete="start"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('start')" />
                        </div>

                        {{-- End --}}
                        <div>
                            <x-input-label for="end" :value="__('End')" />
                            <x-text-input id="end" name="end" type="datetime-local" class="block w-full mt-1"
                                :value="old('end')" required autofocus autocomplete="end"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('end')" />
                        </div>

                        {{-- Room --}}
                        <div>
                            <x-input-label for="room_id" :value="__('Room')" />
                            <x-select-input id="room_id" name="room_id" type="select"
                                class="block w-full mt-1" :value="old('room_id')" required autofocus autocomplete="room_id">
                                @foreach ($rooms as $room)
                                    <option value="{{ $room->id }}">{{ $room->name }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error class="mt-2" :messages="$errors->get('room_id')" />
                        </div>

                        {{-- Type --}}
                        <div>
                            <x-input-label for="type" :value="__('Type')" />
                            <x-select-input id="type" name="type" type="text" class="block w-full mt-1"
                                :value="old('type')" required autofocus autocomplete="type">
                                <option value="Directed">{{ __('Directed') }}</option>
                                <option value="Free">{{ __('Free') }}</option>
                                <option value="Supervised">{{ __('Supervised') }}</option>
                            </x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('type')" />
                        </div>

                        {{-- Level --}}
                        <div>
                            <x-input-label for="level" :value="__('Level')" />
                            <x-text-input id="level" name="level" type="text" class="block w-full mt-1"
                                :value="old('level')" required autofocus autocomplete="level"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('level')" />
                        </div>

                        {{-- Trainer name --}}
                        <div>
                            <x-input-label for="trainer_name" :value="__('Trainer Name')" />
                            <x-text-input id="trainer_name" name="trainer_name" type="text" class="block w-full mt-1"
                                :value="old('trainer_name')" autofocus autocomplete="trainer_name"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('trainer_name')" />
                        </div>

                        <div>
                            <x-primary-button>{{ __('Create new team') }}</x-primary-button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
