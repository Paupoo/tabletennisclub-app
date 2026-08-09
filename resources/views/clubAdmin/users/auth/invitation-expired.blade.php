<x-login-layout>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        <p class="font-medium">{{ __('This invitation link has expired.') }}</p>
        <p class="mt-2">{{ __('Invitation links are valid for :days days.', ['days' => \App\Domains\ClubAdmin\Users\Models\User::INVITATION_LINK_VALIDITY_DAYS]) }}</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    @if ($resendUser !== null)
        <form method="POST" action="{{ route('invitation.resend', ['user' => $resendUser->id]) }}">
            @csrf

            <div class="flex items-center justify-end mt-4">
                <x-button type="submit" :label="__('Receive a new link')" class="btn-primary" />
            </div>
        </form>
    @else
        <div class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('Please contact the club to receive a new invitation.') }}
        </div>
    @endif
</x-login-layout>
