<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <!-- En-tête de page -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-4 space-y-4 sm:space-y-0">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">{{ __('Articles management') }}</h2>
                    <p class="text-gray-600 text-sm sm:text-base">{{ __('Manage all your articles here.') }}</p>
                </div>
                
                <!-- Statistiques rapides -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-4">
                    <div class="bg-green-50 rounded-lg p-3 text-center border border-green-200">
                        <div class="text-lg sm:text-xl font-bold text-green-600">{{ $stats->get('totalPublished') }}</div>
                        <div class="text-xs text-green-700">Publiés</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-3 text-center border border-yellow-200">
                        <div class="text-lg sm:text-xl font-bold text-yellow-600">{{ $stats->get('totalDraft') }}</div>
                        <div class="text-xs text-yellow-700">Brouillons</div>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-3 text-center border border-blue-200">
                        <div class="text-lg sm:text-xl font-bold text-blue-600">{{ $stats->get('totalPublic') }}</div>
                        <div class="text-xs text-blue-700">Publics</div>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-3 text-center border border-purple-200">
                        <div class="text-lg sm:text-xl font-bold text-purple-600">{{ $stats->get('totalPrivate') }}</div>
                        <div class="text-xs text-purple-700">Privés</div>
                    </div>
                </div>
            </div>

            <!-- Bouton d'ajout -->
            <div class="flex justify-end">
                <a href="{{ route('admin.articles.create') }}" 
                   class="bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    {{ __('New article') }}
                </a>
            </div>
        </div>

        <!-- Barre de filtres -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center space-y-4 lg:space-y-0 lg:space-x-4">
                <!-- Recherche -->
                <div class="flex-1 max-w-md">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input 
                            type="text" 
                            wire:model.live.debounce.500ms="search"
                            class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-club-blue focus:border-club-blue text-sm"
                            placeholder="{{ __('Search articles...') }}">
                    </div>
                </div>

                <!-- Filtres -->
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                    <!-- Statut -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Status') }}</label>
                        <select wire:model.live="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-club-blue focus:border-club-blue">
                            <option value="">{{ __('All') }}</option>
                            <option value="published">{{ __('Published') }}</option>
                            <option value="draft">{{ __('Draft') }}</option>
                            <option value="archived">{{ __('Archived') }}</option>
                        </select>
                    </div>

                    <!-- Catégorie -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Category') }}</label>
                        <select wire:model.live="category" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-club-blue focus:border-club-blue">
                            <option value="">{{ __('All') }}</option>
                            @foreach(\App\Enums\ArticlesCategoryEnum::cases() as $categoryEnum)
                                <option value="{{ $categoryEnum->value }}">{{ $categoryEnum->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Visibilité -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Visibility') }}</label>
                        <select wire:model.live="visibility" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-club-blue focus:border-club-blue">
                            <option value="">{{ __('All') }}</option>
                            <option value="1">{{ __('Public') }}</option>
                            <option value="0">{{ __('Private') }}</option>
                        </select>
                    </div>

                    <!-- Pagination -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-700 whitespace-nowrap">{{ __('Per page') }}</label>
                        <select wire:model.live="perPage" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-club-blue focus:border-club-blue">
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table des articles -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- En-tête responsive -->
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg font-medium text-gray-900">
                    {{ __('Articles') }} 
                    <span class="text-sm font-normal text-gray-500">({{ $articles->total() }} résultats)</span>
                </h3>
            </div>

            <!-- Version desktop -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Article') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Category') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Author') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Date') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Status') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Visibility') }}
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($articles as $article)
                            <tr class="hover:bg-gray-50 transition-colors duration-200">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        @if($article->image)
                                            <div class="flex-shrink-0 h-12 w-12">
                                                <img class="h-12 w-12 rounded-lg object-cover" src="{{ asset('storage/' . $article->image) }}" alt="{{ $article->title }}">
                                            </div>
                                        @else
                                            <div class="flex-shrink-0 h-12 w-12">
                                                <div class="h-12 w-12 rounded-lg bg-gray-300 flex items-center justify-center">
                                                    <svg class="h-6 w-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                        @endif
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 max-w-xs">
                                                {{ Str::limit($article->title, 50) }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                {{ Str::limit(strip_tags($article->content), 80) }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ $article->category->name }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $article->user->first_name }} {{ $article->user->last_name }}</div>
                                    <div class="text-sm text-gray-500">{{ $article->user->email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $article->created_at->format('d/m/Y') }}
                                    <div class="text-xs text-gray-400">{{ $article->created_at->format('H:i') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusConfig = [
                                            'published' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'label' => 'Publié'],
                                            'draft' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'label' => 'Brouillon'],
                                            'archived' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'label' => 'Archivé'],
                                        ];
                                        $config = $statusConfig[$article->status->value] ?? $statusConfig['draft'];
                                    @endphp
                                    <span class="{{ $config['bg'] }} {{ $config['text'] }} px-2 py-1 rounded-full text-xs font-medium">
                                        {{ $config['label'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($article->is_public)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
                                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            Public
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd"></path>
                                                <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"></path>
                                            </svg>
                                            Privé
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <a href="{{ route('admin.articles.show', $article) }}" 
                                           class="bg-club-blue hover:bg-club-blue-light text-white p-2 rounded-lg transition-colors duration-200"
                                           title="{{ __('View details') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </a>
                                        <a href="{{ route('admin.articles.edit', $article) }}" 
                                           class="bg-yellow-600 hover:bg-yellow-700 text-white p-2 rounded-lg transition-colors duration-200"
                                           title="{{ __('Edit article') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>
                                        @can('delete', Auth()->user())
                                            <button 
                                                wire:click="$set('selectedArticleId', {{ $article->id }})"
                                                @click="$dispatch('open-modal', 'confirm-delete-article')"
                                                class="bg-red-600 hover:bg-red-700 text-white p-2 rounded-lg transition-colors duration-200"
                                                title="{{ __('Delete article') }}">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Version mobile -->
            <div class="lg:hidden">
                <div class="space-y-0">
                    @foreach ($articles as $article)
                        <div class="border-b border-gray-200 p-4 hover:bg-gray-50 transition-colors duration-200">
                            <div class="flex items-start space-x-3 mb-3">
                                @if($article->image)
                                    <div class="flex-shrink-0 h-16 w-16">
                                        <img class="h-16 w-16 rounded-lg object-cover" src="{{ asset('storage/' . $article->image) }}" alt="{{ $article->title }}">
                                    </div>
                                @else
                                    <div class="flex-shrink-0 h-16 w-16">
                                        <div class="h-16 w-16 rounded-lg bg-gray-300 flex items-center justify-center">
                                            <svg class="h-8 w-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                    </div>
                                @endif
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-900 mb-1">{{ Str::limit($article->title, 50) }}</h4>
                                    <p class="text-sm text-gray-600 mb-2">{{ Str::limit(strip_tags($article->content), 100) }}</p>
                                    <div class="flex flex-wrap gap-2 mb-2">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ $article->category->name }}
                                        </span>
                                        @php
                                            $statusConfig = [
                                                'published' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'label' => 'Publié'],
                                                'draft' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'label' => 'Brouillon'],
                                                'archived' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'label' => 'Archivé'],
                                            ];
                                            $config = $statusConfig[$article->status->value] ?? $statusConfig['draft'];
                                        @endphp
                                        <span class="{{ $config['bg'] }} {{ $config['text'] }} px-2 py-1 rounded-full text-xs font-medium">
                                            {{ $config['label'] }}
                                        </span>
                                        @if($article->is_public)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Public</span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Privé</span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        Par {{ $article->user->first_name }} {{ $article->user->last_name }} • {{ $article->created_at->format('d/m/Y à H:i') }}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-end space-x-2">
                                <a href="{{ route('admin.articles.show', $article) }}" 
                                   class="bg-club-blue hover:bg-club-blue-light text-white px-3 py-2 rounded-lg text-xs font-medium transition-colors duration-200">
                                    Voir
                                </a>
                                <a href="{{ route('admin.articles.edit', $article) }}" 
                                   class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-2 rounded-lg text-xs font-medium transition-colors duration-200">
                                    Modifier
                                </a>
                                @can('delete', Auth()->user())
                                    <button 
                                        wire:click="$set('selectedArticleId', {{ $article->id }})"
                                        @click="$dispatch('open-modal', 'confirm-delete-article')"
                                        class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg text-xs font-medium transition-colors duration-200">
                                        Supprimer
                                    </button>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Message si aucun résultat -->
            @if($articles->isEmpty())
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Aucun article trouvé</h3>
                    <p class="mt-1 text-sm text-gray-500">Commencez par créer votre premier article.</p>
                    <div class="mt-6">
                        <a href="{{ route('admin.articles.create') }}" 
                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-club-blue hover:bg-club-blue-light">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Nouvel article
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <!-- Pagination -->
        @if($articles->hasPages())
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mt-6">
                {{ $articles->links() }}
            </div>
        @endif

        <!-- Modal de confirmation de suppression -->
        <x-modal name="confirm-delete-article" focusable>
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                
                <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">
                    {{ __('Confirm deletion') }}
                </h3>
                
                <p class="text-sm text-gray-600 text-center mb-6">
                    {{ __('Are you sure you want to delete this article? This action cannot be undone.') }}
                </p>
                
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                    <x-secondary-button @click="$dispatch('close')" class="flex-1">
                        {{ __('Cancel') }}
                    </x-secondary-button>
                    <x-danger-button 
                        wire:click="deleteArticle"
                        @click="$dispatch('close')"
                        class="flex-1">
                        {{ __('Delete permanently') }}
                    </x-danger-button>
                </div>
            </div>
        </x-modal>
    </x-admin-block>
</x-app-layout>