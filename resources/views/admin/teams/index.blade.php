<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Teams') }}
        </h2>
    </x-slot>

    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex flex-row gap-4">
                <form action="{{ route('dashboard') }}" method="GET">
                    <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
                </form>
                @can('create', $teamModel)
                    <form action="{{ route('teamBuilder.prepare') }}" method="GET">
                        <x-primary-button>{{ __('Team Builder') }}</x-primary-button>
                    </form>
                    <form action="{{ route('teams.create') }}" method="GET">
                        <x-primary-button>{{ __('Create new team') }}</x-primary-button>
                    </form>
                @endcan
            </div>

            @if (session('success'))
                <div class="mt-4 bg-green-500">
                    {{ session('success') }}
                </div>
            @elseif(session('deleted'))
                <div class="pl-3 mt-4 bg-red-500 rounded-lg">
                    {{ session('deleted') }}
                </div>
            @endif
        </div>
    </div>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-xs dark:bg-gray-800 sm:rounded-lg">
                <div class="w-full p-6 text-gray-900 dark:text-gray-100 lg:w-1/2">
                    <header>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('Teams in the club') }}
                        </h2>
                        {{-- 
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('Create a new team') }}
                        </p> --}}
                    </header>
                </div>

                <table class="min-w-full text-sm font-light text-left dark:bg-neutral-300">
                    <thead class="font-medium border-b dark:border-neutral-500">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ __('Name (#Players)') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Season') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Category') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('League') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Captain') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teamsInClub as $team)
                            <tr class="border-b dark:border-neutral-500">
                                <td class="px-4 whitespace-nowrap">{{ $team->name }} ({{ $team->users->count() }})
                                </td>
                                <td class="px-4 whitespace-nowrap">{{ $team->season?->name }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $team->league?->category }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $team->league?->level }}
                                    {{ $team->league?->division }}</td>
                                <td class="px-4 whitespace-nowrap">
                                    {{ isset($team->captain->last_name) ? $team->captain->first_name . ' ' . $team->captain->last_name : __('No captain') }}
                                </td>
                                <td class="flex items-center gap-2 px-4 whitespace-nowrap">
                                    <a href="{{ route('teams.show', $team->id) }}"><img class="h-4 cursor-pointer"
                                            src="{{ asset('images/icons/contact.svg') }}" alt="Check details"></a>
                                    @can('update', $teamModel)
                                        <a href="{{ route('teams.edit', $team->id) }}">
                                            <img class="h-4 cursor-pointer" src="{{ asset('images/icons/edit.svg') }}"
                                                alt="Edit">
                                        </a>
                                    @endcan
                                    @can('delete', $teamModel)
                                        <form action="{{ route('teams.destroy', $team->id) }}" method="POST">
                                            @csrf
                                            @method('delete')
                                            <button>
                                                <img class="h-4 cursor-pointer"
                                                    src="{{ asset('images/icons/delete.svg') }}" alt="Delete">
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <x-admin-block>
                    {{ $teamsInClub->links() }}
                </x-admin-block>

            </div>
        </div>
    </div>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-xs dark:bg-gray-800 sm:rounded-lg">
                <div class="w-full p-6 text-gray-900 dark:text-gray-100 lg:w-1/2">
                    <header>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('Teams not in the club') }}
                        </h2>
                        {{-- 
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('Create a new team') }}
                        </p> --}}
                    </header>
                </div>

                <table class="min-w-full text-sm font-light text-left dark:bg-neutral-300">
                    <thead class="font-medium border-b dark:border-neutral-500">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ __('Name (#Players)') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Season') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Category') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('League') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Captain') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teamsNotInClub as $team)
                            <tr class="border-b dark:border-neutral-500">
                                <td class="px-4 whitespace-nowrap">{{ $team->name }} ({{ $team->users->count() }})
                                </td>
                                <td class="px-4 whitespace-nowrap">{{ $team->season?->name }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $team->league?->category }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $team->league?->level }}
                                    {{ $team->league?->division }}</td>
                                <td class="px-4 whitespace-nowrap">
                                    {{ isset($team->captain->last_name) ? $team->captain->first_name . ' ' . $team->captain->last_name : __('No captain') }}
                                </td>
                                <td class="flex items-center gap-2 px-4 whitespace-nowrap">
                                    <a href="{{ route('teams.show', $team->id) }}"><img class="h-4 cursor-pointer"
                                            src="{{ asset('images/icons/contact.svg') }}" alt="Check details"></a>
                                    @can('update', $teamModel)
                                        <a href="{{ route('teams.edit', $team->id) }}">
                                            <img class="h-4 cursor-pointer" src="{{ asset('images/icons/edit.svg') }}"
                                                alt="Edit">
                                        </a>
                                    @endcan
                                    @can('delete', $teamModel)
                                        <form action="{{ route('teams.destroy', $team->id) }}" method="POST">
                                            @csrf
                                            @method('delete')
                                            <button>
                                                <img class="h-4 cursor-pointer"
                                                    src="{{ asset('images/icons/delete.svg') }}" alt="Delete">
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <x-admin-block>
                    {{ $teamsNotInClub->links() }}
                </x-admin-block>

            </div>
        </div>
    </div>
</x-app-layout>
