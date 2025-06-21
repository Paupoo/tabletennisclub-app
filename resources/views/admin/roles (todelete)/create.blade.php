<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Create a role') }}
        </h2>
    </x-slot>

    <x-admin-block>
        <div class="flex gap-4">
            <form action="{{ route('dashboard') }}" method="GET">
                <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
            </form>
            <form action="{{ route('roles.index') }}" method="GET">
                <x-primary-button>{{ __('Manage Roles') }}</x-primary-button>
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
                            {{ __('Create roles') }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __("Beware, roles are only to be used and modified by an admin, as authentication policies are linked to them.") }}
                        </p>
                    </header>

                    <form action="{{ route('roles.store') }}" method="POST" class="mt-6 space-y-6">
                        @csrf

                        {{-- Name --}}
                        <div>
                            <x-input-label for="name" :value="__('Name')" />
                            <x-text-input id="name" name="name" type="text" class="block w-full mt-1"
                                :value="old('name')" required autofocus autocomplete="name"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>

                        {{-- Description --}}
                        <div>
                            <x-input-label for="description" :value="__('Description')" />
                            <x-textarea-input id="description" name="description" type="text" class="block w-full mt-1"
                                :value="old('description')" autofocus autocomplete="description">Describe here the new role.</x-textarea-input>
                            <x-input-error class="mt-2" :messages="$errors->get('description')" />
                        </div>

                        <div>
                            <x-primary-button>{{ __('Create new role') }}</x-primary-button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
