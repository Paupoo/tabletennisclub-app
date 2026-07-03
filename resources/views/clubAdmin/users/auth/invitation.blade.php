<x-login-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ url()->full() }}">
        @csrf

        <!-- Email Address (readonly) -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="$user->email" readonly />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-form.field name="password" :label="__('Mot de passe')">
                <x-text-input id="password" class="block mt-1 w-full"
                    type="password" name="password" required autocomplete="new-password" />
            </x-form.field>
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-form.field name="password_confirmation" :label="__('Confirmer le mot de passe')">
                <x-text-input id="password_confirmation" class="block mt-1 w-full"
                    type="password" name="password_confirmation" required autocomplete="new-password" />
            </x-form.field>
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-button type="submit" :label="__('Finaliser mon inscription')" class="btn-primary ms-3" />
        </div>
    </form>
</x-login-layout>
