<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="pt-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100 italic">
                    <p>Here you can manage the club's affairs.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Roles management --}}
    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h2 class="text-lg font-semibold pb-2">Roles</h2>

                    <table class="table table-auto border border-collapse mt-4 lg:w-3/4 w-full text-left">
                        <caption class="caption-top font-thin text-right">Current roles</caption>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Members</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($roles as $role)
                                <tr>
                                    <td>1</td>
                                    <td>{{ $role->name }}</td>
                                    <td>{{ $role->description }}</td>
                                    <td>{{ $role->user->count() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="flex gap-4 mt-4 w-96">
                        <form action="{{ route('roles.create') }}">
                            <x-primary-button>Create new role</x-primary-button>
                        </form>
                        <form action="{{ route('roles.index') }}">
                            <x-primary-button>Manage roles</x-primary-button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Rooms management --}}
    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h2 class="text-lg font-semibold pb-2">Rooms</h2>

                    <table class="table table-auto border border-collapse mt-4 lg:w-3/4 w-full text-left">
                        <caption class="caption-top font-thin text-right">Current rooms</caption>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Adress</th>
                                <th>Capacity</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>Demeester/-1</td>
                                <td>
                                    <address>Rue de l'Invasion 80 - 1340 Ottignies-Louvain-la-Neuve</address>
                                </td>
                                <td>T 6 / M 4</td>
                                <td>La salle se trouve au -1</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex gap-4 mt-4 w-96">
                        <form action="">
                            <x-primary-button>Create new role</x-primary-button>
                        </form>
                        <form action="">
                            <x-primary-button>Manage roles</x-primary-button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Members management --}}
    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h2 class="text-lg font-semibold pb-2">Members</h2>
                    <div class="w-full flex gap-4 justify-around">
                        <table class="table-auto table-collapse border w-96">
                            <caption class="caption-top font-extralight text-right">Members overview</caption>
                            <thead class="text-left border-b">
                                <tr>
                                    <th></th>
                                    <th class=""">Active</th>
                                    <th class="">Inactive</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="">
                                    <th class="text-right">Total</th>
                                    <td class="hover:bg-blue-200 rounded-lg hover:font-bold text-center">
                                        {{ $members_total }}</td>
                                    <td class="hover:bg-blue-200 rounded-lg hover:font-bold text-center">x</td>
                                </tr>
                                <tr>
                                    <th class="text-right">Training</th>
                                    <td class="hover:bg-blue-200 rounded-lg hover:font-bold text-center">y</td>
                                    <td class="hover:bg-blue-200 rounded-lg hover:font-bold text-center">z</td>
                                </tr>
                                <tr>
                                    <th class="text-right">Competition</th>
                                    <td class="hover:bg-blue-200 rounded-lg hover:font-bold text-center">a</td>
                                    <td class="hover:bg-blue-200 rounded-lg hover:font-bold text-center">b</td>
                                </tr>
                            </tbody>
                        </table>

                        <table class="table table-auto border border-collapse mt-4 w-96 text-left">
                            <caption class="caption-top font-thin text-right">5 latest members</caption>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Last Name</th>
                                    <th>First Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($members as $member)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $member->first_name }}</td>
                                        <td>{{ $member->last_name }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex gap-4 mt-4 w-96">
                        <form action="{{ route('members.create') }}" method="GET">
                            <x-primary-button>Create new member</x-primary-button>
                        </form>
                        <form action="{{ route('members.index') }}" method="GET">
                            <x-primary-button>Manage members</x-primary-button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Training management --}}
    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h2 class="text-lg font-semibold pb-2">Trainings</h2>
                    <p>There are currently <var class="font-semibold">xx</var> trainings.</p>

                    <table class="table table-auto border border-collapse mt-4 lg:w-3/4 w-full text-left">
                        <caption class="caption-top font-thin text-right">Trainings</caption>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Level</th>
                                <th>Room</th>
                                <th>Trainer</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>Newbies</td>
                                <td>Demeester/-1</td>
                                <td>John Doe</td>
                                <td>Saturday - 09:00-10:30</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex gap-4 mt-4 w-96">
                        <form action="">
                            <x-primary-button>Create new team</x-primary-button>
                        </form>
                        <form action="">
                            <x-primary-button>Manage teams</x-primary-button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Teams management --}}
    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h2 class="text-lg font-semibold pb-2">Teams</h2>
                    <p>There are currently <var class="font-semibold">xx</var> teams.</p>

                    <table class="table table-auto border border-collapse mt-4 w-96 text-left">
                        <caption class="caption-top font-thin text-right">Teams</caption>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Team</th>
                                <th>Kern</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>A</td>
                                <td>6</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex gap-4 mt-4 w-96">
                        <form action="">
                            <x-primary-button>Create new team</x-primary-button>
                        </form>
                        <form action="">
                            <x-primary-button>Manage teams</x-primary-button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Matches management --}}
    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h2 class="text-lg font-semibold pb-2">Matches</h2>
                    <p>There are currently <var class="font-semibold">xx</var> matches.</p>

                    <table class="table table-auto border border-collapse mt-4 lg:w-3/4 w-full text-left">
                        <caption class="caption-top font-thin text-right">Upcoming matches</caption>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Match</th>
                                <th>Division</th>
                                <th>Team</th>
                                <th>Captain</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>BBW114 vs BBW210</td>
                                <td>PROV-1</td>
                                <td>A</td>
                                <td>Jean Dupont</td>
                                <td class="flex align-middle flex-row gap-2">
                                    <p>Pending</p>
                                    <img src="{{ asset('images/icons/hourglass.svg') }}" alt="pending" class="h-5">
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex gap-4 mt-4 w-96">
                        <form action="">
                            <x-primary-button>Create new match</x-primary-button>
                        </form>
                        <form action="">
                            <x-primary-button>Manage matches</x-primary-button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>



</x-app-layout>
