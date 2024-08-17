<div class="mt-8 overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
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

        <form action="{{ route('users.update', $user->id) }}" method="POST" class="mt-6 space-y-6">
            @csrf
            @method('PUT')

            <x-forms.user :user="$user" :rankings="$rankings" :teams="$teams"
                :sexes="$sexes"></x-forms.user>


            <div>
                <x-primary-button>{{ __('Save') }}</x-primary-button>
            </div>

        </form>
    </div>
</div>