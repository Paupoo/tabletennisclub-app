<x-app-layout>

    @if (session('success'))
                <div class="mt-4 bg-green-500 rounded-lg pl-3">
                    {{ session('success') }}
                </div>
            @elseif(session('deleted'))
                <div class=" mt-4 bg-red-500 rounded-lg pl-3">
                    {{ session('deleted') }}
                </div>
            @endif

    <div class="grid grid-flow-row m-auto p-4">
        <div class="grid-flow-col mb-6 ml-8">
            <h1 class="text-lg font-semibold">Selected Players ({{ count($selectedUsers) }})</h1>
            <ul class="flex gap-4">
                @foreach ($selectedUsers as $user)
                    <li class="bg-yellow-100">
                        {{ $user->last_name }} {{ $user->first_name }} {{ $user->ranking }}
                        <form action="{{ route('interclubs.toggleSelection', [$interclub, $user]) }}" method="POST">
                            @csrf
                            <x-button class="bg-blue-300">Unselect</x-button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="grid-flow-col mb-6 ml-8">
            <h1 class="text-lg font-semibold">Available Players ({{ count($subscribedUsers) }})</h1>
            <ul class="flex gap-4">
                @foreach ($subscribedUsers as $user)
                    <li class="bg-yellow-100">
                        {{ $user->last_name }} {{ $user->first_name }} {{ $user->ranking }}
                        <form action="{{ route('interclubs.toggleSelection', [$interclub, $user]) }}" method="POST">
                            @csrf
                            <x-button class="bg-blue-300">Select</x-button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="grid-flow-col mb-6 ml-8">
            <h1 class="text-lg font-semibold">Other competitors ({{ count($users) }})</h1>
            <ul class="flex gap-4">
                @foreach ($users as $user)
                    <li class="bg-yellow-100">
                        {{ $user->last_name }} {{ $user->first_name }} {{ $user->ranking }}
                        <form action="{{ route('interclubs.addToSelection', [$interclub, $user]) }}" method="POST">
                            @csrf
                            <x-button class="bg-blue-300">To do</x-button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>

    </div>


    {{-- <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Create a member') }}
        </h2>
    </x-slot>

    <x-admin-block>
        <div class="flex gap-4">
            <form action="{{ route('dashboard') }}" method="GET">
                <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
            </form>
            <form action="{{ route('users.index') }}" method="GET">
                <x-primary-button>{{ __('Manage members') }}</x-primary-button>
            </form>
        </div>
    </x-admin-block>

    <x-admin-block>
        <div class="flex flex-row flex-wrap gap-20 min-w-fit w-fit">
            <div>
                <div class="mt-6 w-fit h-fit mr-auto p-1">
                    <div
                        class="flex flex-row space-around w-fit -mt-5 -ml-5 rounded-sm bg-indigo-500 text-white text-lg font-bold text-left py-1 px-3 shadow-md relative">
                        {{ __('Selected players') }}
                    </div>

                    <div class="flex flex-wrap">
                    @foreach ($selectedUsers as $user)
                    <div class="w-fit ml-5 mt-2 rounded-sm bg-white">
                        <ul class="mt-5 border border-gray-200 rounded overflow-hidden shadow-md w-80 mx-auto">
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                                <img class="rounded-full w-12 h-12 border-4 border-indigo-200 object-cover"
                                    @if ($user->sex == \App\Enums\Sex::MEN->name) src="{{ asset('images/man.png') }}"
                            @elseif ($user->sex == \App\Enums\Sex::WOMEN->name)
                                src="{{ asset('images/woman.png') }}" alt="" @endif>
                                <div class="my-auto">
                                    {{ $user->first_name . ' ' . $user->last_name }}
                                </div>
                                <div class="">
                                    @if ($user->sex == \App\Enums\Sex::MEN->name)
                                        &#9794;
                                    @elseif ($user->sex == \App\Enums\Sex::WOMEN->name)
                                        &#9792;
                                    @endif
                                </div>
                            </li>
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                                <img class="rounded-full w-5 h-5object-cover"
                                    src="{{ asset('images/icons/phone.svg') }}" alt="">
                                <div class="my-auto">
                                    {{ $user->phone_number }}
                                </div>
                            </li>
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                                <img class="rounded-full w-5 h-5object-cover"
                                    src="{{ asset('images/icons/email.svg') }}" alt="">
                                <div class="my-auto">
                                    {{ $user->email }}
                                </div>
                            </li>
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                                <img class="rounded-full w-5 h-5object-cover"
                                    src="{{ asset('images/icons/address.svg') }}" alt="">
                                <div class="my-auto">
                                    <p>{{ $user->street }}</p>
                                    <p>{{ $user->city_code }} {{ $user->city_name }}</p>
                                </div>
                            </li>
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                                <img class="rounded-full w-5 h-5object-cover"
                                    src="{{ asset('images/icons/birthday.svg') }}" alt="">
                                <div class="my-auto">
                                    @if ($user->birthdate)
                                        {{ $user->birthdate->format('d/m/Y') }} ({{ $user->age }} {{ __('years') }})
                                    @else
                                        {{ __('Unknown')}}
                                    @endif
                                    
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
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white last:border-none border-gray-200 transition-all duration-300 ease-in-out"     >
                                    <form action="{{ route('interclubs.toggleSelection', $interclub) }}" method="post">
                                        @csrf
                                        <input type="hidden" name="user" value="{{ $user->id }}">
                                        <x-button type="submit" class="bg-indigo-300">{{ __('Unselect') }}</a></x-button>
                                    </form>
                                    
                            </li>
                        </ul>
                    </div>
                    @endforeach
                </div>
                <div class="mt-6 w-fit h-fit mr-auto p-1">
                    <div
                        class="flex flex-row space-around w-fit -mt-5 -ml-5 rounded-sm bg-indigo-500 text-white text-lg font-bold text-left py-1 px-3 shadow-md relative">
                        {{ __('Available Players') }}
                    </div>

                    <div class="flex flex-wrap">
                    @foreach ($subscribedUsers as $user)
                        
                    <div class="w-fit ml-5 mt-2 rounded-sm bg-white">
                        <ul class="mt-5 border border-gray-200 rounded overflow-hidden shadow-md w-80 mx-auto">
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                                <img class="rounded-full w-12 h-12 border-4 border-indigo-200 object-cover"
                                    @if ($user->sex == \App\Enums\Sex::MEN->name) src="{{ asset('images/man.png') }}"
                            @elseif ($user->sex == \App\Enums\Sex::WOMEN->name)
                                src="{{ asset('images/woman.png') }}" alt="" @endif>
                                <div class="my-auto">
                                    {{ $user->first_name . ' ' . $user->last_name }}
                                </div>
                                <div class="">
                                    @if ($user->sex == \App\Enums\Sex::MEN->name)
                                        &#9794;
                                    @elseif ($user->sex == \App\Enums\Sex::WOMEN->name)
                                        &#9792;
                                    @endif
                                </div>
                            </li>
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                                <img class="rounded-full w-5 h-5object-cover"
                                    src="{{ asset('images/icons/phone.svg') }}" alt="">
                                <div class="my-auto">
                                    {{ $user->phone_number }}
                                </div>
                            </li>
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                                <img class="rounded-full w-5 h-5object-cover"
                                    src="{{ asset('images/icons/email.svg') }}" alt="">
                                <div class="my-auto">
                                    {{ $user->email }}
                                </div>
                            </li>
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                                <img class="rounded-full w-5 h-5object-cover"
                                    src="{{ asset('images/icons/address.svg') }}" alt="">
                                <div class="my-auto">
                                    <p>{{ $user->street }}</p>
                                    <p>{{ $user->city_code }} {{ $user->city_name }}</p>
                                </div>
                            </li>
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                                <img class="rounded-full w-5 h-5object-cover"
                                    src="{{ asset('images/icons/birthday.svg') }}" alt="">
                                <div class="my-auto">
                                    @if ($user->birthdate)
                                        {{ $user->birthdate->format('d/m/Y') }} ({{ $user->age }} {{ __('years') }})
                                    @else
                                        {{ __('Unknown')}}
                                    @endif
                                    
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
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white last:border-none border-gray-200 transition-all duration-300 ease-in-out"     >
                                    <form action="{{ route('interclubs.toggleSelection', $interclub) }}" method="post">
                                        @csrf
                                        <input type="hidden" name="user" value="{{ $user->id }}">
                                        <x-button type="submit" class="bg-indigo-300">{{ __('Confirm') }}</a></x-button>
                                    </form>
                                    
                            </li>
                        </ul>
                    </div>
                    @endforeach
                </div>

                <div class="mt-6 w-fit h-fit mr-auto p-1">
                    <div
                        class="flex flex-row space-around w-fit -mt-5 -ml-5 rounded-sm bg-indigo-500 text-white text-lg font-bold text-left py-1 px-3 shadow-md relative">
                        {{ __('Other players') }}
                    </div>

                    <div class="flex flex-wrap">
                    @foreach ($users as $user)
                        
                    <div class="w-fit ml-5 mt-2 rounded-sm bg-white">
                        <ul class="mt-5 border border-gray-200 rounded overflow-hidden shadow-md w-80 mx-auto">
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                                <img class="rounded-full w-12 h-12 border-4 border-indigo-200 object-cover"
                                    @if ($user->sex == \App\Enums\Sex::MEN->name) src="{{ asset('images/man.png') }}"
                            @elseif ($user->sex == \App\Enums\Sex::WOMEN->name)
                                src="{{ asset('images/woman.png') }}" alt="" @endif>
                                <div class="my-auto">
                                    {{ $user->first_name . ' ' . $user->last_name }}
                                </div>
                                <div class="">
                                    @if ($user->sex == \App\Enums\Sex::MEN->name)
                                        &#9794;
                                    @elseif ($user->sex == \App\Enums\Sex::WOMEN->name)
                                        &#9792;
                                    @endif
                                </div>
                            </li>
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                                <img class="rounded-full w-5 h-5object-cover"
                                    src="{{ asset('images/icons/phone.svg') }}" alt="">
                                <div class="my-auto">
                                    {{ $user->phone_number }}
                                </div>
                            </li>
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                                <img class="rounded-full w-5 h-5object-cover"
                                    src="{{ asset('images/icons/email.svg') }}" alt="">
                                <div class="my-auto">
                                    {{ $user->email }}
                                </div>
                            </li>
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                                <img class="rounded-full w-5 h-5object-cover"
                                    src="{{ asset('images/icons/address.svg') }}" alt="">
                                <div class="my-auto">
                                    <p>{{ $user->street }}</p>
                                    <p>{{ $user->city_code }} {{ $user->city_name }}</p>
                                </div>
                            </li>
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white hover:bg-sky-100 hover:text-sky-900 border-b last:border-none border-gray-200 transition-all duration-300 ease-in-out">
                                <img class="rounded-full w-5 h-5object-cover"
                                    src="{{ asset('images/icons/birthday.svg') }}" alt="">
                                <div class="my-auto">
                                    @if ($user->birthdate)
                                        {{ $user->birthdate->format('d/m/Y') }} ({{ $user->age }} {{ __('years') }})
                                    @else
                                        {{ __('Unknown')}}
                                    @endif
                                    
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
                            <li
                                class="flex justify-center align-middle gap-1 px-4 py-2 bg-white last:border-none border-gray-200 transition-all duration-300 ease-in-out"     >
                                    <form action="{{ route('interclubs.toggleSelection', $interclub) }}" method="post">
                                        @csrf
                                        <input type="hidden" name="user" value="{{ $user->id }}">
                                        <x-button type="submit" class="bg-indigo-300">{{ __('Add to selection') }}</a></x-button>
                                    </form>
                                    
                            </li>
                        </ul>
                    </div>
                    @endforeach
                </div>

                </div>
            </div>
            <div class="flex flex-auto gap-2">
                @can('update', $interclub)
                    <a href=""><x-primary-button
                            class="my-2 float-end">{{ __('Edit') }}</x-primary-button></a>
                @endcan
                @can('delete', $interclub)
                    <a href=""><x-danger-button
                            class="my-2 float-end">{{ __('Delete') }}</x-danger-button></a>
                @endcan
            </div>
        </div>

    </x-admin-block> --}}

</x-app-layout>
