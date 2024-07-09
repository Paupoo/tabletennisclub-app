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
                <form action="{{ route('competitions.create') }}" method="GET">
                    <x-primary-button>{{ __('Create new match') }}</x-primary-button>
                </form>
            </div>
            
            @if(session('success'))
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
                            <th scope="col" class="px-4 py-2">{{ __('Match') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Competition week') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Date and time') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Address') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Available') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Selected') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- {{ dd($competitions)}} --}}
                        @foreach ($competitions as $competition)
                            <tr class="border-b dark:border-neutral-500">
                                <td class="px-4 whitespace-nowrap">Ottignies {{ $competition->team->name }} - {{ $competition->opposing_team }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $competition->week_number }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $competition->competition_date->format('d-m-Y H:i') }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $competition->address }}</td>
                                <td class="px-4 whitespace-nowrap"><input type="checkbox" name="subscription" id="subscription" @checked($competition->pivot->is_available)></td>
                                <td class="px-4 whitespace-nowrap"><input type="checkbox" name="selection" id="selection" disabled @checked($competition->pivot->is_selected)></td>
                                <td class="px-4 whitespace-nowrap"><x-secondary-button>Send mail invite</x-secondary-button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                
                <x-admin-block>
                    <x-primary-button>Save</x-primary-button>
                    {{ $competitions->links() }}
                </x-admin-block>

            </div>
        </div>
    </div>
</x-app-layout>