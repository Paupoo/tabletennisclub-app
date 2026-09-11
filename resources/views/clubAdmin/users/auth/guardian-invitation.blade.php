<x-login-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <p class="mb-4 text-sm opacity-80">
        @if ($wards->count() === 1)
            {{ __('You are creating the account that lets you manage :name\'s membership.', ['name' => $wards->first()->first_name]) }}
        @else
            {{ __('You are creating the account that lets you manage the membership of :names.', ['names' => $wards->pluck('first_name')->join(', ', ' ' . __('and') . ' ')]) }}
        @endif
        {{ __('Please check your details before confirming.') }}
    </p>

    <form method="POST" action="{{ url()->full() }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email"
                :value="$guardian->email" readonly />
        </div>

        <div class="mt-4">
            <x-form.field name="first_name" :label="__('First name')">
                <x-text-input id="first_name" class="block mt-1 w-full" type="text" name="first_name"
                    :value="old('first_name', $guardian->first_name)" required autocomplete="given-name" />
            </x-form.field>
        </div>

        <div class="mt-4">
            <x-form.field name="last_name" :label="__('Last name')">
                <x-text-input id="last_name" class="block mt-1 w-full" type="text" name="last_name"
                    :value="old('last_name', $guardian->last_name)" required autocomplete="family-name" />
            </x-form.field>
        </div>

        <div class="mt-4">
            <x-form.field name="gender" :label="__('Gender')">
                <select id="gender" name="gender"
                    class="select select-bordered block mt-1 w-full">
                    @foreach (\App\Domains\Shared\Enums\Gender::cases() as $case)
                        <option value="{{ $case->value }}" @selected(old('gender') === $case->value)>
                            {{ $case->getLabel() }}
                        </option>
                    @endforeach
                </select>
            </x-form.field>
        </div>

        <div class="mt-4">
            <x-form.field name="phone" :label="__('Phone')">
                <x-text-input id="phone" class="block mt-1 w-full" type="tel" name="phone"
                    :value="old('phone', $guardian->phone)" autocomplete="tel" />
            </x-form.field>
        </div>

        <div class="mt-4">
            <x-form.field name="password" :label="__('Password')">
                <x-text-input id="password" class="block mt-1 w-full"
                    type="password" name="password" required autocomplete="new-password" />
            </x-form.field>
        </div>

        <div class="mt-4">
            <x-form.field name="password_confirmation" :label="__('Confirm password')">
                <x-text-input id="password_confirmation" class="block mt-1 w-full"
                    type="password" name="password_confirmation" required autocomplete="new-password" />
            </x-form.field>
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-button type="submit" :label="__('Create my account')" class="btn-primary ms-3" />
        </div>
    </form>
</x-login-layout>
