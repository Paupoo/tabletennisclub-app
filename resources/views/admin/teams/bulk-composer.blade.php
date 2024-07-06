<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Create a member') }}
        </h2>
    </x-slot>

    <div class="pt-6">
        <div class="relative mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex flex-row justify-start gap-4">
                <form action="{{ route('dashboard') }}" method="GET">
                    <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
                </form>
                <form action="{{ route('teams.index') }}" method="GET">
                    <x-primary-button>{{ __('Manage Teams') }}</x-primary-button>
                </form>
                <form action="{{ route('teams.create') }}" method="GET">
                    <x-primary-button>{{ __('Create new team') }}</x-primary-button>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-6">

        <x-admin-block>
            <form class="flex flex-col" action="{{ route('proposeTeamsCompositions') }}" method="GET">
                @csrf
                <x-input-label for="season">{{ __('Pick up a season') }}</x-input-label>
                <x-select-input class="mt-2 w-1/2 w-min-sm" id="season" name="season">
                    {!! \App\Classes\HtmlFactory::SeasonsInHTMLList() !!}
                </x-select-input>

                <x-input-label class="mt-2"
                    for="playersPerTeamSelector">{{ __('Define your players per teams') }}</x-input-label>
                <x-text-input class="w-20 h-8 mt-2" type="number" name="playersPerTeam" id="playersPerTeamSelector"
                    min="5" step="1" value="{{ old('playersPerTeam') }}" required></x-text-input>
                    <x-input-error class="" :messages="$errors->get('playersPerTeam')" />
                <x-primary-button class="mt-4 w-36">{{ __('Build teams') }}</x-primary-button>
            </form>
        </x-admin-block>

        @isset($teams)
            <div class="mx-auto text-center font-bold text-2xl">
                <h1>{{ $season }}</h1>

            </div>
            <div class="grid grid-cols-3 gap-8 px-8 m-auto max-xl:grid-cols-2 max-w-7xl">
                @foreach ($teams as $team)
                    <div
                        class="@if ($loop->last) bg-gray-300 @else bg-white @endif shadow-lg rounded-xl w-96">
                        @foreach ($team as $name => $players)
                            <h2 class="mt-2 mb-4 text-center">{{ $name }}</h2>
                            <ol>
                                @foreach ($players as $player)
                                    <li class="flex justify-between px-4 rounded-lg even:bg-gray-200">
                                        {{ $player->last_name . ' ' . $player->first_name . ' - ' . $player->ranking }}
                                    </li>
                                @endforeach
                            </ol>
                        @endforeach
                    </div>
                @endforeach

                <div class="flex w-1/2 gap-4 mx-auto mt-6">
                    <form action="{{ route('saveTeamsCompositions') }}" method="POST">
                        @csrf
                        <input type="hidden" name="playersPerTeam" value="{{ $playersPerTeam }}">
                        <input type="hidden" name="season" value="{{ $season }}">
                        <x-danger-button>Confirm and create</x-primary-button>
                    </form>
                </div>
            </div>
        @endisset

    </div>
</x-app-layout>
