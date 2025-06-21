<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Edit a room') }}
        </h2>
    </x-slot>

    <x-admin-block>
        <div class="flex gap-4">
            <form action="{{ route('dashboard') }}" method="GET">
                <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
            </form>
            <form action="{{ route('rooms.index') }}" method="GET">
                <x-primary-button>{{ __('Manage Rooms') }}</x-primary-button>
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
                            {{ __('Edit \'' . $room->name) . '\'' }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __("Rooms are used for trainings and for competition. Their addresses are also shown on the public website.") }}
                        </p>
                    </header>

                    <x-forms.room :room="$room" />
                </div>
            </div>
        </div>
    </div>

</x-app-layout>