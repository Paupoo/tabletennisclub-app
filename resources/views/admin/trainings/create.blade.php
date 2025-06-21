<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Create a training') }}
        </h2>
    </x-slot>

    <x-admin-block>
        <div class="flex gap-4">
            <form action="{{ route('dashboard') }}" method="GET">
                <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
            </form>
            <form action="{{ route('trainings.index') }}" method="GET">
                <x-primary-button>{{ __('Manage Trainings') }}</x-primary-button>
            </form>
        </div>
    </x-admin-block>

    @if (session('error'))
        <div class="mt-4 bg-red-500 rounded-lg">
            {{ session('error') }}
        </div>
    @endif
    <div class="pt-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-xs dark:bg-gray-800 sm:rounded-lg">
                @if (session('success'))
                    <x-notification-success>{{ session('success') }}</x-notification-success>
                @endif
                <div class="w-full p-6 text-gray-900 dark:text-gray-100 lg:w-1/2">
                    <header>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('Create trainings') }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {!! __('Here you can create training sessions. <br> If you wish to create "bulk" training sessions, simply extend the end date at your convenience. The trainings will be created on a weekly bases (for example, if your start date is a Monday, it will occur every Mondays between start and end date.)') !!}
                        </p>
                    </header>

                    <x-forms.training :levels="$levels" :training="$training" :types="$types" :rooms="$rooms" :seasons="$seasons" :users="$users"/>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
