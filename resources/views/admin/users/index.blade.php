<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Members') }}
        </h2>
    </x-slot>

    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex flex-row gap-4">
                <a href="{{ route('dashboard') }}">
                    <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
                </a>
                @can('create', $user_model)
                    <a href="{{ route('users.create') }}">
                        <x-primary-button>{{ __('Create new user') }}</x-primary-button>
                    </a>
                    <a href="{{ route('setForceList') }}">
                        <x-primary-button>{{ __('Set Force Index') }}</x-primary-button>
                    </a>
                    <a href="{{ route('deleteForceList') }}">
                        <x-danger-button>{{ __('Delete Force Index') }}</x-primary-button>
                    </a>
                @endcan
            </div>

            @if (session('success'))
                <div class="mt-4 bg-green-500 rounded-lg pl-3">
                    {{ session('success') }}
                </div>
            @elseif(session('deleted'))
                <div class=" mt-4 bg-red-500 rounded-lg pl-3">
                    {{ session('deleted') }}
                </div>
            @endif
        </div>
    </div>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <livewire:users-table>
        </div>
    </div>
</x-app-layout>
