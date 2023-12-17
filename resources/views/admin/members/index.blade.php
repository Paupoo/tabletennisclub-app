<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Members') }}
        </h2>
    </x-slot>

    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex flex-row gap-4">
                <form action="{{ route('dashboard') }}">
                    <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
                </form>
                <form action="{{ route('members.create') }}">
                    <x-primary-button>{{ __('Create new user') }}</x-primary-button>
                </form>
            </div>
            
            @if(session('success'))
            <div class="mt-4 bg-red-500">
                {{ session('success') }}
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
                            <th scope="col" class="px-4 py-2">#</th>
                            <th scope="col" class="px-4 py-2">{{ __('Last Name') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('First Name') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Role') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Force Index') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Ranking') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Team') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $member)
                            <tr class="border-b dark:border-neutral-500">
                                <td class="px-4 font-medium whitespace-nowrap">
                                    {{ $loop->iteration }}
                                </td>
                                <td class="px-4 whitespace-nowrap">{{ $member->last_name }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $member->first_name }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $member->role->name }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $member->force_index }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $member->ranking }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $member->team }}</td>
                                <td class="flex items-center gap-2 px-4 whitespace-nowrap">
                                    <img class="h-4 cursor-pointer" src="{{ asset('images/icons/contact.svg') }}"
                                        alt="Contact">
                                    <form action="{{ route('members.edit', $member->id) }}" method="GET">
                                        <button type="submit">
                                            <img class="h-4 cursor-pointer" src="{{ asset('images/icons/edit.svg') }}" alt="Edit">
                                        </button>
                                    </form>
                                    <form action="{{ route('members.destroy', $member->id) }}" method="POST">
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

            </div>
        </div>
    </div>
</x-app-layout>
