<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Create a member') }}
        </h2>
    </x-slot>

    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 relative">
            <div class="flex flex-row gap-4 justify-start">
                <form action="{{ route('dashboard') }}" method="GET">
                    <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
                </form>
                <form action="{{ route('teams.create') }}" method="GET">
                    <x-primary-button>{{ __('Create new team') }}</x-primary-button>
                </form>
                
                <form class="absolute right-0"
                    action="{{ route('proposeTeamsCompositions') }}" method="GET">
                    <input class="h-8 w-20" type="number" name="kern_size" id="kern_selector" min="5"
                        step="1" value="{{ old('kern_size') }}">
                    <x-primary-button class="w-36">{{ __('Build teams') }}</x-primary-button>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-6">


        @isset($results)
            <div class="grid grid-cols-3 gap-8 w-3/4 m-auto">

                @foreach ($results as $team_name => $team)
                    <div class="bg-white rounded-xl w-96 shadow-lg">
                        <h2 class="mt-2 mb-4 text-center">{{ $team_name }}</h2>
                        <ol>

                            @foreach ($team as $players)
                                <li class="even:bg-gray-200 rounded-lg flex justify-between px-4">
                                    @foreach ($players as $key => $value)
                                        <span>
                                            {{ $value }}
                                        </span>
                                    @endforeach
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endforeach
                <div class="mt-6 flex gap-4 w-1/2 mx-auto">
                <x-danger-button>Validate</x-primary-button></div>
            </div>
        @endisset

    </div>
</x-app-layout>
