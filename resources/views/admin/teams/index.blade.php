<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Members') }}
        </h2>
    </x-slot>

    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex flex-row gap-4">
                <form action="{{ route('dashboard') }}" method="GET">
                    <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
                </form>
                <form action="{{ route('teams.create') }}" method="GET">
                    <x-primary-button>{{ __('Create new team') }}</x-primary-button>
                </form>
                <form action="{{ route('teamBulkComposer') }}" method="GET">
                    <x-primary-button>{{ __('Bulk builder') }}</x-primary-button>
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
                            <th scope="col" class="px-4 py-2">{{ __('Name') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Season') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Division') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Captain') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teams as $team)
                            <tr class="border-b dark:border-neutral-500">
                                <td class="px-4 whitespace-nowrap">{{ $team->name }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $team->season }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $team->division }}</td>
                                <td class="px-4 whitespace-nowrap"></td>
                                <td class="flex items-center gap-2 px-4 whitespace-nowrap">
                                    <img class="h-4 cursor-pointer" src="{{ asset('images/icons/contact.svg') }}"
                                        alt="Contact">
                                    <form action="" method="GET">
                                        <button type="submit">
                                            <img class="h-4 cursor-pointer" src="{{ asset('images/icons/edit.svg') }}" alt="Edit">
                                        </button>
                                    </form>
                                    <form action="{{ route('teams.destroy', $team->id) }}" method="POST">
                                        @csrf
                                        @method('delete')
                                        <button>
                                            <img class="h-4 cursor-pointer" src="{{ asset('images/icons/delete.svg') }}" alt="Delete">
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                
                <x-admin-block>
                    {{ $teams->links() }}
                </x-admin-block>

            </div>
        </div>
    </div>
</x-app-layout>
