<x-app-layout :breadcrumbs="$breadcrumbs">
    <div x-data="{ showStats: false, showFilters: false }">
        <x-admin-block>
            <!-- En-tête de page avec actions -->
            <div class="bg-white rounded-lg shadow-lg p-3 sm:p-4 lg:p-6 mb-4 sm:mb-6">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start space-y-3 sm:space-y-0">
                    <div>
                        <h2 class="text-lg sm:text-xl lg:text-2xl font-bold text-club-blue mb-1 sm:mb-2">{{ __('Trainings') }}</h2>
                        <p class="text-gray-600 text-xs sm:text-sm lg:text-base">{{ __('Manage training sessions and schedules for your club members.') }}</p>
                    </div>
        
                    <!-- Menu d'actions optimisé -->
                    <div class="flex flex-col xs:flex-row space-y-2 xs:space-y-0 xs:space-x-2 lg:space-x-3">
                        <a href="{{ route('trainings.create') }}"
                           class="bg-club-blue hover:bg-club-blue-light text-white px-3 py-1.5 lg:px-4 lg:py-2 rounded-lg font-medium transition-colors text-xs sm:text-sm lg:text-base w-full xs:w-auto text-center flex items-center justify-center">
                            <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1.5 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <span class="hidden sm:inline">{{ __('Create new training') }}</span>
                            <span class="sm:hidden">{{ __('New') }}</span>
                        </a>
                        
                        <!-- Bouton toggle statistiques -->
                        <button
                            @click="showStats = !showStats"
                            class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1.5 lg:px-4 lg:py-2 rounded-lg font-medium transition-colors text-xs sm:text-sm lg:text-base w-full xs:w-auto flex items-center justify-center"
                        >
                            <svg
                                x-bind:class="showStats ? 'rotate-180' : 'rotate-0'"
                                class="w-3 h-3 sm:w-4 sm:h-4 mr-1.5 sm:mr-2 transition-transform duration-200"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            <span class="hidden sm:inline" x-text="showStats ? '{{ __('Hide Statistics') }}' : '{{ __('Show Statistics') }}'"></span>
                            <span class="sm:hidden" x-text="showStats ? '{{ __('Stats') }}' : '{{ __('Stats') }}'"></span>
                        </button>
                        
                        <!-- Bouton toggle filtres -->
                        <button
                            @click="showFilters = !showFilters"
                            class="bg-club-yellow hover:bg-club-yellow-light text-gray-700 px-3 py-1.5 lg:px-4 lg:py-2 rounded-lg font-medium transition-colors text-xs sm:text-sm lg:text-base w-full xs:w-auto flex items-center justify-center"
                        >
                            <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1.5 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                            </svg>
                            <span class="hidden sm:inline" x-text="showFilters ? '{{ __('Hide Filters') }}' : '{{ __('Show Filters') }}'"></span>
                            <span class="sm:hidden" x-text="showFilters ? '{{ __('Filters') }}' : '{{ __('Filters') }}'"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Bloc des statistiques avec transition -->
            <div
                x-show="showStats"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform -translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 transform translate-y-0"
                x-transition:leave-end="opacity-0 transform -translate-y-2"
                class="bg-white rounded-lg shadow-lg p-3 sm:p-4 lg:p-6 mb-4 sm:mb-6"
            >
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-4">
                    <div class="bg-club-blue bg-opacity-10 rounded-lg p-2 sm:p-4 text-center border border-club-blue border-opacity-20">
                        <div class="text-base sm:text-xl lg:text-2xl font-bold text-club-yellow">{{ $stats?->get('totalActiveUsers') ?? 0 }}</div>
                        <div class="text-xs sm:text-sm text-club-yellow font-medium">{{ __('Total Trainings') }}</div>
                    </div>
                    <div class="bg-club-yellow rounded-lg p-2 sm:p-4 text-center border border-club-yellow">
                        <div class="text-base sm:text-xl lg:text-2xl font-bold text-club-blue">{{ $stats?->get('totalCompetitors') ?? 0 }}</div>
                        <div class="text-xs sm:text-sm text-club-blue font-medium">{{ __('Total hours') }}</div>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-2 sm:p-4 text-center border border-blue-200">
                        <div class="text-base sm:text-xl lg:text-2xl font-bold text-blue-600">{{ $stats?->get('totalUsersCreatedLastYear') ?? 0 }}</div>
                        <div class="text-xs sm:text-sm text-blue-700 font-medium">{{ __('Total Directed Trainings') }}</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-2 sm:p-4 text-center border border-yellow-200">
                        <div class="text-base sm:text-xl lg:text-2xl font-bold text-yellow-600">{{ $stats?->get('totalUnpaidUsers') ?? 0 }}</div>
                        <div class="text-xs sm:text-sm text-yellow-700 font-medium">{{ __('Average occupation') }}</div>
                    </div>
                    <!-- Statistiques secondaires masquées sur mobile -->
                    <div class="hidden sm:block bg-gray-100 rounded-lg p-2 sm:p-4 text-center border border-gray-300">
                        <div class="text-base sm:text-xl lg:text-2xl font-bold text-gray-600">{{ $stats?->get('totalUnderagedUsers') ?? 0 }}</div>
                        <div class="text-xs sm:text-sm text-gray-600 font-medium">{{ __('Young people') }}</div>
                    </div>
                    <div class="hidden sm:block bg-gray-100 rounded-lg p-2 sm:p-4 text-center border border-gray-300">
                        <div class="text-base sm:text-xl lg:text-2xl font-bold text-gray-600">{{ $stats?->get('totalWomen') ?? 0 }}</div>
                        <div class="text-xs sm:text-sm text-gray-600 font-medium">{{ __('Women') }}</div>
                    </div>
                    <div class="hidden sm:block bg-gray-100 rounded-lg p-2 sm:p-4 text-center border border-gray-300">
                        <div class="text-base sm:text-xl lg:text-2xl font-bold text-gray-600">{{ $stats?->get('totalMen') ?? 0 }}</div>
                        <div class="text-xs sm:text-sm text-gray-600 font-medium">{{ __('Men') }}</div>
                    </div>
                    <div class="hidden sm:block bg-gray-100 rounded-lg p-2 sm:p-4 text-center border border-gray-300">
                        <div class="text-base sm:text-xl lg:text-2xl font-bold text-gray-600">{{ $stats?->get('totalVeterans') ?? 0 }}</div>
                        <div class="text-xs sm:text-sm text-gray-600 font-medium">{{ __('Veterans') }}</div>
                    </div>
                </div>
            </div>

            <!-- Bloc des filtres avec transition -->
            <div
                x-show="showFilters"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform -translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 transform translate-y-0"
                x-transition:leave-end="opacity-0 transform -translate-y-2"
                class="bg-white rounded-lg shadow-lg p-3 sm:p-4 lg:p-6 mb-4 sm:mb-6"
            >
                <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">{{ __('Filter Trainings') }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">{{ __('Training Type') }}</label>
                        <select class="w-full rounded-lg border-gray-300 focus:border-club-blue focus:ring-club-blue text-sm">
                            <option>{{ __('All Types') }}</option>
                            <!-- Options dynamiques -->
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">{{ __('Level') }}</label>
                        <select class="w-full rounded-lg border-gray-300 focus:border-club-blue focus:ring-club-blue text-sm">
                            <option>{{ __('All Levels') }}</option>
                            <!-- Options dynamiques -->
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">{{ __('Date Range') }}</label>
                        <input type="date" class="w-full rounded-lg border-gray-300 focus:border-club-blue focus:ring-club-blue text-sm">
                    </div>
                    <div class="flex items-end">
                        <button class="w-full bg-club-blue hover:bg-club-blue-light text-white px-3 py-2 rounded-lg font-medium transition-colors text-sm">
                            {{ __('Apply Filters') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Notifications -->
            @if (session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg p-3 sm:p-4 mb-4 sm:mb-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-2 sm:ml-3">
                            <p class="text-xs sm:text-sm font-medium text-green-800">
                                {{ session('success') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            @if (session('deleted'))
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 sm:p-4 mb-4 sm:mb-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-2 sm:ml-3">
                            <p class="text-xs sm:text-sm font-medium text-red-800">
                                {{ session('deleted') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif
            <!-- Conteneur des entraînements -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                @if ($trainings->count() == 0)
                    <!-- État vide -->
                    <div class="text-center py-8 sm:py-12 px-4">
                        <div class="mx-auto w-16 h-16 sm:w-24 sm:h-24 bg-gray-100 rounded-full flex items-center justify-center mb-3 sm:mb-4">
                            <svg class="w-8 h-8 sm:w-12 sm:h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 00-2 2v2a2 2 0 002 2m0 0h14m-14 0a2 2 0 002 2v2a2 2 0 01-2 2M7 7V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                        </div>
                        <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">{{ __('No trainings scheduled') }}</h3>
                        <p class="text-gray-600 mb-4 sm:mb-6 text-sm sm:text-base">{{ __('It seems that no trainings have been defined. Start by creating a new training session.') }}</p>
                        <a href="{{ route('trainings.create') }}"
                           class="bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 sm:px-6 sm:py-3 rounded-lg font-medium transition-colors inline-flex items-center text-sm sm:text-base">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            {{ __('Create your first training') }}
                        </a>
                    </div>
                @else
                    <!-- Version desktop : Table -->
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Schedule') }}
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Location & Type') }}
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Details') }}
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Reservations and capacity') }}
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Actions') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($trainings as $training)
                                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                                        <!-- Schedule -->
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $training->start->format('l') }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ $training->start->format('d/m/Y') }}
                                            </div>
                                            <div class="text-xs text-gray-400">
                                                {{ $training->start->format('H:i') }} - {{ $training->end->format('H:i') }}
                                            </div>
                                        </td>
                                        
                                        <!-- Location & Type -->
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                            <div>
                                                    <div class="text-sm font-medium text-gray-900">{{ $training->room->name }}</div>
                                                    <div class="text-sm text-gray-500">{{ $training->type }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Details -->
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                @if($training->trainer)
                                                    <div class="flex items-center mb-1">
                                                        <svg class="w-4 h-4 text-gray-400 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                        </svg>
                                                        {{ $training->trainer->last_name }} {{ $training->trainer->first_name }}
                                                    </div>
                                                @endif
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-club-yellow bg-opacity-20 text-club-blue">
                                                    {{ $training->level }}
                                                </span>
                                            </div>
                                        </td>
                                        
                                        <!-- Capacity -->
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-2">
                                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                    </svg>
                                                </div>
                                                <span class="text-sm font-medium text-gray-900">{{ $training->trainees()->count() . '/' . $training->room->capacity_for_trainings }}</span>
                                                <span class="text-xs text-gray-500 ml-1">{{ __('places') }}</span>
                                            </div>
                                        </td>
                                        
                                        <!-- Actions -->
                                        <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center justify-end space-x-2">
                                                @if($training->trainees()->where('user_id', Auth()->id())->exists())
                                                {{-- User is registered - Show unregister button --}}
                                                <form action="{{ route('trainings.unregister', $training->id) }}" method="GET" class="inline">
                                                    @csrf
                                                    <button type="submit" 
                                                            onclick="return confirm('{{ __('Are you sure you want to unregister from this training?') }}')"
                                                            class="bg-red-600 hover:bg-red-700 text-white p-2 rounded-lg transition-colors duration-200"
                                                            title="{{ __('Unregister') }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                                @else
                                                {{-- User is not registered - Show register/waiting list buttons --}}
                                                    @if($training->room->capacity_for_trainings > $training->trainees()->count())
                                                    {{-- Subscription --}}
                                                    <form action="{{ route('trainings.register', $training->id) }}" method="GET" class="inline">
                                                        @csrf
                                                        <button type="submit"
                                                               class="bg-green-600 hover:bg-green-700 text-white p-2 rounded-lg transition-colors duration-200"
                                                               title="{{ __('Subscribe to training') }}">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                    @else
                                                    {{-- Waiting list --}}
                                                    <form action="#" method="POST" class="inline"> <!-- TODO -->
                                                        @csrf
                                                        <button type="submit"
                                                               class="bg-orange-600 hover:bg-orange-700 text-white p-2 rounded-lg transition-colors duration-200"
                                                               title="{{ __('Add to waiting list') }}">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                    @endif
                                                @endif
                                                <a href="{{ route('trainings.show', $training->id) }}"
                                                   class="bg-club-blue hover:bg-club-blue-light text-white p-2 rounded-lg transition-colors duration-200"
                                                   title="{{ __('View details') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                </a>
                                                @can('update', Auth()->user())
                                                <a href="{{ route('trainings.edit', $training->id) }}"
                                                   class="bg-yellow-600 hover:bg-yellow-700 text-white p-2 rounded-lg transition-colors duration-200"
                                                   title="{{ __('Edit training') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                </a>
                                                @endcan
                                                @can('delete', Auth()->user())
                                                <form action="{{ route('trainings.destroy', $training->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('delete')
                                                    <button type="submit" 
                                                            onclick="return confirm('{{ __('Are you sure you want to delete this training?') }}')"
                                                            class="bg-red-600 hover:bg-red-700 text-white p-2 rounded-lg transition-colors duration-200"
                                                            title="{{ __('Delete training') }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Version mobile et tablette : Cards simplifiées -->
                    <div class="lg:hidden">
                        @foreach ($trainings as $training)
                            <div class="border-b border-gray-200 p-4 hover:bg-gray-50 transition-colors duration-200">
                                <!-- Ligne principale : Date + Lieu + Actions -->
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex-1 min-w-0">
                                        <!-- Date et heure -->
                                        <div class="flex items-center space-x-3">
                                            <div class="text-sm font-semibold text-gray-900">
                                                {{ $training->start->format('d/m') }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{ $training->start->format('H:i') }}
                                            </div>
                                            <div class="text-xs text-gray-400">•</div>
                                            <div class="text-sm text-gray-700 truncate">
                                                {{ $training->room->name }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Actions principales -->
                                    <div class="flex items-center space-x-1 flex-shrink-0">
                                        @if($training->trainees()->where('user_id', Auth()->id())->exists())
                                        {{-- User is registered - Show unregister button --}}
                                        <form action="{{ route('trainings.unregister', $training->id) }}" method="GET" class="inline">
                                            @csrf
                                            <button type="submit" 
                                                    onclick="return confirm('{{ __('Are you sure you want to unregister?') }}')"
                                                    class="bg-red-600 hover:bg-red-700 text-white p-2 rounded-lg transition-colors duration-200"
                                                    title="{{ __('Unregister') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </form>
                                        @else
                                        {{-- User is not registered - Show register/waiting list buttons --}}
                                            @if($training->room->capacity_for_trainings > $training->trainees()->count())
                                            <!-- Inscription disponible -->
                                            <form action="{{ route('trainings.register', $training->id) }}" method="GET" class="inline">
                                                @csrf
                                                <button type="submit" 
                                                       class="bg-green-600 hover:bg-green-700 text-white p-2 rounded-lg transition-colors duration-200"
                                                       title="{{ __('Subscribe') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                            @else
                                            <!-- Liste d'attente -->
                                            <form action="#" method="POST" class="inline"> <!-- TODO -->
                                                @csrf
                                                <button type="submit" 
                                                       class="bg-orange-600 hover:bg-orange-700 text-white p-2 rounded-lg transition-colors duration-200"
                                                       title="{{ __('Waiting list') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                            @endif
                                        @endif

                                        <!-- Menu dropdown pour les autres actions -->
                                        <div x-data="{ open: false }" class="relative">
                                            <button @click="open = !open" 
                                                    class="bg-gray-600 hover:bg-gray-700 text-white p-2 rounded-lg transition-colors duration-200">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                                </svg>
                                            </button>
                                            
                                            <!-- Dropdown menu -->
                                            <div x-show="open" 
                                                 @click.away="open = false"
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="transform opacity-0 scale-95"
                                                 x-transition:enter-end="transform opacity-100 scale-100"
                                                 x-transition:leave="transition ease-in duration-75"
                                                 x-transition:leave-start="transform opacity-100 scale-100"
                                                 x-transition:leave-end="transform opacity-0 scale-95"
                                                 class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-10">
                                                
                                                <a href="{{ route('trainings.show', $training->id) }}" 
                                                   class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                    {{ __('View details') }}
                                                </a>

                                                @can('update', Auth()->user())
                                                <a href="{{ route('trainings.edit', $training->id) }}" 
                                                   class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    <svg class="w-4 h-4 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                    {{ __('Edit') }}
                                                </a>
                                                @endcan

                                                @can('delete', Auth()->user())
                                                <form action="{{ route('trainings.destroy', $training->id) }}" method="POST" class="block">
                                                    @csrf
                                                    @method('delete')
                                                    <button type="submit" 
                                                            onclick="return confirm('{{ __('Are you sure?') }}')"
                                                            class="w-full text-left flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                        {{ __('Delete') }}
                                                    </button>
                                                </form>
                                                @endcan
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Ligne secondaire : Informations essentielles -->
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex items-center space-x-4 text-gray-600">
                                        <!-- Type + Niveau -->
                                        <div class="flex items-center space-x-2">
                                            <span>{{ $training->type }}</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-club-yellow bg-opacity-20 text-club-blue">
                                                {{ $training->level }}
                                            </span>
                                        </div>
                                        
                                        <!-- Entraîneur -->
                                        @if($training->trainer)
                                        <div class="hidden sm:flex items-center">
                                            <svg class="w-3 h-3 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                            <span class="text-xs text-gray-500">{{ $training->trainer->last_name }}</span>
                                        </div>
                                        @endif
                                    </div>

                                    <!-- Capacité -->
                                    <div class="flex items-center space-x-1">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                        <span class="text-sm font-medium">
                                            {{ $training->trainees()->count() }}/{{ $training->room->capacity_for_trainings }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Pagination -->
            @if($trainings->hasPages())
                <div class="bg-white rounded-lg shadow-lg mt-4 sm:mt-6 px-3 py-3 sm:px-6 sm:py-4">
                    {{ $trainings->links() }}
                </div>
            @endif
        </x-admin-block>
    </div>
</x-app-layout>