<x-app-layout :breadcrumbs="$breadcrumbs">
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
            <a href="{{ route('teams.edit', $team) }}"><x-primary-button
                    class="my-2 float-end">{{ __('Edit') }}</x-primary-button></a>
        @endcan
        <div class="mt-6 w-3/4 h-fit mr-auto border border-gray-100 rounded-md p-1">
            <div
                class="w-fit -mt-5 -ml-5 rounded-sm bg-indigo-500 text-white text-lg font-bold text-left py-1 px-3 shadow-md relative">
                {{ __('Team') }} {{ $team->name }}</div>
            <div class="w-full m-auto mt-2 rounded-sm bg-white">Hello</div>
            <div
                class="w-fit mt-5 -ml-5 rounded-sm bg-indigo-500 text-white text-lg font-bold text-left py-1 px-3 shadow-md relative">
                {{ __('Captain') }} {{ $team->captain?->first_name }} {{ $team->captain?->last_name }}</div>
                <div class="mt-5 border border-gray-200 rounded overflow-hidden shadow-md w-3/4 mx-auto">
                    {{ $team->captain?->first_name }} {{ $team->captain?->last_name }}
                    {{ $team->captain?->email }} {{ $team->captain?->phone_number }}
                </div>
            <div
                class="w-fit mt-5 -ml-5 rounded-sm bg-indigo-500 text-white text-lg font-bold text-left py-1 px-3 shadow-md relative">
                {{ __('Players') }}</div>
            <ul class="mt-5 border border-gray-200 rounded overflow-hidden shadow-md w-3/4 mx-auto">
                @foreach ($team->users as $player)
                    <li
                        class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                        <img class="rounded-full w-12 h-12 border-4 border-indigo-200 object-cover"
                            @if ($player->sex === \App\Enums\Sex::MEN->name)
                                src="{{ asset('images/man.png') }}"
                            @elseif ($player->sex === \App\Enums\Sex::WOMEN->name)
                                src="{{ asset('images/woman.png') }}"
                            @endif 
                        alt="">
                        <div class="my-auto">
                            {{ $player->first_name }} {{ $player->last_name }}
                        </div>
                    </li>
                @endforeach

            </ul>
        </div>
    </x-admin-block>
</x-app-layout>
