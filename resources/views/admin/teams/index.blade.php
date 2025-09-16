<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Teams') }}
        </h2>
    </x-slot>

    <!-- Boutons d’action en haut -->
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
                <div class="mt-4 bg-green-500 text-white p-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @elseif(session('deleted'))
                <div class="mt-4 bg-red-500 text-white p-3 rounded-lg">
                    {{ session('deleted') }}
                </div>
            @endif
        </div>
    </div>

    <!-- === Teams in club === -->
    @include('admin.teams.partials.list', [
        'title' => __('Teams in the club'),
        'teams' => $teamsInClub,
        'teamModel' => $teamModel
    ])

    <!-- === Teams not in club === -->
    @include('admin.teams.partials.list', [
        'title' => __('Teams not in the club'),
        'teams' => $teamsNotInClub,
        'teamModel' => $teamModel
    ])
</x-app-layout>
