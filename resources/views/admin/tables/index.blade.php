<x-app-layout>
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
                <form action="{{ route('tables.create') }}">
                    <x-primary-button>{{ __('Create a new table') }}</x-primary-button>
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
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">


                @if($tables->count() > 0)
                <table class="min-w-full text-md font-light text-left border-collapse table-auto dark:bg-neutral-300">
                    <thead class="font-medium border-b dark:border-neutral-500">
                        <tr>
                            <th scope="col" class="px-4 py-2">{{ __('Name') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Room') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Date of purchase') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Age (in years)') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('State') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Last update') }}</th>
                            @can('create', \App\Models\Room::class)
                            <th scope="col" class="px-4 py-2">{{ __('Actions') }}</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tables as $table)
                            <tr class="border-b dark:border-neutral-500">
                                <td class="px-4 whitespace-wrap">{{ $table->name }}</td>
                                <td class="px-4 whitespace-wrap">{{ $table->room->name }}</td>
                                <td class="px-4 whitespace-wrap">{{ $table->purchased_on ? $table->purchased_on->format('d/m/Y') : __('Unknown') }}</td>
                                <td class="px-4 whitespace-wrap">{{ $table->purchased_on ? round($table->purchased_on->diffInYears(now())) : __('Unknown') }}</td>
                                <td class="px-4 whitespace-wrap">{{ $table->state ? $table->state : __('Unknown') }}</td>
                                <td class="px-4 whitespace-wrap">{{ $table->updated_at }}</td>
                                <td class="px-4 whitespace-wrap">{{ \Illuminate\Support\Str::of($table->access_description)->limit(100) }}</td>
                                @can('create', \App\Models\Room::class)
                                <td class="flex items-center gap-2 px-4 whitespace-nowrap">
                                    <form action="{{ route('tables.edit', $table->id) }}" method="GET">
                                        <button type="submit">
                                            <img class="h-4 cursor-pointer" src="{{ asset('images/icons/edit.svg') }}" alt="Edit">
                                        </button>
                                    </form>
                                    <form action="{{ route('tables.destroy', $table->id) }}" method="POST">
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

                @else
                {{ __('No table found. Maybe start creating a new one?') }}
                @endif
                
            </div>
            <div class="mt-4">
                {{ $tables->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
