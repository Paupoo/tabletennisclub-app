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
            <form action="{{ route('members.index') }}" method="GET">
                <x-primary-button>{{ __('Manage users') }}</x-primary-button>
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

                    <form action="{{ route('members.update', $member->id) }}" method="POST" class="mt-6 space-y-6">
                        @csrf
                        @method('PUT')
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
                            <x-text-input id="ranking" name="ranking" type="text" class="block w-full mt-1"
                                :value="old('ranking', $member->ranking)" autofocus autocomplete="ranking"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('ranking')" />
                        </div>

                        {{-- Role --}}
                        <div>
                            <x-input-label for="role" :value="__('Role')" />
                            <x-select-input id="role" name="role" type="text" class="block w-full mt-1"
                                 autofocus autocomplete="role">
                                @foreach ($roles as $role)

                                    @if(old('role') !== null && old('role') == $role->id)

                                        <option value="{{ $role->id }}" selected>{{ $role->name }}</option>

                                    @elseif (old('role') === null && $role->id == $member->role->id)

                                        <option value="{{ $role->id }}" selected>{{ $role->name }}</option>

                                    @else

                                        <option value="{{ $role->id }}">{{ $role->name }}</option>

                                    @endif

                                @endforeach

                            </x-select-input>
                            <x-input-error class="mt-2" :messages="$errors->get('role')" />
                        </div>
                        {{-- Team --}}
                        <div>
                            <x-input-label for="team_id" :value="__('Team')" />
                            <x-select-input id="team_id" name="team_id" type="text" class="block w-full mt-1"
                                 autofocus autocomplete="team_id">
                                <option value="" selected disabled>{{ __('None') }}</option>

                                @foreach ($teams as $team)
                                    @if (old('team_id') != null && old('team_id') == $team->id)
                                        <option value="{{ $team->id }}" selected>
                                            {{ $team->season . ' - ' . $team->name . ' - ' . $team->division }}
                                        </option>
                                    @else
                                        <option value="{{ $team->id }}">
                                            {{ $team->season . ' - ' . $team->name . ' - ' . $team->division }}
                                        </option>
                                    @endif
                                @endforeach
                            </x-select-input>
                            <x-input-error class="mt-2" :messages="$errors->get('team_id')" />
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
