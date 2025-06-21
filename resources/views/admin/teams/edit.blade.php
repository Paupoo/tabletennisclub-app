<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Edit a team') }}
        </h2>
    </x-slot>

    <x-admin-block>
        <div class="flex gap-4">
            <form action="{{ route('dashboard') }}" method="GET">
                <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
            </form>
            <form action="{{ route('teams.index') }}" method="GET">
                <x-primary-button>{{ __('Manage Teams') }}</x-primary-button>
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
                            {{ __('Team Information') }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __("Update your team.") }}
                        </p>
                    </header>

                    
                    <form action="{{ route('teams.update', $team->id) }}" method="POST" class="mt-6 space-y-6">
                        @csrf
                        @method('PUT')
                        
                        <x-forms.team 
                            :attachedUsers="$attachedUsers"
                            :league_categories="$league_categories"
                            :league_levels="$league_levels"
                            :seasons="$seasons"
                            :team="$team"
                            :team_names="$team_names"
                            :users="$users"
                            >
                        </x-forms.team>

                        <div>
                            <x-primary-button>{{ __('Save change') }}</x-primary-button>
                        </div>
                        
                    </form>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
