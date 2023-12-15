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

                    <table class="table table-auto border border-collapse mt-4 w-96 text-left">
                        <caption class="caption-top font-thin text-right">Current roles</caption>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>Name</td>
                                <td>Description</td>
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
                                <td><address>Rue de l'Invasion 80 - 1340 Ottignies-Louvain-la-Neuve</address></td>
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

    {{-- Users management --}}
    <div class="pt-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h2 class="text-lg font-semibold pb-2">Users</h2>
                    <p>There are currently <var class="font-semibold">xx</var> users.</p>

                    <table class="table table-auto border border-collapse mt-4 w-96 text-left">
                        <caption class="caption-top font-thin text-right">5 latest users</caption>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Last Name</th>
                                <th>First Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>Jean</td>
                                <td>Dupont</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex gap-4 mt-4 w-96">
                        <form action="">
                            <x-primary-button>Create new user</x-primary-button>
                        </form>
                        <form action="">
                            <x-primary-button>Manage users</x-primary-button>
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
                                <td>Pending</td>
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
