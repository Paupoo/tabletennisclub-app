<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Matches') }}
        </h2>
    </x-slot>

    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex flex-row gap-4">
                <form action="{{ route('dashboard') }}" method="GET">
                    <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
                </form>
                <form action="{{ route('interclubs.create') }}" method="GET">
                    <x-primary-button>{{ __('Create new match') }}</x-primary-button>
                </form>
                </form>
                <form action="{{ route('interclubs.selections') }}" method="GET">
                    <x-primary-button>{{ __('Make selections') }}</x-primary-button>
                </form>
            </div>

            @if (session('success'))
                <div class="mt-4 bg-green-500">
                    {{ session('success') }}
                </div>
            @elseif(session('deleted'))
                <div class="bg-red-500 mt-4 rounded-lg pl-3">
                    {{ session('deleted') }}
                </div>
            @endif
        </div>
    </div>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">

                <table class="min-w-full text-sm font-light text-left dark:bg-neutral-300">
                    <thead class="font-medium border-b dark:border-neutral-500">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ __('Week') }}</th>
                            {{-- <th scope="col" class="px-4 py-2">{{ __('Level') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Category') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Division') }}</th> --}}
                            <th scope="col" class="px-4 py-2">{{ __('Visited') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Visitor') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Date and time') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Address') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Available') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Selected') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <form action="{{ route('interclubs.subscription') }}" method="post">
                            @csrf

                            {{-- {{ dd($interclubs)}} --}}
                            @foreach ($interclubs as $interclub)
                                <tr class="border-b dark:border-neutral-500">
                                    <td class="px-4 whitespace-nowrap">{{ $interclub->week_number }}</td>
                                    {{-- <td class="px-4 whitespace-nowrap">{{ $interclub->league?->level }}</td>
                                    <td class="px-4 whitespace-nowrap">{{ $interclub->league?->category }}</td>
                                    <td class="px-4 whitespace-nowrap">{{ $interclub->league?->division }}</td> --}}
                                    <td class="px-4 whitespace-nowrap">{{ $interclub->visitedTeam?->club->name}} {{ $interclub?->visitedTeam?->name }}</td>
                                    <td class="px-4 whitespace-nowrap">{{ $interclub->visitingTeam?->club->name}} {{ $interclub?->visitingTeam?->name }}</td>
                                    <td class="px-4 whitespace-nowrap">
                                        {{ $interclub->start_date_time->format('l d/m/Y H:i') }}</td>
                                    <td class="px-4 whitespace-nowrap">{{ $interclub->address }}</td>
                                    <td class="px-4 whitespace-nowrap"><input type="checkbox"
                                            name="subscriptions[{{ $interclub->id }}]" id="subscription"
                                            @checked(Auth::user()->interclubs->firstWhere('id', $interclub->id))></td>
                                    <td class="px-4 whitespace-nowrap"><input type="checkbox" name="selection"
                                            id="selection" disabled @checked(false)></td>
                                    <td class="px-4 whitespace-nowrap"><a href="{{ route('interclubs.show', $interclub->id) }}"><button type="button"><img
                                                    class="h-4 cursor-pointer"
                                                    src="{{ asset('images/icons/info.svg') }}"
                                                    alt="Info"></button></a></td>
                                    <td class="px-4 whitespace-nowrap"><a href=""><button type="submit"><img
                                                    class="h-4 cursor-pointer"
                                                    src="{{ asset('images/icons/edit.svg') }}"
                                                    alt="Edit"></button></a></td>
                                    <td class="px-4 whitespace-nowrap"><a href=""><button type="submit"><img
                                                    class="h-4 cursor-pointer"
                                                    src="{{ asset('images/icons/calendar.svg') }}"
                                                    alt="Send iCal"></button></a></td>
                                </tr>
                            @endforeach
                    </tbody>
                </table>

                <x-admin-block>
                    <x-primary-button>Save</x-primary-button>

                    </form>
                    {{ $interclubs->links() }}
                </x-admin-block>

            </div>
        </div>
    </div>
</x-app-layout>
