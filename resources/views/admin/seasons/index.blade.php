<x-app-layout :breadcrumbs="$breadcrumbs">
    <div x-data="{ showStats: false, showSubscribeModal: false, selectedSeason: null }">
        <x-admin-block>
            <!-- En-tête de page avec actions -->
            <div class="bg-white rounded-lg shadow-lg p-3 sm:p-4 lg:p-6 mb-4 sm:mb-6">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start space-y-3 sm:space-y-0">
                    <div>
                        <h2 class="text-lg sm:text-xl lg:text-2xl font-bold text-club-blue mb-1 sm:mb-2">{{ __('Seasons') }}</h2>
                        <p class="text-gray-600 text-xs sm:text-sm lg:text-base">{{ __('Manage club seasons and member subscriptions.') }}</p>
                    </div>
        
                    <!-- Menu d'actions optimisé -->
                    <div class="flex flex-col xs:flex-row space-y-2 xs:space-y-0 xs:space-x-2 lg:space-x-3">
                        @can('create', App\Models\Season::class)
                            <a href="{{ route('admin.seasons.create') }}"
                               class="bg-club-blue hover:bg-club-blue-light text-white px-3 py-1.5 lg:px-4 lg:py-2 rounded-lg font-medium transition-colors text-xs sm:text-sm lg:text-base w-full xs:w-auto text-center flex items-center justify-center">
                                <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1.5 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                <span class="hidden sm:inline">{{ __('Create new season') }}</span>
                                <span class="sm:hidden">{{ __('New') }}</span>
                            </a>
                        @endcan
                        
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
                        <div class="text-base sm:text-xl lg:text-2xl font-bold text-club-yellow">{{ $seasons->count() }}</div>
                        <div class="text-xs sm:text-sm text-club-yellow font-medium">{{ __('Total Seasons') }}</div>
                    </div>
                    <div class="bg-club-yellow rounded-lg p-2 sm:p-4 text-center border border-club-yellow">
                        <div class="text-base sm:text-xl lg:text-2xl font-bold text-club-blue">{{ $seasons->sum(fn($s) => $s->users()->count()) }}</div>
                        <div class="text-xs sm:text-sm text-club-blue font-medium">{{ __('Total Subscriptions') }}</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-2 sm:p-4 text-center border border-green-200">
                        <div class="text-base sm:text-xl lg:text-2xl font-bold text-green-600">
                            {{ $seasons->where('end_at', '>=', now())->count() }}
                        </div>
                        <div class="text-xs sm:text-sm text-green-700 font-medium">{{ __('Active Seasons') }}</div>
                    </div>
                    <div class="bg-gray-100 rounded-lg p-2 sm:p-4 text-center border border-gray-300">
                        <div class="text-base sm:text-xl lg:text-2xl font-bold text-gray-600">
                            {{ $seasons->where('end_at', '<', now())->count() }}
                        </div>
                        <div class="text-xs sm:text-sm text-gray-600 font-medium">{{ __('Past Seasons') }}</div>
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
                            <p class="text-xs sm:text-sm font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Conteneur des saisons -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                @if ($seasons->count() == 0)
                    <!-- État vide -->
                    <div class="text-center py-8 sm:py-12 px-4">
                        <div class="mx-auto w-16 h-16 sm:w-24 sm:h-24 bg-gray-100 rounded-full flex items-center justify-center mb-3 sm:mb-4">
                            <svg class="w-8 h-8 sm:w-12 sm:h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">{{ __('No seasons found') }}</h3>
                        <p class="text-gray-600 mb-4 sm:mb-6 text-sm sm:text-base">{{ __('Start by creating your first season to manage subscriptions.') }}</p>
                        @can('create', App\Models\Season::class)
                            <a href="{{ route('admin.seasons.create') }}"
                               class="bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 sm:px-6 sm:py-3 rounded-lg font-medium transition-colors inline-flex items-center text-sm sm:text-base">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                {{ __('Create your first season') }}
                            </a>
                        @endcan
                    </div>
                @else
                    <!-- Version desktop : Table -->
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Season Name') }}
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Period') }}
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Status') }}
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Subscriptions') }}
                                    </th>
                                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Actions') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($seasons as $season)
                                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                                        <!-- Nom de la saison -->
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ $season->name }}</div>
                                        </td>
                                        
                                        <!-- Période -->
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex items-center text-sm text-gray-500">
                                                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                <span>{{ $season->start_at->format('d/m/Y') }} - {{ $season->end_at->format('d/m/Y') }}</span>
                                            </div>
                                        </td>
                                        
                                        <!-- Status -->
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            @if($season->end_at >= now())
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    {{ __('Active') }}
                                                </span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                    {{ __('Finished') }}
                                                </span>
                                            @endif
                                        </td>
                                        
                                        <!-- Inscriptions -->
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 w-8 h-8 bg-club-blue bg-opacity-10 rounded-full flex items-center justify-center mr-2">
                                                    <svg class="w-4 h-4 text-club-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <span class="text-sm font-medium text-gray-900">{{ $season->users()->count() }}</span>
                                                    <span class="text-xs text-gray-500 ml-1">{{ __('members') }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Actions -->
                                        <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center justify-end space-x-2">
                                                @can('subscribe', $season)
                                                    <button @click="selectedSeason = {{ $season->id }}; showSubscribeModal = true"
                                                            class="bg-green-600 hover:bg-green-700 text-white p-2 rounded-lg transition-colors duration-200"
                                                            title="{{ __('Subscribe a member') }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                                        </svg>
                                                    </button>
                                                @endcan
                                                
                                                <a href="{{ route('admin.seasons.show', $season) }}"
                                                   class="bg-club-blue hover:bg-club-blue-light text-white p-2 rounded-lg transition-colors duration-200"
                                                   title="{{ __('View details') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                </a>
                                                
                                                @can('update', $season)
                                                    <a href="{{ route('admin.seasons.edit', $season) }}"
                                                       class="bg-yellow-600 hover:bg-yellow-700 text-white p-2 rounded-lg transition-colors duration-200"
                                                       title="{{ __('Edit season') }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                        </svg>
                                                    </a>
                                                @endcan
                                                
                                                @can('delete', $season)
                                                    <form action="{{ route('admin.seasons.destroy', $season) }}" method="POST" class="inline">
                                                        @csrf
                                                        @method('delete')
                                                        <button type="submit" 
                                                                onclick="return confirm('{{ __('Are you sure you want to delete this season?') }}')"
                                                                class="bg-red-600 hover:bg-red-700 text-white p-2 rounded-lg transition-colors duration-200"
                                                                title="{{ __('Delete season') }}">
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

                    <!-- Version mobile et tablette : Cards modernes -->
                    <div class="lg:hidden">
                        @foreach ($seasons as $season)
                            <div class="border-b border-gray-200 last:border-b-0">
                                <div class="p-4 space-y-3">
                                    <!-- Header: Nom + Badge Status -->
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex-1 min-w-0">
                                            <h3 class="font-semibold text-gray-900 text-base leading-tight">
                                                {{ $season->name }}
                                            </h3>
                                        </div>
                                        
                                        @if($season->end_at >= now())
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 flex-shrink-0">
                                                {{ __('Active') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 flex-shrink-0">
                                                {{ __('Finished') }}
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Infos: Dates + Membres -->
                                    <div class="flex items-center gap-4 text-xs text-gray-500">
                                        <!-- Dates -->
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            <span class="whitespace-nowrap">{{ $season->start_at->format('d/m/y') }} - {{ $season->end_at->format('d/m/y') }}</span>
                                        </div>

                                        <!-- Membres -->
                                        <div class="flex items-center gap-1.5">
                                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                            </svg>
                                            <span class="font-medium text-gray-900">{{ $season->users()->count() }}</span>
                                            <span>{{ __('members') }}</span>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex items-center gap-2 pt-1">
                                        @can('subscribe', $season)
                                            <button @click="selectedSeason = {{ $season->id }}; showSubscribeModal = true"
                                                    class="flex-1 inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg font-medium text-sm transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                                                </svg>
                                                <span>{{ __('Subscribe') }}</span>
                                            </button>
                                        @endcan
                                        
                                        <a href="{{ route('admin.seasons.show', $season) }}" 
                                           class="flex-1 inline-flex items-center justify-center gap-2 bg-club-blue hover:bg-club-blue-light text-white px-3 py-2 rounded-lg font-medium text-sm transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            <span>{{ __('View') }}</span>
                                        </a>

                                        <!-- Menu dropdown -->
                                        <div x-data="{ open: false }" class="relative" @click.away="open = false">
                                            <button @click="open = !open" 
                                                    class="inline-flex items-center justify-center bg-gray-100 hover:bg-gray-200 text-gray-700 p-2 rounded-lg transition-colors">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                                </svg>
                                            </button>
                                            
                                            <div x-show="open" 
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="opacity-0 scale-95"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 x-transition:leave="transition ease-in duration-75"
                                                 x-transition:leave-start="opacity-100 scale-100"
                                                 x-transition:leave-end="opacity-0 scale-95"
                                                 class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-200 overflow-hidden z-20"
                                                 style="display: none;">
                                                
                                                @can('update', $season)
                                                    <a href="{{ route('admin.seasons.edit', $season) }}" 
                                                       class="flex items-center gap-3 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 transition-colors border-b border-gray-100">
                                                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                        </svg>
                                                        <span class="font-medium">{{ __('Edit') }}</span>
                                                    </a>
                                                @endcan

                                                @can('delete', $season)
                                                    <form action="{{ route('admin.seasons.destroy', $season) }}" method="POST">
                                                        @csrf
                                                        @method('delete')
                                                        <button type="submit" 
                                                                onclick="return confirm('{{ __('Are you sure you want to delete this season?') }}')"
                                                                class="w-full flex items-center gap-3 px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                            </svg>
                                                            <span class="font-medium">{{ __('Delete') }}</span>
                                                        </button>
                                                    </form>
                                                @endcan
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Pagination -->
            @if($seasons->hasPages())
                <div class="bg-white rounded-lg shadow-lg mt-4 sm:mt-6 px-3 py-3 sm:px-6 sm:py-4">
                    {{ $seasons->links() }}
                </div>
            @endif
        </x-admin-block>

        <!-- Modal d'inscription d'un membre (commun à toutes les saisons) -->
        <div x-show="showSubscribeModal" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50" 
             style="display: none;">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div x-show="showSubscribeModal"
                     @click.outside="showSubscribeModal = false"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-95"
                     class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md mx-auto">
                    
                    <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-club-blue bg-opacity-10 rounded-full">
                        <svg class="w-6 h-6 text-club-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                    </div>
                    
                    <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">
                        {{ __('Subscribe a member') }}
                    </h3>
                    
                    <p class="text-sm text-gray-600 text-center mb-6">
                        {{ __('Select a member and subscription type') }}
                    </p>
                    
                    <form action="" method="POST" class="space-y-4" x-ref="subscribeForm">
                        @csrf
                        <input type="hidden" name="season_id" x-model="selectedSeason">
                        
                        <!-- Sélection du membre -->
                        <div>
                            <x-input-label for="user_id" :value="__('Member')" />
                            <select name="user_id" 
                                    id="user_id" 
                                    class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-club-blue focus:ring focus:ring-club-blue focus:ring-opacity-50"
                                    required>
                                <option value="" disabled selected>{{ __('Select a member') }}</option>
                                @foreach($users ?? [] as $user)
                                    <option value="{{ $user->id }}">{{ $user->fullName }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <!-- Type d'inscription -->
                        <div>
                            <x-input-label :value="__('Subscription type')" class="mb-3" />
                            <div class="space-y-3">
                                <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" 
                                           name="type" 
                                           value="casual" 
                                           checked
                                           class="text-club-blue focus:ring-club-blue">
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">{{ __('Casual') }}</div>
                                        <div class="text-xs text-gray-500">{{ __('For non-competitive members') }}</div>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" 
                                           name="type" 
                                           value="competitive"
                                           class="text-club-blue focus:ring-club-blue">
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">{{ __('Competitive') }}</div>
                                        <div class="text-xs text-gray-500">{{ __('For interclub members') }}</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Boutons d'action -->
                        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3 pt-4">
                            <button type="button" 
                                    @click="showSubscribeModal = false" 
                                    class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                                {{ __('Cancel') }}
                            </button>
                            <button type="button"
                                    @click="
                                        $refs.subscribeForm.action = '{{ route('admin.seasons.index') }}/' + selectedSeason + '/subscribe';
                                        $refs.subscribeForm.submit();
                                    "
                                    class="flex-1 bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                                {{ __('Subscribe') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>