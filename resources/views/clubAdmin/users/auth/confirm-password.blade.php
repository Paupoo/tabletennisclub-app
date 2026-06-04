<x-login-layout>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-form.field name="password" :label="__('Password')">
                <x-text-input id="password" class="block mt-1 w-full" type="password"
                    name="password" required autocomplete="current-password" />
            </x-form.field>
        </div>

        <div class="flex justify-end mt-4">
            <x-button type="submit" :label="__('Confirm')" class="btn-primary" />
        </div>
    </form>
</x-login-layout>
