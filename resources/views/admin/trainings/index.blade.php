<x-app-layout :breadcrumbs="$breadcrumbs">
    <div x-data="{ showStats: false, showFilters: false }">
        <x-admin-block>
            <!-- En-tête de page avec actions -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start space-y-4 sm:space-y-0">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">{{ __('Trainings') }}</h2>
                        <p class="text-gray-600 text-sm sm:text-base">{{ __('Manage training sessions and schedules for your club members.') }}</p>
                    </div>
        
                    <!-- Menu d'actions -->
                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                        <a href="{{ route('dashboard') }}"
                           class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto text-center flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2 2v10z"></path>
                            </svg>
                            {{ __('Dashboard') }}
                        </a>
                        
                        <a href="{{ route('trainings.create') }}"
                           class="bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto text-center flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            {{ __('Create new training') }}
                        </a>

                        <!-- Bouton toggle filtres -->
                        <button
                            @click="showFilters = !showFilters"
                            class="bg-club-yellow hover:bg-club-yellow-light text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto flex items-center justify-center"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                            </svg>
                            <span x-text="showFilters ? '{{ __('Hide Filters') }}' : '{{ __('Show Filters') }}'"></span>
                        </button>
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
                class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6"
            >
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Filter Trainings') }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Training Type') }}</label>
                        <select class="w-full rounded-lg border-gray-300 focus:border-club-blue focus:ring-club-blue">
                            <option>{{ __('All Types') }}</option>
                            <!-- Options dynamiques -->
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Level') }}</label>
                        <select class="w-full rounded-lg border-gray-300 focus:border-club-blue focus:ring-club-blue">
                            <option>{{ __('All Levels') }}</option>
                            <!-- Options dynamiques -->
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Date Range') }}</label>
                        <input type="date" class="w-full rounded-lg border-gray-300 focus:border-club-blue focus:ring-club-blue">
                    </div>
                    <div class="flex items-end">
                        <button class="w-full bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 rounded-lg font-medium transition-colors">
                            {{ __('Apply Filters') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Notifications -->
            @if (session('success'))
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">
                                {{ session('success') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            @if (session('deleted'))
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">
                                {{ session('deleted') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Table des entraînements -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                @if ($trainings->count() == 0)
                    <!-- État vide -->
                    <div class="text-center py-12">
                        <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 00-2 2v2a2 2 0 002 2m0 0h14m-14 0a2 2 0 002 2v2a2 2 0 01-2 2M7 7V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('No trainings scheduled') }}</h3>
                        <p class="text-gray-600 mb-6">{{ __('It seems that no trainings have been defined. Start by creating a new training session.') }}</p>
                        <a href="{{ route('trainings.create') }}"
                           class="bg-club-blue hover:bg-club-blue-light text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            {{ __('Create your first training') }}
                        </a>
                    </div>
                @else
                    <!-- Table responsive -->
                    <div class="overflow-x-auto">
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
                                        {{ __('Capacity') }}
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
                                                <div class="flex-shrink-0 w-8 h-8 bg-club-blue bg-opacity-10 rounded-full flex items-center justify-center mr-3">
                                                    <svg class="w-4 h-4 text-club-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    </svg>
                                                </div>
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
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-club-yellow bg-opacity-20 text-club-yellow">
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
                                                <span class="text-sm font-medium text-gray-900">{{ $training->room->capacity_for_trainings }}</span>
                                                <span class="text-xs text-gray-500 ml-1">{{ __('places') }}</span>
                                            </div>
                                        </td>
                                        
                                        <!-- Actions -->
                                        <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center justify-end space-x-2">
                                                <a href="{{ route('trainings.edit', $training->id) }}"
                                                   class="text-club-blue hover:text-club-blue-light transition-colors p-1 rounded">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                </a>
                                                <form action="{{ route('trainings.destroy', $training->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('delete')
                                                    <button type="submit" 
                                                            onclick="return confirm('{{ __('Are you sure you want to delete this training?') }}')"
                                                            class="text-red-600 hover:text-red-800 transition-colors p-1 rounded">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <!-- Pagination -->
            @if($trainings->hasPages())
                <div class="bg-white rounded-lg shadow-lg mt-6 px-6 py-4">
                    {{ $trainings->links() }}
                </div>
            @endif
        </x-admin-block>
    </div>
</x-app-layout>