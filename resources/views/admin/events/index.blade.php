<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <!-- En-tête de page -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-4 space-y-4 sm:space-y-0">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">{{ __('Events management') }}</h2>
                    <p class="text-gray-600 text-sm sm:text-base">{{ __('Create and manage club events here.') }}</p>
                </div>
                
                <!-- Bouton d'ajout -->
                <div>
                    <a href="{{ route('admin.events.create') }}" 
                       class="bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 rounded-lg font-medium transition-colors inline-flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Nouvel événement
                    </a>
                </div>
            </div>

            <!-- Statistiques rapides -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-4">
                <div class="bg-gray-50 rounded-lg p-3 text-center border border-gray-200">
                    <div class="text-lg sm:text-xl font-bold text-gray-600">{{ $stats->get('totalDrafts') }}</div>
                    <div class="text-xs text-gray-700">Brouillons</div>
                </div>
                <div class="bg-green-50 rounded-lg p-3 text-center border border-green-200">
                    <div class="text-lg sm:text-xl font-bold text-green-600">{{ $stats->get('totalPublished') }}</div>
                    <div class="text-xs text-green-700">Publiés</div>
                </div>
                <div class="bg-red-50 rounded-lg p-3 text-center border border-red-200">
                    <div class="text-lg sm:text-xl font-bold text-red-600">{{ $stats->get('totalArchived') }}</div>
                    <div class="text-xs text-red-700">Archivés</div>
                </div>
                <div class="bg-blue-50 rounded-lg p-3 text-center border border-blue-200">
                    <div class="text-lg sm:text-xl font-bold text-blue-600">{{ $stats->get('totalUpcoming') }}</div>
                    <div class="text-xs text-blue-700">À venir</div>
                </div>
            </div>
        </div>

        <!-- Barre de filtres -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <form method="GET" class="flex flex-col lg:flex-row lg:justify-between lg:items-center space-y-4 lg:space-y-0 lg:space-x-4">
                <!-- Recherche -->
                <div class="flex-1 max-w-md">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input type="text" 
                               name="search" 
                               value="{{ request('search') }}"
                               class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-club-blue focus:border-club-blue text-sm"
                               placeholder="{{ __('Search events...') }}">
                    </div>
                </div>

                <!-- Filtres -->
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                    <!-- Statut -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Status') }}</label>
                        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-club-blue focus:border-club-blue">
                            <option value="">{{ __('All') }}</option>
                            @foreach(\App\Models\Event::STATUSES as $key => $label)
                                <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Catégorie -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Category') }}</label>
                        <select name="category" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-club-blue focus:border-club-blue">
                            <option value="">{{ __('All') }}</option>
                            @foreach(\App\Models\Event::CATEGORIES as $key => $label)
                                <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Pagination -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Per page') }}</label>
                        <select name="perPage" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-club-blue focus:border-club-blue">
                            <option value="25" {{ request('perPage', 25) == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ request('perPage', 25) == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ request('perPage', 25) == 100 ? 'selected' : '' }}>100</option>
                        </select>
                    </div>

                    <button type="submit" class="bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 rounded-lg text-sm font-medium">
                        Filtrer
                    </button>
                </div>
            </form>
        </div>

        <!-- Table des événements -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- En-tête responsive -->
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-medium text-gray-900">
                    {{ __('Events') }} 
                    <span class="text-sm font-normal text-gray-500">({{ $events->total() }} résultats)</span>
                </h3>
            </div>

            <!-- Version desktop -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Event') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Date & Time') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Location') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Category') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Status') }}
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($events as $event)
                            <tr class="hover:bg-gray-50 transition-colors duration-200">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-club-blue flex items-center justify-center text-lg">
                                                {{ $event->icon }}
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $event->title }}
                                                @if($event->featured)
                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                                                        ⭐ Mis en avant
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-sm text-gray-500 truncate max-w-xs">
                                                {{ Str::limit($event->description, 60) }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $event->formatted_date }}</div>
                                    <div class="text-sm text-gray-500">{{ $event->formatted_time }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $event->location }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="{{ $event->getCategoryBadgeClasses() }} px-2 py-1 rounded-full text-xs font-medium">
                                        {{ $event->category_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="{{ $event->getStatusBadgeClasses() }} px-2 py-1 rounded-full text-xs font-medium">
                                        {{ $event->status_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <!-- Actions rapides de statut -->
                                        @if($event->status === 'draft')
                                            <form action="{{ route('admin.events.publish', $event) }}" method="POST" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" 
                                                        class="bg-green-600 hover:bg-green-700 text-white p-1 rounded transition-colors duration-200"
                                                        title="Publier">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif

                                        @if($event->status === 'published')
                                            <form action="{{ route('admin.events.archive', $event) }}" method="POST" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" 
                                                        class="bg-orange-600 hover:bg-orange-700 text-white p-1 rounded transition-colors duration-200"
                                                        title="Archiver">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8l6 6 6-6"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif

                                        <!-- Voir -->
                                        <a href="{{ route('admin.events.show', $event) }}" 
                                           class="bg-club-blue hover:bg-club-blue-light text-white p-1 rounded transition-colors duration-200"
                                           title="{{ __('View details') }}">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </a>

                                        <!-- Modifier -->
                                        <a href="{{ route('admin.events.edit', $event) }}" 
                                           class="bg-yellow-600 hover:bg-yellow-700 text-white p-1 rounded transition-colors duration-200"
                                           title="Modifier">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>

                                        <!-- Dupliquer -->
                                        <form action="{{ route('admin.events.duplicate', $event) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" 
                                                    class="bg-blue-600 hover:bg-blue-700 text-white p-1 rounded transition-colors duration-200"
                                                    title="Dupliquer">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                </svg>
                                            </button>
                                        </form>

                                        <!-- Supprimer (seulement si possible) -->
                                        @if($event->canBeDeleted())
                                            <form action="{{ route('admin.events.destroy', $event) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="bg-red-600 hover:bg-red-700 text-white p-1 rounded transition-colors duration-200"
                                                        title="Supprimer"
                                                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ?')">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h8a2 2 0 012 2v4m-6 12h8a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900">Aucun événement trouvé</h3>
                                        <p class="mt-1 text-sm text-gray-500">Commencez par créer un nouvel événement.</p>
                                        <div class="mt-6">
                                            <a href="{{ route('admin.events.create') }}" 
                                               class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-club-blue hover:bg-club-blue-light">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                </svg>
                                                Nouvel événement
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Version mobile -->
            <div class="lg:hidden">
                <div class="space-y-0">
                    @forelse ($events as $event)
                        <div class="border-b border-gray-200 p-4 hover:bg-gray-50 transition-colors duration-200">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-club-blue flex items-center justify-center text-lg">
                                            {{ $event->icon }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $event->title }}
                                            @if($event->featured)
                                                <span class="ml-1 text-yellow-500">⭐</span>
                                            @endif
                                        </div>
                                        <div class="text-sm text-gray-500">{{ $event->location }}</div>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col space-y-1">
                                    <span class="{{ $event->getStatusBadgeClasses() }} px-2 py-1 rounded-full text-xs font-medium text-center">
                                        {{ $event->status_label }}
                                    </span>
                                    <span class="{{ $event->getCategoryBadgeClasses() }} px-2 py-1 rounded-full text-xs font-medium text-center">
                                        {{ $event->category_label }}
                                    </span>
                                </div>
                            </div>
                            
                            <div class="space-y-2 mb-3">
                                <div class="text-sm text-gray-600">
                                    {{ Str::limit($event->description, 100) }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    📅 {{ $event->formatted_date }} • ⏰ {{ $event->formatted_time }}
                                </div>
                                @if($event->price)
                                    <div class="text-sm text-gray-500">
                                        💰 {{ $event->price }}
                                    </div>
                                @endif
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <a href="{{ route('admin.events.show', $event) }}" 
                                   class="bg-club-blue hover:bg-club-blue-light text-white px-3 py-1 rounded text-xs font-medium transition-colors">
                                    Voir
                                </a>
                                <a href="{{ route('admin.events.edit', $event) }}" 
                                   class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded text-xs font-medium transition-colors">
                                    Modifier
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h8a2 2 0 012 2v4m-6 12h8a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Aucun événement trouvé</h3>
                            <p class="mt-1 text-sm text-gray-500">Essayez de modifier vos critères de recherche.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Pagination -->
        @if($events->hasPages())
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mt-6">
                {{ $events->appends(request()->query())->links() }}
            </div>
        @endif
    </x-admin-block>
</x-app-layout>