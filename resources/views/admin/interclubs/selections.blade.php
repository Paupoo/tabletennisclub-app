<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Selections') }}
        </h2>
    </x-slot>

    <x-admin-block>
        
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
                    <th scope="col" class="px-4 py-2">{{ __('Select') }}</th>
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
                            <td class="px-4 whitespace-nowrap">
                                @foreach ($interclub->users as $user)
                                    <div class="bg-green-100 rounded-lg">{{ $user->last_name[0].$user->first_name[0] }} - {{ $user->ranking }}</div>                                        
                                @endforeach
                            </td>
                            <td class="px-4 whitespace-nowrap"><input type="checkbox" name="selection"
                                    id="selection" disabled @checked(false)></td>
                            <td class="px-4 whitespace-nowrap"><a href=""><button type="submit"><img
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
    </x-admin-block>

    
</x-app-layout>