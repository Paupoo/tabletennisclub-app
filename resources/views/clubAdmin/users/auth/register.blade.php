<x-login-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <x-public.antispam-fields />

        <!-- Last Name -->
        <div>
            <x-form.field name="last_name" :label="__('Last Name')">
                <x-text-input id="last_name" class="block w-full mt-1" type="text" name="last_name" :value="old('last_name')" required autofocus autocomplete="last_name" />
            </x-form.field>
        </div>

        <!-- First Name -->
        <div class="mt-4">
            <x-form.field name="first_name" :label="__('First Name')">
                <x-text-input id="first_name" class="block w-full mt-1" type="text" name="first_name" :value="old('first_name')" required autofocus autocomplete="first_name" />
            </x-form.field>
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-form.field name="email" :label="__('Email')">
                <x-text-input id="email" class="block w-full mt-1" type="email" name="email" :value="old('email')" required autocomplete="username" />
            </x-form.field>
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-form.field name="password" :label="__('Password')">
                <x-text-input id="password" class="block w-full mt-1"
                    type="password" name="password" required autocomplete="new-password" />
            </x-form.field>
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-form.field name="password_confirmation" :label="__('Confirm Password')">
                <x-text-input id="password_confirmation" class="block w-full mt-1"
                    type="password" name="password_confirmation" required autocomplete="new-password" />
            </x-form.field>
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="text-sm text-gray-600 underline rounded-md dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-button type="submit" :label="__('Register')" class="btn-primary ms-4" />
        </div>
    </form>
</x-login-layout>
