<x-login-layout>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div>
            <x-form.field name="email" :label="__('Email')">
                <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            </x-form.field>
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-form.field name="password" :label="__('Password')">
                <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            </x-form.field>
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-form.field name="password_confirmation" :label="__('Confirm Password')">
                <x-text-input id="password_confirmation" class="block mt-1 w-full"
                    type="password" name="password_confirmation" required autocomplete="new-password" />
            </x-form.field>
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-button type="submit" :label="__('Reset Password')" class="btn-primary" />
        </div>
    </form>
</x-login-layout>
