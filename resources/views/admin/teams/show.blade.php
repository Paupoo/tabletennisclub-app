<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Team ' . $team->name) }}
        </h2>
    </x-slot>

    <x-admin-block>
        <div class="flex gap-4">
            <form action="{{ route('dashboard') }}" method="GET">
                <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
            </form>
            <form action="{{ route('teams.index') }}" method="GET">
                <x-primary-button>{{ __('Manage teams') }}</x-primary-button>
            </form>
        </div>
    </x-admin-block>

    <x-admin-block>
        @can('update', $team) 
        <a href="{{ route('teams.edit', $team) }}"><x-primary-button class="my-2 float-end">{{ __('Edit') }}</x-primary-button></a>
        @endcan
        <div class="flex flex-col max-w-sm gap-4 p-10">
            <div class="flex justify-between">{{ __('Team name : ') }}<span class="font-bold">{{ $team->name }}</span></div>
            <div class="flex justify-between">{{ __('Season : ') }}<span class="font-bold">{{ $team->season }}</span></div>
            <div class="flex justify-between">{{ __('Division : ') }}<span class="font-bold">{{ $team->division }}</span></div>
            <div class="flex justify-between">{{ __('Players : ') }}</div>
            @isset($team->users)
                <ol>
                    @foreach ($team->users->sortBy([
                        ['ranking', 'asc'], 
                        ['last_name', 'asc'],
                        ]) as $player)
                        <li>{{ $player->last_name }} {{ $player->first_name }} - {{ $player->ranking }} -- {{ $player->force_index }}</li>
                    @endforeach
                </ol>
            @endisset
        </div>
    </x-admin-block>


</x-app-layout>
