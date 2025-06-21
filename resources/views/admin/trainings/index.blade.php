<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Trainings') }}
        </h2>
    </x-slot>

    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex flex-row gap-4">
                <form action="{{ route('dashboard') }}" method="GET">
                    @csrf
                    <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
                </form>
                <form action="{{ route('trainings.create') }}">
                    <x-primary-button>{{ __('Create new training') }}</x-primary-button>
                </form>

            </div>

            @if (session('success'))
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

                @if ($trainings->count() == 0)
                    <p class="p-4">{{ __('It seems that no trainings have been defined. Start by creating a new training.') }}</p>
                @else
                    <table
                        class="min-w-full text-sm font-light text-left border-collapse table-auto dark:bg-neutral-300">
                        <thead class="font-medium border-b dark:border-neutral-500">
                            <tr>
                                <th scope="col" class="px-4 py-2">{{ __('Day') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Date') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Start') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('End') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Room name') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Type') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Trainer') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Level') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Remaining places') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($trainings as $training)
                                <tr class="border-b dark:border-neutral-500 hover:bg-slate-500 hover:bg-opacity-10">
                                    <td class="px-4 whitespace-wrap">{{ $training->start->format('l') }}</td>
                                    <td class="px-4 whitespace-wrap">{{ $training->start->format('d-m-Y') }}</td>
                                    <td class="px-4 whitespace-wrap">{{ $training->start->format('H:i') }}</td>
                                    <td class="px-4 whitespace-wrap">{{ $training->end->format('H:i') }}</td>
                                    <td class="px-4 whitespace-wrap">{{ $training->room->name }}</td>
                                    <td class="px-4 whitespace-wrap">{{ $training->type }}</td>
                                    <td class="px-4 whitespace-wrap">{{ $training->trainer?->last_name }} {{ $training->trainer?->first_name }}</td>
                                    <td class="px-4 whitespace-wrap">{{ $training->level }}</td>
                                    <td class="px-4 whitespace-wrap">{{ $training->room->capacity_for_trainings }}</td>
                                    <td class="flex items-center gap-2 px-4 whitespace-nowrap">
                                        <form action="{{ route('trainings.edit', $training->id) }}" method="GET">
                                            <button type="submit">
                                                <img class="h-4 cursor-pointer"
                                                    src="{{ asset('images/icons/edit.svg') }}" alt="Edit">
                                            </button>
                                        </form>
                                        <form action="{{ route('trainings.destroy', $training->id) }}" method="POST">
                                            @csrf
                                            @method('delete')
                                            <button>
                                                <img class="h-4 cursor-pointer"
                                                    src="{{ asset('images/icons/delete.svg') }}" alt="Delete">
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

            </div>
        </div>
    </div>

    <x-admin-block>
        {{ $trainings->links() }}
    </x-admin-block>
    
</x-app-layout>

