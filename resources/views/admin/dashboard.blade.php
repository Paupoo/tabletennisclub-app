<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="pt-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-6 italic text-gray-900 dark:text-gray-100">
                    <p>Here you can manage the club's affairs.</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Members management --}}
    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h2 class="pb-2 text-lg font-semibold">Members</h2>
                    <div class="flex justify-around w-full gap-4">
                        <table class="border table-auto table-collapse w-96">
                            <caption class="text-right caption-top font-extralight">Members overview</caption>
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
                                    <td class="text-center rounded-lg hover:bg-blue-200 hover:font-bold">
                                        {{ $members_total_active }}</td>
                                    <td class="text-center rounded-lg hover:bg-blue-200 hover:font-bold">
                                        {{ $members_total_inactive }}</td>
                                </tr>
                                <tr>
                                    <th class="text-right">Training</th>
                                    <td class="text-center rounded-lg hover:bg-blue-200 hover:font-bold"></td>
                                    <td class="text-center rounded-lg hover:bg-blue-200 hover:font-bold"></td>
                                </tr>
                                <tr>
                                    <th class="text-right">Competition</th>
                                    <td class="text-center rounded-lg hover:bg-blue-200 hover:font-bold">
                                        {{ $members_total_competitors }}</td>
                                    <td class="text-center rounded-lg hover:bg-blue-200 hover:font-bold">
                                        {{ $members_total_casuals }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <table class="table mt-4 text-left border border-collapse table-auto w-96">
                            <caption class="font-thin text-right caption-top">5 latest members</caption>
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
                                        <td>{{ $member->created_at->format('d-M-Y') }}</td>
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

    {{-- Rooms management --}}
    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h2 class="pb-2 text-lg font-semibold">Rooms</h2>

                    @if ($rooms->count() == 0)
                        <p>{{ __('It seems that no rooms have been defined. Start by creating a new room.') }}</p>
                    @else
                        <table class="table w-full mt-4 text-left border border-collapse table-auto lg:w-3/4">
                            <caption class="font-thin text-right caption-top">Current rooms</caption>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Adress</th>
                                    <th>Capacity</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rooms as $room)
                                    <tr>
                                        <td>{{ $room->name }}</td>
                                        <td>
                                            <address>{{ $room->street }}, {{ $room->city_code }},
                                                {{ $room->city_name }}</address>
                                        </td>
                                        <td class="flex flex-row justify-around">
                                            <span>T{{ $room->capacity_trainings }}</span>
                                            <span>-</span>
                                            <span>M{{ $room->capacity_matches }}</span>
                                        </td>
                                        <td>{{ $room->access_description }}</td>
                                    </tr>
                                @endforeach

                            </tbody>
                        </table>
                    @endif


                    <div class="flex gap-4 mt-4 w-96">
                        <form action="">
                            <x-primary-button>Create new room</x-primary-button>
                        </form>
                        <form action="{{ route('rooms.index') }}" method="GET">
                            <x-primary-button>Manage rooms</x-primary-button>
                        </form>
                        <form action="/test" method="GET">
                            Room capacity checker
                            <input type="number" name="people" id="">
                            <select name="activity" id="activity">
                                <option value="training">Training</option>
                                <option value="match">Match</option>
                                <option value="unknown">Unknown</option>
                            </select>
                            <select name="room_id" id="room">
                                @foreach ($rooms as $room)
                                    <option value="{{ $room->id }}">{{ $room->name }}</option>
                                @endforeach
                            </select>
                            <x-danger-button>Test</x-primary-button>
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
                    <h2 class="pb-2 text-lg font-semibold">Trainings</h2>
                    
                    @if ($trainings->count() == 0)
                        <p>{{ __('It seems that no trainings have been defined. Start by creating a new training session.') }}
                        </p>
                    @else
                        <table class="table w-full mt-4 text-left border border-collapse table-auto lg:w-3/4">
                            <caption class="font-thin text-right caption-top">5 latest trainings</caption>
                            <thead>
                                <tr>
                                    <th>Room</th>
                                    <th>Time</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Demeester/-1</td>
                                    <td>Saturday - 09:00-10:30</td>
                                    <td>Directed</td>
                                </tr>
                            </tbody>
                        </table>
                    @endif

                    {{-- Quick Actions --}}
                    <div class="flex gap-4 mt-4 w-96">

                        <form action="{{ route('trainings.create')}}" method="GET">
                            <x-danger-button>Create a new training</x-primary-button>
                        </form>
                        <form action="{{ route('trainings.index')}}" method="GET">
                            <x-danger-button>Manage trainings</x-primary-button>
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
                    <h2 class="pb-2 text-lg font-semibold">Teams</h2>

                    @if ($teams->count() == 0)
                        <p>{{ __('It seems that no teams have been defined. Start by creating a new team. ') }}</p>
                    @else
                        <table class="table mt-4 text-left border border-collapse table-auto w-96">
                            <caption class="font-thin text-right caption-top">{{ __('5 latest Teams') }}</caption>
                            <thead>
                                <tr>
                                    <th>Team</th>
                                    <th>Division</th>
                                    <th>Captain</th>
                                    <th># players</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($teams as $team)
                                    <tr>
                                        <td>{{ $team->name }}</td>
                                        <td>{{ $team->league?->category }} {{ $team->league?->level }} {{ $team->league?->division }}</td>
                                        <td>{{ $team->captain?->first_name }} {{ $team->captain?->last_name }}</td>
                                        <td>{{ $team->users->count()}}</td>
                                    </tr>
                                @endforeach

                            </tbody>
                        </table>
                    @endif

                    <div class="flex gap-4 mt-4 w-96">
                        <form action="{{ route('teams.create') }}" method="GET">
                            <x-primary-button>Create new team</x-primary-button>
                        </form>
                        <form action="{{ route('teams.index') }}">
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
                    <h2 class="pb-2 text-lg font-semibold">Matches</h2>

                    {{-- IF no matches --}}
                    <p>{{ __('It seems that no teams have been defined. Start by creating a new team.') }}</p>

                    {{-- If matches --}}
                    <p>There are currently <var class="font-semibold">xx</var> matches.</p>

                    <table class="table w-full mt-4 text-left border border-collapse table-auto lg:w-3/4">
                        <caption class="font-thin text-right caption-top">Upcoming matches</caption>
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
                                <td class="flex flex-row gap-2 align-middle">
                                    <p>Pending</p>
                                    <img src="{{ asset('images/icons/hourglass.svg') }}" alt="pending"
                                        class="h-5">
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex gap-4 mt-4 w-96">
                        <form action="">
                            <x-danger-button>{{ __('Create new match') }}</x-primary-button>
                        </form>
                        <form action="">
                            <x-danger-button>{{ __('Manage matches') }}</x-primary-button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>



</x-app-layout>
