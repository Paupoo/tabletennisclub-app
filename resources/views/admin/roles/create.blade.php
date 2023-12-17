<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Create a member') }}
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

                    <form action="{{ route('members.store') }}" method="POST" class="mt-6 space-y-6">
                        @csrf

                        {{-- First Name --}}
                        <div>
                            <x-input-label for="first_name" :value="__('First Name')" />
                            <x-text-input id="first_name" name="first_name" type="text" class="block w-full mt-1"
                                :value="old('first_name')" required autofocus autocomplete="first_name"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
                        </div>

                        {{-- Last Name --}}
                        <div>
                            <x-input-label for="last_name" :value="__('Last Name')" />
                            <x-text-input id="last_name" name="last_name" type="text" class="block w-full mt-1"
                                :value="old('last_name')" required autofocus autocomplete="last_name"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
                        </div>

                        {{-- Email --}}
                        <div>
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" name="email" type="email" class="block w-full mt-1"
                                :value="old('email')" required autofocus autocomplete="email"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('email')" />
                        </div>

                        {{-- Password --}}
                        <div>
                            <x-input-label for="password" :value="__('Password')" />
                            <x-text-input id="password" name="password" type="password" class="block w-full mt-1"
                                :value="old('password')" required autofocus autocomplete="password"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('password')" />
                        </div>

                        {{-- Confirm Password --}}
                        <div class="mt-4">
                            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

                            <x-text-input id="password_confirmation" class="block w-full mt-1" type="password"
                                name="password_confirmation" required autocomplete="new-password" />

                            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="licence" :value="__('Licence')" />
                            <x-text-input id="licence" name="licence" type="number" class="block w-full mt-1"
                                :min="1" :max="999999" :value="old('licence')" autofocus
                                autocomplete="licence"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('licence')" />
                        </div>

                        <div>
                            <x-input-label for="ranking" :value="__('Ranking')" />
                            <x-text-input id="ranking" name="ranking" type="text" class="block w-full mt-1"
                                :value="old('ranking')" autofocus autocomplete="ranking"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('ranking')" />
                        </div>

                        <div>
                            <x-input-label for="team" :value="__('Team')" />
                            <x-text-input id="team" name="team" type="text" class="block w-full mt-1"
                                :value="old('team')" autofocus autocomplete="team"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('team')" />
                        </div>

                        <div>
                            <x-primary-button>{{ __('Create new user') }}</x-primary-button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
