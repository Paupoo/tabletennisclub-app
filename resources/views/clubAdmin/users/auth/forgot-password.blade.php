<x-login-layout>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-form.field name="email" :label="__('Email')">
                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            </x-form.field>
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-button type="submit" :label="__('Email Password Reset Link')" class="btn-primary" />
        </div>
    </form>
</x-login-layout>
