<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Edit a member') }}
        </h2>
    </x-slot>

    <x-admin-block>
        <div class="flex gap-4">
            <form action="{{ route('dashboard') }}" method="GET">
                <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
            </form>
            <form action="{{ route('teams.index') }}" method="GET">
                <x-primary-button>{{ __('Manage Teams') }}</x-primary-button>
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
                            {{ __('Profile Information') }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __("Update your account's profile information and email address.") }}
                        </p>
                    </header>

                    <form action="{{ route('teams.update', $team->id) }}" method="POST" class="mt-6 space-y-6">
                        @csrf
                        @method('PUT')
                        {{-- Season --}}
                        <div>
                            <x-input-label for="season" :value="__('Season')" />
                            <x-text-input id="season" name="season" list="seasons" type="text"
                                class="block w-full mt-1" :value="old('season', $team->season)" required autofocus></x-text-input>
                            <datalist id="seasons">
                                @for ($i = -1; $i < 5; $i++)
                                    <option value="{{ date('Y')+$i . ' - ' . date('Y')+$i+1 }}">
                                @endfor

                            </datalist>
                            <x-input-error class="mt-2" :messages="$errors->get('season')" />
                        </div>

                        {{-- Name --}}
                        <div>
                            <x-input-label for="name" :value="__('Name')" />
                            <x-text-input id="name" name="name" type="text" class="block w-full mt-1"
                                :value="old('name', $team->name)" required autofocus autocomplete="name"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        {{-- Division --}}
                        <div>
                            <x-input-label for="division" :value="__('Division')" />
                            <x-text-input id="division" name="division" type="text" class="block w-full mt-1"
                                :value="old('division', $team->division)" required autofocus autocomplete="division"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('division')" />
                        </div>

                        {{-- Players --}}
                        <div>
                            <x-input-label for="player1" :value="__('Choose a player from the list')" />

                            <x-text-input id="player1" name="player1" list="players" class="block w-full mt-1"
                                :value="old('player1')" autofocus></x-text-input>
                            <datalist id="players">
                                @foreach ($users as $user)
                                    <option
                                        value="{{ $user->last_name . ' ' . $user->first_name . ' - ' . $user->ranking . ' - ' . $user->force_index }}">
                                @endforeach
                            </datalist>
                            <x-input-error class="mt-2" :messages="$errors->get('division')" />
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
