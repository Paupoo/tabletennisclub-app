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
                @can('create', $user_model)
                    <a href="{{ route('users.create') }}">
                        <x-primary-button>{{ __('Create new user') }}</x-primary-button>
                    </a>
                    <a href="{{ route('setForceList') }}">
                        <x-primary-button>{{ __('Set Force Index') }}</x-primary-button>
                    </a>
                    <a href="{{ route('deleteForceList') }}">
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
                        @foreach ($users as $user)
                            <tr class="border-b dark:border-neutral-500">
                                <td class="px-4 whitespace-nowrap">
                                    @if ($user->sex === \App\Enums\Sex::MEN->name )
                                        &#9794;
                                    @elseif ($user->sex === \App\Enums\Sex::WOMEN->name)
                                        &#9792;
                                    @else
                                        &#9892;
                                    @endif
                                    {{ $user->last_name }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $user->first_name }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $user->force_list }}</td>
                                <td class="px-4 whitespace-nowrap">{{ $user->ranking }}</td>
                                <td class="px-4 whitespace-nowrap">
                                    @if ($user->teams->count() > 0)
                                        @foreach ($user->teams->sortBy('name') as $team)
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
                                    @if ($user->is_active == false)
                                        {{ __('Inactive') }}
                                    @else
                                        {{ __('Active') }}
                                    @endif
                                </td>
                                <td class="px-4 whitespace-nowrap">
                                    @if ($user->is_competitor == false)
                                        {{ __('Casual') }}
                                    @else
                                        {{ __('Competitor') }}
                                    @endif
                                </td>
                                <td class="px-4 whitespace-nowrap">
                                    @if ($user->has_debt == false)
                                        {{ __('No') }}
                                    @else
                                        {{ __('Yes') }}
                                    @endif
                                </td>
                                <td class="flex items-center gap-2 px-4 whitespace-nowrap">
                                    <a href="{{ route('users.show', $user->id) }}"><img class="h-4 cursor-pointer"
                                            src="{{ asset('images/icons/info.svg') }}" alt="Contact"></a>
                                    @can('update', $user_model)
                                        <a href="{{ route('users.edit', $user) }}">
                                            <button type="submit">
                                                <img class="h-4 cursor-pointer" src="{{ asset('images/icons/edit.svg') }}"
                                                    alt="Edit">
                                            </button>
                                        </a>
                                    @endcan
                                    @can('delete', $user_model)
                                        <form action="{{ route('users.destroy', $user) }}" method="POST">
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
                    {{ $users->links() }}
                </x-admin-block>

            </div>
        </div>
    </div>
</x-app-layout>
