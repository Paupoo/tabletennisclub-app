<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Members') }}
        </h2>
    </x-slot>

    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="flex flex-row gap-4">
                <a href="{{ route('dashboard') }}">
                    <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
                </a>
                @can('create', $member_model)
                    <a href="{{ route('members.create') }}">
                        <x-primary-button>{{ __('Create new user') }}</x-primary-button>
                    </a>
                    <a href="{{ route('setForceIndex') }}">
                        <x-primary-button>{{ __('Set Force Index') }}</x-primary-button>
                    </a>
                    <a href="{{ route('deleteForceIndex') }}">
                        <x-danger-button>{{ __('Delete Force Index') }}</x-primary-button>
                    </a>
                @endcan
            </div>

            @if (session('success'))
                <div class="mt-4 bg-green-500 rounded-lg pl-3">
                    {{ session('success') }}
                </div>
            @elseif(session('deleted'))
                <div class=" mt-4 bg-red-500 rounded-lg pl-3">
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
                            <th scope="col" class="px-4 py-2">{{ __('Last Name') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('First Name') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Force Index') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Ranking') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Teams') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Active') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Competitor') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Has Debts') }}</th>
                            <th scope="col" class="px-4 py-2">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $member)
                            <tr class="border-b dark:border-neutral-500">
                                <td class="px-4 whitespace-nowrap">{{ $member->last_name }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $member->first_name }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $member->force_index }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $member->ranking }}</td>
                                <td class="px-4 whitespace-nowrap">
                                    @if ($member->teams->count() > 0)
                                        @foreach ($member->teams->sortBy('name') as $team)
                                            <a href="{{ route('teams.show', $team)}}">{{ $team->name }}</a>    
                                            @if (!$loop->last)
                                                {{-- This is not the last iteration --}}
                                                {{ ' | ' }}
                                            @endif
                                        @endforeach
                                    @else
                                        {{ __('No team') }}
                                    @endif
                                </td>
                                <td class="px-4 whitespace-nowrap">
                                    @if ($member->is_active == false)
                                        {{ __('Inactive') }}
                                    @else
                                        {{ __('Active') }}
                                    @endif
                                </td>
                                <td class="px-4 whitespace-nowrap">
                                    @if ($member->is_competitor == false)
                                        {{ __('Casual') }}
                                    @else
                                        {{ __('Competitor') }}
                                    @endif
                                </td>
                                <td class="px-4 whitespace-nowrap">
                                    @if ($member->has_debt == false)
                                        {{ __('No') }}
                                    @else
                                        {{ __('Yes') }}
                                    @endif
                                </td>
                                <td class="flex items-center gap-2 px-4 whitespace-nowrap">
                                    <a href="{{ route('members.show', $member->id) }}"><img class="h-4 cursor-pointer"
                                            src="{{ asset('images/icons/info.svg') }}" alt="Contact"></a>
                                    @can('update', $member_model)
                                        <a href="{{ route('members.edit', $member->id) }}">
                                            <button type="submit">
                                                <img class="h-4 cursor-pointer" src="{{ asset('images/icons/edit.svg') }}"
                                                    alt="Edit">
                                            </button>
                                        </a>
                                    @endcan
                                    @can('delete', $member_model)
                                        <form action="{{ route('members.destroy', $member->id) }}" method="POST">
                                            @csrf
                                            @method('delete')
                                            <button>
                                                <img class="h-4 cursor-pointer"
                                                    src="{{ asset('images/icons/delete.svg') }}" alt="Delete">
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <x-admin-block>
                    {{ $members->links() }}
                </x-admin-block>

            </div>
        </div>
    </div>
</x-app-layout>
