<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Rooms') }}
        </h2>
    </x-slot>

    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex flex-row gap-4">
                <form action="{{ route('dashboard') }}" method="GET">
                    @csrf
                    <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
                </form>
                @can('create', \App\Models\Room::class)        
                <form action="{{ route('rooms.create') }}">
                    <x-primary-button>{{ __('Create a new room') }}</x-primary-button>
                </form>
                @endcan

            </div>
            
            @if(session('success'))
            <div class="mt-4 bg-green-500 rounded-lg">
                {{ session('success') }}
            </div>
            @elseif(session('deleted'))
            <div class="pl-3 mt-4 bg-red-500 rounded-lg">
                {{ session('deleted') }}
            </div>
            @endif
        </div>
    </div>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-xs dark:bg-gray-800 sm:rounded-lg">


                <table class="min-w-full text-md font-light text-left border-collapse table-auto dark:bg-neutral-300">
                    <thead class="font-medium border-b dark:border-neutral-500">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ __('Name') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Building name') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Address') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Total tables') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Total playable tables') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Training capacity') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Interclubs capacity') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Access description') }}</th>
                            @can('create', \App\Models\Room::class)
                            <th scope="col" class="px-4 py-2">{{ __('Actions') }}</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rooms as $room)
                            <tr class="border-b dark:border-neutral-500">
                                <td class="px-4 whitespace-wrap">{{ $room->name }}</td>
                                <td class="px-4 whitespace-wrap">{{ $room->building_name }}</td>
                                <td class="px-4 whitespace-wrap">{{ $room->street . ', ' . $room->city_code . ' ' . $room->city_name }}</td>
                                <td class="px-4 whitespace-wrap">{{ $room->total_tables }}</td>
                                <td class="px-4 whitespace-wrap">{{ $room->total_playable_tables }}</td>
                                <td class="px-4 whitespace-wrap">{{ $room->capacity_for_trainings }}</td>
                                <td class="px-4 whitespace-wrap">{{ $room->capacity_for_interclubs }}</td>
                                <td class="px-4 whitespace-wrap">{{ \Illuminate\Support\Str::of($room->access_description)->limit(100) }}</td>
                                @can('create', \App\Models\Room::class)
                                <td class="flex items-center gap-2 px-4 whitespace-nowrap">
                                    <form action="{{ route('rooms.edit', $room->id) }}" method="GET">
                                        <button type="submit">
                                            <img class="h-4 cursor-pointer" src="{{ asset('images/icons/edit.svg') }}" alt="Edit">
                                        </button>
                                    </form>
                                    <form action="{{ route('rooms.destroy', $room->id) }}" method="POST">
                                        @csrf
                                        @method('delete')
                                        <button>
                                            <img class="h-4 cursor-pointer" src="{{ asset('images/icons/delete.svg') }}" alt="Delete">
                                        </button>
                                    </form>
                                </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                
            </div>
            <div class="mt-2">
                {{ $rooms->links() }}

            </div>
        </div>
    </div>
</x-app-layout>
