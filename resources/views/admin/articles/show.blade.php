<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <!-- En-tête avec informations principales -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-4 space-y-4 sm:space-y-0">
                <div class="flex-1">
                    <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">
                        {{ $article->title }}
                    </h2>
                    <div class="space-y-1">
                        <p class="text-sm sm:text-base text-gray-600">
                            <span class="font-medium">Auteur:</span> {{ $article->user->first_name }} {{ $article->user->last_name }}
                        </p>
                        <p class="text-sm sm:text-base text-gray-600">
                            <span class="font-medium">Slug:</span> {{ $article->slug }}
                        </p>
                        <p class="text-xs sm:text-sm text-gray-500">
                            Créé le {{ $article->created_at->format('d/m/Y à H:i') }}
                            @if($article->updated_at && $article->updated_at != $article->created_at)
                                • Modifié le {{ $article->updated_at->format('d/m/Y à H:i') }}
                            @endif
                        </p>
                    </div>
                </div>
                
                <!-- Badges de statut -->
                <div class="flex flex-wrap gap-2">
                    <!-- Statut de publication -->
                    @php
                        $statusConfig = [
                            'published' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'label' => 'Publié'],
                            'draft' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'label' => 'Brouillon'],
                            'archived' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'label' => 'Archivé'],
                        ];
                        $config = $statusConfig[$article->status->value] ?? $statusConfig['draft'];
                    @endphp
                    <span class="{{ $config['bg'] }} {{ $config['text'] }} px-3 py-1 rounded-full text-xs sm:text-sm font-medium">
                        {{ $config['label'] }}
                    </span>

                    <!-- Catégorie -->
                    <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs sm:text-sm font-medium">
                        {{ $article->category->name }}
                    </span>

                    <!-- Visibilité -->
                    @if($article->is_public)
                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs sm:text-sm font-medium inline-flex items-center">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"></path>
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"></path>
                            </svg>
                            Public
                        </span>
                    @else
                        <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-xs sm:text-sm font-medium inline-flex items-center">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd"></path>
                                <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"></path>
                            </svg>
                            Privé
                        </span>
                    @endif
                </div>
            </div>

            <!-- Actions principales -->
            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                <a href="{{ route('admin.articles.edit', $article) }}" 
                   class="bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base inline-flex items-center justify-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Modifier l'article
                </a>

                @if($article->is_public && $article->status->value === 'published')
                    <a href="{{ route('articles.show', $article->slug) }}" 
                       target="_blank"
                       class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base inline-flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                        Voir sur le site
                    </a>
                @endif

                @if($article->status->value === 'draft')
                    <form action="{{ route('admin.articles.publish', $article) }}" method="POST" class="inline">
                        @csrf
                        @method('PATCH')
                        <button type="submit" 
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base inline-flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                            Publier
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Image et contenu principal -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Image de l'article -->
                @if($article->image)
                    <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                        <h3 class="text-lg sm:text-xl font-bold text-club-blue mb-4">Image de l'article</h3>
                        <div class="relative">
                            <img src="{{ asset('storage/' . $article->image) }}" 
                                 alt="{{ $article->title }}" 
                                 class="w-full h-auto rounded-lg shadow-sm">
                        </div>
                    </div>
                @endif

                <!-- Contenu de l'article -->
                <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                    <h3 class="text-lg sm:text-xl font-bold text-club-blue mb-4">Contenu</h3>
                    <div class="prose prose-sm sm:prose max-w-none prose-blue">
                        {!! nl2br(e($article->content)) !!}
                    </div>
                </div>
            </div>

            <!-- Informations techniques -->
            <div class="space-y-6">
                <!-- Métadonnées -->
                <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                    <h3 class="text-lg sm:text-xl font-bold text-club-blue mb-4">Informations techniques</h3>
                    <div class="space-y-3">
                        <div class="border-b border-gray-100 pb-2">
                            <span class="text-xs sm:text-sm font-medium text-gray-500">ID de l'article</span>
                            <p class="text-sm sm:text-base text-gray-800 mt-1">{{ $article->id }}</p>
                        </div>
                        
                        <div class="border-b border-gray-100 pb-2">
                            <span class="text-xs sm:text-sm font-medium text-gray-500">URL (Slug)</span>
                            <p class="text-sm sm:text-base text-gray-800 mt-1 font-mono">{{ $article->slug }}</p>
                        </div>

                        <div class="border-b border-gray-100 pb-2">
                            <span class="text-xs sm:text-sm font-medium text-gray-500">Catégorie</span>
                            <p class="text-sm sm:text-base text-gray-800 mt-1">{{ $article->category->name }}</p>
                        </div>

                        <div class="border-b border-gray-100 pb-2">
                            <span class="text-xs sm:text-sm font-medium text-gray-500">Statut</span>
                            <p class="text-sm sm:text-base text-gray-800 mt-1">{{ $config['label'] }}</p>
                        </div>

                        <div class="border-b border-gray-100 pb-2">
                            <span class="text-xs sm:text-sm font-medium text-gray-500">Visibilité</span>
                            <p class="text-sm sm:text-base text-gray-800 mt-1">{{ $article->is_public ? 'Public' : 'Privé' }}</p>
                        </div>

                        <div class="border-b border-gray-100 pb-2">
                            <span class="text-xs sm:text-sm font-medium text-gray-500">Nombre de caractères</span>
                            <p class="text-sm sm:text-base text-gray-800 mt-1">{{ strlen($article->content) }} caractères</p>
                        </div>

                        <div class="border-b border-gray-100 pb-2">
                            <span class="text-xs sm:text-sm font-medium text-gray-500">Nombre de mots</span>
                            <p class="text-sm sm:text-base text-gray-800 mt-1">{{ str_word_count(strip_tags($article->content)) }} mots</p>
                        </div>
                    </div>
                </div>

                <!-- Informations sur l'auteur -->
                <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                    <h3 class="text-lg sm:text-xl font-bold text-club-blue mb-4">Auteur</h3>
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0 h-12 w-12">
                            <div class="h-12 w-12 rounded-full bg-club-blue flex items-center justify-center">
                                <span class="text-sm font-medium text-white">
                                    {{ strtoupper(substr($article->user->first_name, 0, 1) . substr($article->user->last_name, 0, 1)) }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                {{ $article->user->first_name }} {{ $article->user->last_name }}
                            </p>
                            <p class="text-sm text-gray-500">{{ $article->user->email }}</p>
                        </div>
                    </div>
                </div>

                <!-- Dates importantes -->
                <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                    <h3 class="text-lg sm:text-xl font-bold text-club-blue mb-4">Historique</h3>
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">Créé</p>
                                <p class="text-xs text-gray-500">{{ $article->created_at->format('d/m/Y à H:i') }}</p>
                            </div>
                        </div>

                        @if($article->updated_at && $article->updated_at != $article->created_at)
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Dernière modification</p>
                                    <p class="text-xs text-gray-500">{{ $article->updated_at->format('d/m/Y à H:i') }}</p>
                                </div>
                            </div>
                        @endif

                        @if($article->deleted_at)
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Supprimé</p>
                                    <p class="text-xs text-gray-500">{{ $article->deleted_at->format('d/m/Y à H:i') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions supplémentaires -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mt-6">
            <h3 class="text-lg sm:text-xl font-bold text-club-blue mb-4">Actions supplémentaires</h3>
            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                <a href="{{ route('admin.articles.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto inline-flex items-center justify-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Retour à la liste
                </a>

                <a href="{{ route('admin.articles.create') }}" 
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto inline-flex items-center justify-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Nouvel article
                </a>

                <form action="{{ route('admin.articles.destroy', $article) }}" method="POST" class="w-full sm:w-auto">
                    @csrf
                    @method('DELETE')
                    <button type="submit" 
                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')"
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full inline-flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Supprimer
                    </button>
                </form>

                @if($article->status->value !== 'archived')
                    <form action="{{ route('admin.articles.archive', $article) }}" method="POST" class="w-full sm:w-auto">
                        @csrf
                        @method('PATCH')
                        <button type="submit" 
                                class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full inline-flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8l6 6 6-6"></path>
                            </svg>
                            Archiver
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-admin-block>
</x-app-layout>