<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Create a member') }}
        </h2>
    </x-slot>

    <x-admin-block>
        <div class="flex gap-4">
            <form action="{{ route('dashboard') }}" method="GET">
                <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
            </form>
            <form action="{{ route('members.index') }}" method="GET">
                <x-primary-button>{{ __('Manage members') }}</x-primary-button>
            </form>
        </div>
    </x-admin-block>

    <x-admin-block>
        @can('update', $member)
            <a href="{{ route('members.edit', $member) }}"><x-primary-button
                    class="my-2 float-end">{{ __('Edit') }}</x-primary-button></a>
        @endcan
        <div class="mt-6 w-3/4 h-fit mr-auto p-1">
            <div
                class="w-fit -mt-5 -ml-5 rounded-sm bg-indigo-500 text-white text-lg font-bold text-left py-1 px-3 shadow-md relative">
                {{ __('Personnal info') }}
            </div>
            <div class="w-full m-auto mt-2 rounded-sm bg-white">
                <ul class="mt-5 border border-gray-200 rounded overflow-hidden shadow-md w-80 mx-auto">
                    <li
                        class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                        <img class="rounded-full w-12 h-12 border-4 border-indigo-200 object-cover"
                            @if ($member->sex == \App\Enums\Sex::MEN->name) src="{{ asset('images/man.png') }}"
                            @elseif ($member->sex == \App\Enums\Sex::WOMEN->name)
                                src="{{ asset('images/woman.png') }}" alt="" @endif>
                        <div class="my-auto">
                            {{ $member->first_name . ' ' . $member->last_name }}
                        </div>
                        <div class="">
                            @if ($member->sex == \App\Enums\Sex::MEN->name)
                                &#9794;
                            @elseif ($member->sex == \App\Enums\Sex::WOMEN->name)
                                &#9792;
                            @endif
                        </div>
                    </li>
                    <li
                        class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                        <img class="rounded-full w-5 h-5object-cover" src="{{ asset('images/icons/phone.svg') }}"
                            alt="">
                        <div class="my-auto">
                            {{ $member->phone_number }}
                        </div>
                    </li>
                    <li
                        class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                        <img class="rounded-full w-5 h-5object-cover" src="{{ asset('images/icons/email.svg') }}"
                            alt="">
                        <div class="my-auto">
                            {{ $member->email }}
                        </div>
                    </li>
                    <li
                        class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                        <img class="rounded-full w-5 h-5object-cover" src="{{ asset('images/icons/address.svg') }}"
                            alt="">
                        <div class="my-auto">
                            <p>{{ $member->street }}</p>
                            <p>{{ $member->city_code }} {{ $member->city_name }}</p>
                        </div>
                    </li>
                    <li
                        class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                        <img class="rounded-full w-5 h-5object-cover" src="{{ asset('images/icons/birthday.svg') }}"
                            alt="">
                        <div class="my-auto">
                            {{ $member->birthdate->format('d/m/Y') }} ({{ $member->age }} {{ __('years') }})
                        </div>
                    </li>
                </ul>
            </div>
        </div>
        <div class="mt-6 w-3/4 h-fit mr-auto p-1">
            <div
                class="w-fit mt-5 -ml-5 rounded-sm bg-indigo-500 text-white text-lg font-bold text-left py-1 px-3 shadow-md relative">
                {{ __('Player info') }}</div>
            <div class="w-full m-auto mt-2 rounded-sm bg-white">
                <ul class="mt-5 border border-gray-200 rounded overflow-hidden shadow-md w-80 mx-auto">

                    <li
                        class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                        <div class="my-auto">
                            {{ __('Licence:') }} {{ $member->licence }}
                        </div>
                    </li>
                    <li
                        class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                        <img class="rounded-full w-5 h-5object-cover" src="{{ asset('images/icons/ranking.svg') }}"
                            alt="">
                        <div class="my-auto">
                            {{ __('Ranking:') }} {{ $member->ranking }}
                        </div>
                    </li>

                    <li
                        class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                        <img class="rounded-full w-5 h-5object-cover" src="{{ asset('images/icons/teams.svg') }}"
                            alt="">
                        <div class="my-auto">
                            <ul>
                                @foreach ($member->teams as $team)
                                    <li>{{ $team->league->level }} {{ $team->league->division }} {{ $team->name }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </li>
                    <li
                        class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                        <div class="my-auto">
                            {{ __('Matches played:') }}
                        </div>
                        <img class="rounded-full w-5 h-5object-cover bg-green-600"
                            src="{{ asset('images/icons/win.svg') }}" alt="">
                        <div class="my-auto">
                            <p class="text-green-600">13</p>
                        </div>
                        <img class="rounded-full w-5 h-5object-cover bg-red-600"
                            src="{{ asset('images/icons/loss.svg') }}" alt="">
                        <div class="my-auto">
                            <p class="text-red-600">3</p>
                        </div>
                    </li>
                </ul>
            </div>


        </div>
    </x-admin-block>

</x-app-layout>
