<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center">
            <div>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-gray-200">
                    {{ __('Dashboard') }}
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    {{ __('Manage your club affairs and get an overview of all activities') }}
                </p>
            </div>
        </div>
    </x-slot>

    {{-- Hero Section avec message d'accueil --}}
    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-gradient-to-r from-blue-600 to-blue-800 shadow-lg sm:rounded-lg">
                <div class="p-6 text-white">
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <svg class="w-12 h-12 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H9m0 0H5m0 0h2m12-14V9m0 4v2"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold">{{ __('Welcome to your club management dashboard') }}</h3>
                            <p class="text-blue-100 text-sm">{{ __('Here you can manage all aspects of your club efficiently') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Statistiques rapides --}}
    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Members -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('Total Members') }}</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $users_total_active + $users_total_inactive }}</p>
                        </div>
                        <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        {{ $users_total_active }} {{ __('active') }} • {{ $users_total_inactive }} {{ __('inactive') }}
                    </div>
                </div>

                <!-- Active Members -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('Active Members') }}</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $users_total_active }}</p>
                        </div>
                        <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="flex items-center text-xs text-green-600 dark:text-green-400">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L10 4.414 4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            {{ __('Currently active') }}
                        </div>
                    </div>
                </div>

                <!-- Competitors -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('Competitors') }}</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $users_total_competitors }}</p>
                        </div>
                        <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-full">
                            <svg class="w-6 h-6 text-purple-600 dark:text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Competitive players') }}
                    </div>
                </div>

                <!-- Rooms Count -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border-l-4 border-orange-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('Total Rooms') }}</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $rooms->count() }}</p>
                        </div>
                        <div class="p-3 bg-orange-100 dark:bg-orange-900 rounded-full">
                            <svg class="w-6 h-6 text-orange-600 dark:text-orange-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2-2v16m14 0h2m-2 0h-4m-5 0H9m0 0H5m0 0h2"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('Available facilities') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Section principale avec layout en grille --}}
    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                {{-- Members Management --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Members') }}</h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        {{-- Overview Table --}}
                        <div class="overflow-x-auto mb-6">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left py-2 font-medium text-gray-500 dark:text-gray-400"></th>
                                        <th class="text-center py-2 font-medium text-gray-500 dark:text-gray-400">{{ __('Active') }}</th>
                                        <th class="text-center py-2 font-medium text-gray-500 dark:text-gray-400">{{ __('Inactive') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="text-gray-900 dark:text-gray-100">
                                    <tr class="border-b border-gray-100 dark:border-gray-700">
                                        <td class="py-3 font-medium">{{ __('Total') }}</td>
                                        <td class="text-center py-3">
                                            <span class="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 px-2 py-1 rounded-full text-xs font-medium">
                                                {{ $users_total_active }}
                                            </span>
                                        </td>
                                        <td class="text-center py-3">
                                            <span class="bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 px-2 py-1 rounded-full text-xs font-medium">
                                                {{ $users_total_inactive }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr class="border-b border-gray-100 dark:border-gray-700">
                                        <td class="py-3 font-medium">{{ __('Competition') }}</td>
                                        <td class="text-center py-3">
                                            <span class="bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 px-2 py-1 rounded-full text-xs font-medium">
                                                {{ $users_total_competitors }}
                                            </span>
                                        </td>
                                        <td class="text-center py-3">
                                            <span class="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-2 py-1 rounded-full text-xs font-medium">
                                                {{ $users_total_casuals }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {{-- Latest Members --}}
                        @if($users->count() > 0)
                            <div class="mb-6">
                                <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">{{ __('5 Latest Members') }}</h4>
                                <div class="space-y-2">
                                    @foreach($users as $user)
                                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                                    <span class="text-xs font-medium text-blue-600 dark:text-blue-300">
                                                        {{ substr($user->first_name, 0, 1) }}{{ substr($user->last_name, 0, 1) }}
                                                    </span>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $user->first_name }} {{ $user->last_name }}
                                                    </p>
                                                </div>
                                            </div>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $user->created_at->format('d M Y') }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Actions --}}
                        @if(Auth()->user()->is_committee_member || Auth()->user()->is_admin)
                        <div class="flex flex-col sm:flex-row gap-3">
                            <form action="{{ route('users.create') }}" method="GET" class="flex-1">
                                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    {{ __('Create new member') }}
                                </button>
                            </form>
                            <form action="{{ route('users.index') }}" method="GET" class="flex-1">
                                <button type="submit" class="w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    {{ __('Manage members') }}
                                </button>
                            </form>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Teams Management --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg">
                                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Teams') }}</h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        @if($teams->count() == 0)
                            <div class="text-center py-8">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <p class="text-gray-500 dark:text-gray-400">{{ __('It seems that no teams have been defined. Start by creating a new team.') }}</p>
                            </div>
                        @else
                            <div class="space-y-3 mb-6">
                                @foreach($teams as $team)
                                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                                                <span class="text-sm font-bold text-purple-600 dark:text-purple-300">
                                                    {{ substr($team->name, 0, 1) }}
                                                </span>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-gray-100">{{ $team->name }}</p>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $team->league?->category }} {{ $team->league?->level }} {{ $team->league?->division }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm text-gray-900 dark:text-gray-100">{{ $team->users->count() }} {{ __('players') }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $team->captain?->first_name }} {{ $team->captain?->last_name }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Actions --}}
                        @if(Auth()->user()->is_committee_member || Auth()->user()->is_admin)
                        <div class="flex flex-col sm:flex-row gap-3">
                            <form action="{{ route('teams.create') }}" method="GET" class="flex-1">
                                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    {{ __('Create new team') }}
                                </button>
                            </form>
                            <form action="{{ route('teams.index') }}" method="GET" class="flex-1">
                                <button type="submit" class="w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    {{ __('Manage teams') }}
                                </button>
                            </form>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Section Rooms et Trainings --}}
    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                {{-- Rooms Management --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="p-2 bg-orange-100 dark:bg-orange-900 rounded-lg">
                                    <svg class="w-6 h-6 text-orange-600 dark:text-orange-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2-2v16m14 0h2m-2 0h-4m-5 0H9m0 0H5m0 0h2"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Rooms') }}</h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        @if($rooms->count() == 0)
                            <div class="text-center py-8">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2-2v16m14 0h2m-2 0h-4m-5 0H9m0 0H5m0 0h2"></path>
                                </svg>
                                <p class="text-gray-500 dark:text-gray-400">{{ __('It seems that no rooms have been defined. Start by creating a new room.') }}</p>
                            </div>
                        @else
                            <div class="space-y-3 mb-6">
                                @foreach($rooms as $room)
                                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <div class="flex items-center justify-between mb-2">
                                            <h4 class="font-medium text-gray-900 dark:text-gray-100">{{ $room->name }}</h4>
                                            <div class="flex space-x-2">
                                                <span class="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 px-2 py-1 rounded text-xs">
                                                    T{{ $room->capacity_trainings }}
                                                </span>
                                                <span class="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 px-2 py-1 rounded text-xs">
                                                    M{{ $room->capacity_matches }}
                                                </span>
                                            </div>
                                        </div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $room->street }}, {{ $room->city_code }} {{ $room->city_name }}</p>
                                        @if($room->access_description)
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $room->access_description }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Room Capacity Checker --}}
                        @if($rooms->count() > 0)
                            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-6" x-data="{ showChecker: false }">
                                <button @click="showChecker = !showChecker" class="flex items-center justify-between w-full text-left">
                                    <span class="text-sm font-medium text-blue-900 dark:text-blue-100">{{ __('Room Capacity Checker') }}</span>
                                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-300 transition-transform" :class="showChecker ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                
                                <div x-show="showChecker" x-transition class="mt-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
                                    <form action="/test" method="GET" class="contents">
                                        <input type="number" name="people" placeholder="Nb personnes" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <select name="activity" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="training">{{ __('Training') }}</option>
                                            <option value="match">{{ __('Match') }}</option>
                                            <option value="unknown">{{ __('Unknown') }}</option>
                                        </select>
                                        <select name="room_id" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            @foreach($rooms as $room)
                                                <option value="{{ $room->id }}">{{ $room->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                                            {{ __('Test') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endif

                        {{-- Actions --}}
                        @if(Auth()->user()->is_committee_member || Auth()->user()->is_admin)
                        <div class="flex flex-col sm:flex-row gap-3">
                            <form action="" method="GET" class="flex-1">
                                <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    {{ __('Create new room') }}
                                </button>
                            </form>
                            <form action="{{ route('rooms.index') }}" method="GET" class="flex-1">
                                <button type="submit" class="w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    {{ __('Manage rooms') }}
                                </button>
                            </form>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Training Management --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                                    <svg class="w-6 h-6 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Trainings') }}</h3>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        @if($trainings->count() == 0)
                            <div class="text-center py-8">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <p class="text-gray-500 dark:text-gray-400">{{ __('It seems that no trainings have been defined. Start by creating a new training session.') }}</p>
                            </div>
                        @else
                            <div class="space-y-3 mb-6">
                                {{-- Example training session since data structure isn't clear --}}
                                <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium text-gray-900 dark:text-gray-100">Demeester/-1</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Saturday - 09:00-10:30</p>
                                        </div>
                                        <span class="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 px-2 py-1 rounded-full text-xs font-medium">
                                            Directed
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Actions --}}
                        @if(Auth()->user()->is_committee_member || Auth()->user()->is_admin)
                        <div class="flex flex-col sm:flex-row gap-3">
                            <form action="{{ route('trainings.create') }}" method="GET" class="flex-1">
                                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                    </svg>
                                    {{ __('Create a new training') }}
                                </button>
                            </form>
                            <form action="{{ route('trainings.index') }}" method="GET" class="flex-1">
                                <button type="submit" class="w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    {{ __('Manage trainings') }}
                                </button>
                            </form>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Section Matches --}}
    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg">
                <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="p-2 bg-red-100 dark:bg-red-900 rounded-lg">
                                <svg class="w-6 h-6 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Matches') }}</h3>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    {{-- Match count info --}}
                    <div class="mb-6">
                        <p class="text-gray-600 dark:text-gray-400">{{ __('There are currently') }} <span class="font-semibold text-gray-900 dark:text-gray-100">1</span> {{ __('upcoming matches') }}.</p>
                    </div>

                    {{-- Upcoming matches table --}}
                    <div class="overflow-x-auto mb-6">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="text-left py-3 font-medium text-gray-500 dark:text-gray-400">#</th>
                                    <th class="text-left py-3 font-medium text-gray-500 dark:text-gray-400">{{ __('Match') }}</th>
                                    <th class="text-left py-3 font-medium text-gray-500 dark:text-gray-400">{{ __('Division') }}</th>
                                    <th class="text-left py-3 font-medium text-gray-500 dark:text-gray-400">{{ __('Team') }}</th>
                                    <th class="text-left py-3 font-medium text-gray-500 dark:text-gray-400">{{ __('Captain') }}</th>
                                    <th class="text-left py-3 font-medium text-gray-500 dark:text-gray-400">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-900 dark:text-gray-100">
                                <tr class="border-b border-gray-100 dark:border-gray-700">
                                    <td class="py-4">1</td>
                                    <td class="py-4 font-medium">BBW114 vs BBW210</td>
                                    <td class="py-4">PROV-1</td>
                                    <td class="py-4">A</td>
                                    <td class="py-4">Jean Dupont</td>
                                    <td class="py-4">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-2 h-2 bg-yellow-400 rounded-full animate-pulse"></div>
                                            <span class="text-yellow-600 dark:text-yellow-400 text-sm">{{ __('Pending') }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Actions --}}
                    @if(Auth()->user()->is_committee_member || Auth()->user()->is_admin)
                    <div class="flex flex-col sm:flex-row gap-3">
                        <form action="" method="GET" class="flex-1">
                            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                {{ __('Create new match') }}
                            </button>
                        </form>
                        <form action="" method="GET" class="flex-1">
                            <button type="submit" class="w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                {{ __('Manage matches') }}
                            </button>
                        </form>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</x-app-layout>