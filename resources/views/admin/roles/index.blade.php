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
                    @csrf
                    <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
                </form>
                <form action="{{ route('roles.create') }}">
                    <x-primary-button>{{ __('Create new role') }}</x-primary-button>
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

                @if ($roles->count() == 0)
                    <p class="p-4">{{ __('It seems that no roles have been defined. Start by creating a new role.') }}</p>
                @else
                    <table
                        class="min-w-full text-sm font-light text-left border-collapse table-auto dark:bg-neutral-300">
                        <thead class="font-medium border-b dark:border-neutral-500">
                            <tr>
                                <th scope="col" class="px-4 py-2">#</th>
                                <th scope="col" class="px-4 py-2">{{ __('Name') }}</th>
                                <th scope="col" class="px-4 py-2">{{ __('Description') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roles as $role)
                                <tr class="border-b dark:border-neutral-500">
                                    <td class="px-4 font-medium whitespace-nowrap">
                                        {{ $loop->iteration }}
                                    </td>
                                    <td class="px-4 whitespace-wrap">{{ $role->name }}</td>
                                    <td class="px-4 whitespace-wrap">
                                        {{ \Illuminate\Support\Str::of($role->description)->limit(100) }}</td>
                                    <td class="flex items-center gap-2 px-4 whitespace-nowrap">
                                        <form action="{{ route('roles.edit', $role->id) }}" method="GET">
                                            <button type="submit">
                                                <img class="h-4 cursor-pointer"
                                                    src="{{ asset('images/icons/edit.svg') }}" alt="Edit">
                                            </button>
                                        </form>
                                        <form action="{{ route('roles.destroy', $role->id) }}" method="POST">
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
</x-app-layout>
