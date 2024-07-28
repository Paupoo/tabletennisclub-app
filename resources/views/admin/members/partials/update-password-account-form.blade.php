<div class="mt-8 overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
    <div class="w-full p-6 text-gray-900 dark:text-gray-100 lg:w-1/2">
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Member account') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __("Reset the member password or deactivate his account.") }}
            </p>
        </header>

        <div class="mt-6 flex gap-4">
            <x-secondary-button>{{ __('Reset password') }}</x-secondary-button>
            @if ($member->is_active)
            <x-danger-button>{{ __('Deactivate') }}</x-danger-button>
            @else
            <x-green-button>{{ __('Activate') }}</x-green-button>
            @endif
        </div>
    </div>
</div>