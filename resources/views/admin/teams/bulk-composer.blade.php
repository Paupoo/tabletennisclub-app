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
                    {!! \App\Classes\HtmlFactory::GetSeasonsHTMLDropdown() !!}
                </x-select-input>

                <x-input-label class="mt-2" for="kern_selector">{{ __('Define your teams kern size') }}</x-input-label>
                <x-text-input class="w-20 h-8 mt-2" type="number" name="kern_size" id="kern_selector" min="5"
                    step="1" value="{{ old('kern_size') }}" required></x-text-input>
                <x-primary-button class="mt-4 w-36">{{ __('Build teams') }}</x-primary-button>
            </form>
        </x-admin-block>

        @isset($teams)
            <div class="mx-auto text-center font-bold text-2xl">
                <h1>{{ $teams[0]['season'] }}</h1>

            </div>
            <div class="grid grid-cols-3 gap-8 px-8 m-auto max-xl:grid-cols-2 max-w-7xl">
                @foreach ($teams as $team)
                    <div class="bg-white shadow-lg rounded-xl w-96">
                        <h2 class="mt-2 mb-4 text-center">{{ $team['name'] }}</h2>
                        <ol>
                            @foreach ($team as $key => $value)
                                <li class="flex justify-between px-4 rounded-lg even:bg-gray-200">
                                    @if ($key <= $kern)
                                        {{ $value['last_name'] . ' ' . $value['first_name'] . ' - ' . $value['ranking'] . ' - ' . $value['force_index'] }}
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endforeach
                <div class="bg-white shadow-lg rounded-xl w-96">
                    <h2 class="mt-2 mb-4 text-center">{{ __('Players Without Team') }}</h2>
                    <ol>
                        @foreach ($playersWithoutTeam as $key => $value)
                            <li class="flex justify-between px-4 rounded-lg even:bg-gray-200">
                                {{ $value['last_name'] . ' ' . $value['first_name'] . ' - ' . $value['ranking'] . ' - ' . $value['force_index'] }}
                            </li>
                        @endforeach
                    </ol>
                </div>
                <div class="flex w-1/2 gap-4 mx-auto mt-6">
                    <form action="{{ route('saveTeamsCompositions') }}" method="POST">
                        @csrf
                        <input type="hidden" name="kern_size" value="{{ $kern }}">
                        <input type="hidden" name="season" value="{{ $teams[0]['season'] }}">
                        <x-danger-button>Confirm and create</x-primary-button>
                    </form>
                </div>
            </div>
        @endisset

    </div>
</x-app-layout>
