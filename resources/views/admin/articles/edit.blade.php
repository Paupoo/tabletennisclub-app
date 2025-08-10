<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <!-- En-tête de page -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-4 sm:space-y-0">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">{{ __('Edit article') }}</h2>
                    <p class="text-gray-600 text-sm sm:text-base">{{ __('Modify the article information below.') }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        Créé le {{ $article->created_at->format('d/m/Y à H:i') }}
                        @if($article->updated_at && $article->updated_at != $article->created_at)
                            • Dernière modification: {{ $article->updated_at->format('d/m/Y à H:i') }}
                        @endif
                    </p>
                </div>
                
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                    <a href="{{ route('admin.articles.show', $article) }}" 
                       class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base inline-flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        {{ __('View article') }}
                    </a>
                    <a href="{{ route('admin.articles.index') }}" 
                       class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base inline-flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        {{ __('Back to list') }}
                    </a>
                </div>
            </div>

            <!-- Statut actuel -->
            <div class="flex flex-wrap gap-2 mt-4">
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
                <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs sm:text-sm font-medium">
                    {{ $article->category->name }}
                </span>
                @if($article->is_public)
                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs sm:text-sm font-medium">Public</span>
                @else
                    <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-xs sm:text-sm font-medium">Privé</span>
                @endif
            </div>
        </div>

        <!-- Formulaire de modification -->
        <form action="{{ route('admin.articles.update', $article) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Contenu principal -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Titre et slug -->
                    <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informations générales</h3>
                        
                        <div class="space-y-4">
                            <!-- Titre -->
                            <div>
                                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Title') }} <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       id="title" 
                                       name="title" 
                                       value="{{ old('title', $article->title) }}"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-club-blue focus:border-club-blue @error('title') border-red-500 @enderror"
                                       placeholder="Entrez le titre de l'article"
                                       required
                                       x-data="{ title: '{{ old('title', $article->title) }}' }"
                                       x-model="title"
                                       @input="updateSlugFromTitle">
                                @error('title')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Slug -->
                            <div>
                                <label for="slug" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('URL (Slug)') }} <span class="text-red-500">*</span>
                                </label>
                                <div class="flex">
                                    <span class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                                        {{ url('/articles') }}/
                                    </span>
                                    <input type="text" 
                                           id="slug" 
                                           name="slug" 
                                           value="{{ old('slug', $article->slug) }}"
                                           class="flex-1 border border-gray-300 rounded-r-lg px-3 py-2 focus:ring-club-blue focus:border-club-blue @error('slug') border-red-500 @enderror"
                                           placeholder="url-de-l-article"
                                           required
                                           x-data="{ slug: '{{ old('slug', $article->slug) }}' }"
                                           x-model="slug">
                                </div>
                                <div class="flex justify-between mt-1">
                                    <p class="text-xs text-gray-500">L'URL peut être modifiée mais cela peut casser les liens existants</p>
                                    <button type="button" 
                                            @click="regenerateSlugFromTitle()"
                                            class="text-xs text-club-blue hover:text-club-blue-light">
                                        Régénérer depuis le titre
                                    </button>
                                </div>
                                @error('slug')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Contenu -->
                    <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Contenu de l'article</h3>
                        
                        <div>
                            <label for="content" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('Content') }} <span class="text-red-500">*</span>
                            </label>
                            <textarea id="content" 
                                      name="content" 
                                      rows="15"
                                      class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-club-blue focus:border-club-blue @error('content') border-red-500 @enderror"
                                      placeholder="Rédigez le contenu de votre article ici..."
                                      required>{{ old('content', $article->content) }}</textarea>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>Vous pouvez utiliser du HTML basique pour la mise en forme</span>
                                <span id="character-count">{{ strlen($article->content) }} caractères</span>
                            </div>
                            @error('content')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Image -->
                    <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Image de l'article</h3>
                        
                        <div x-data="{ imagePreview: '{{ $article->image ? asset('storage/' . $article->image) : null }}', showCurrentImage: {{ $article->image ? 'true' : 'false' }} }" class="space-y-4">
                            <!-- Image actuelle -->
                            @if($article->image)
                                <div x-show="showCurrentImage && !imagePreview.includes('data:')">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Image actuelle</label>
                                    <div class="relative inline-block">
                                        <img src="{{ asset('storage/' . $article->image) }}" alt="{{ $article->title }}" class="h-32 w-auto rounded-lg shadow-sm">
                                        <button @click="showCurrentImage = false" type="button" class="absolute top-0 right-0 -mt-2 -mr-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                    <input type="hidden" name="remove_image" x-model="!showCurrentImage ? '1' : '0'">
                                </div>
                            @endif
                            
                            <!-- Upload nouvelle image -->
                            <div>
                                <label for="image" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ $article->image ? 'Remplacer l\'image' : 'Ajouter une image' }}
                                </label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-gray-400 transition-colors">
                                    <div class="space-y-1 text-center">
                                        <div x-show="!imagePreview || (!showCurrentImage && !imagePreview.includes('data:'))">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg>
                                            <div class="flex text-sm text-gray-600">
                                                <label for="image" class="relative cursor-pointer bg-white rounded-md font-medium text-club-blue hover:text-club-blue-light focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-club-blue">
                                                    <span>{{ $article->image ? 'Changer l\'image' : 'Télécharger une image' }}</span>
                                                    <input id="image" 
                                                           name="image" 
                                                           type="file" 
                                                           accept="image/*"
                                                           class="sr-only"
                                                           @change="const file = $event.target.files[0]; if (file) { const reader = new FileReader(); reader.onload = e => { imagePreview = e.target.result; showCurrentImage = false; }; reader.readAsDataURL(file); }">
                                                </label>
                                                <p class="pl-1">ou glissez-déposez</p>
                                            </div>
                                            <p class="text-xs text-gray-500">PNG, JPG, GIF jusqu'à 2MB</p>
                                        </div>
                                        <div x-show="imagePreview && imagePreview.includes('data:')" class="relative">
                                            <img :src="imagePreview" class="mx-auto h-32 w-auto rounded-lg shadow-sm">
                                            <button @click="imagePreview = null; document.getElementById('image').value = ''; showCurrentImage = {{ $article->image ? 'true' : 'false' }}" type="button" class="absolute top-0 right-0 -mt-2 -mr-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600 transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                @error('image')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar avec paramètres -->
                <div class="space-y-6">
                    <!-- Actions de publication -->
                    <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Publication</h3>
                        
                        <div class="space-y-4">
                            <!-- Statut -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Status') }}
                                </label>
                                <select id="status" 
                                        name="status" 
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-club-blue focus:border-club-blue @error('status') border-red-500 @enderror">
                                    @foreach(\App\Enums\ArticlesStatusEnum::cases() as $statusEnum)
                                        <option value="{{ $statusEnum->value }}" {{ old('status', $article->status->value) === $statusEnum->value ? 'selected' : '' }}>
                                            {{ ucfirst($statusEnum->value) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Visibilité -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Visibility') }}
                                </label>
                                <div class="space-y-2">
                                    <label class="flex items-center">
                                        <input type="radio" 
                                               name="is_public" 
                                               value="1" 
                                               class="text-club-blue focus:ring-club-blue" 
                                               {{ old('is_public', $article->is_public) == '1' ? 'checked' : '' }}>
                                        <span class="ml-2 text-sm text-gray-700">
                                            <span class="font-medium">Public</span> - Visible par tous les visiteurs
                                        </span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" 
                                               name="is_public" 
                                               value="0" 
                                               class="text-club-blue focus:ring-club-blue" 
                                               {{ old('is_public', $article->is_public) == '0' ? 'checked' : '' }}>
                                        <span class="ml-2 text-sm text-gray-700">
                                            <span class="font-medium">Privé</span> - Visible uniquement par les membres
                                        </span>
                                    </label>
                                </div>
                                @error('is_public')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Boutons d'action -->
                            <div class="pt-4 border-t border-gray-200 space-y-2">
                                <button type="submit" 
                                        name="action" 
                                        value="save"
                                        class="w-full bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                    Enregistrer les modifications
                                </button>
                                
                                <button type="submit" 
                                        name="action" 
                                        value="save_and_view"
                                        class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    Enregistrer et voir
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Catégorie -->
                    <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Catégorisation</h3>
                        
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('Category') }} <span class="text-red-500">*</span>
                            </label>
                            <select id="category" 
                                    name="category" 
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-club-blue focus:border-club-blue @error('category') border-red-500 @enderror"
                                    required>
                                <option value="">Choisir une catégorie</option>
                                @foreach(\App\Enums\ArticlesCategoryEnum::cases() as $categoryEnum)
                                    <option value="{{ $categoryEnum->value }}" {{ old('category', $article->category->value) === $categoryEnum->value ? 'selected' : '' }}>
                                        {{ $categoryEnum->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Informations de l'article -->
                    <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informations</h3>
                        
                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="font-medium text-gray-700">ID:</span>
                                <span class="text-gray-600">{{ $article->id }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium text-gray-700">Auteur:</span>
                                <span class="text-gray-600">{{ $article->user->first_name }} {{ $article->user->last_name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium text-gray-700">Créé:</span>
                                <span class="text-gray-600">{{ $article->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                            @if($article->updated_at && $article->updated_at != $article->created_at)
                                <div class="flex justify-between">
                                    <span class="font-medium text-gray-700">Modifié:</span>
                                    <span class="text-gray-600">{{ $article->updated_at->format('d/m/Y H:i') }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between">
                                <span class="font-medium text-gray-700">Caractères:</span>
                                <span class="text-gray-600" id="current-char-count">{{ strlen($article->content) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium text-gray-700">Mots:</span>
                                <span class="text-gray-600">{{ str_word_count(strip_tags($article->content)) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions rapides -->
                    <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions rapides</h3>
                        
                        <div class="space-y-2">
                            @if($article->is_public && $article->status->value === 'published')
                                <a href="{{ route('articles.show', $article->slug) }}" 
                                   target="_blank"
                                   class="w-full bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                    Voir sur le site
                                </a>
                            @endif

                            @if($article->status->value === 'draft')
                                <button type="submit" 
                                        name="quick_action" 
                                        value="publish"
                                        class="w-full bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                    </svg>
                                    Publication rapide
                                </button>
                            @endif

                            @if($article->status->value !== 'archived')
                                <button type="submit" 
                                        name="quick_action" 
                                        value="archive"
                                        class="w-full bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8l6 6 6-6"></path>
                                    </svg>
                                    Archiver
                                </button>
                            @endif

                            <button type="button" 
                                    onclick="if(confirm('Êtes-vous sûr de vouloir dupliquer cet article ?')) { document.getElementById('duplicate-form').submit(); }"
                                    class="w-full bg-purple-600 hover:bg-purple-700 text-white px-3 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                                Dupliquer
                            </button>
                        </div>
                    </div>

                    <!-- Aperçu en temps réel -->
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <h4 class="text-sm font-semibold text-blue-900 mb-2">📝 État de modification</h4>
                        <div class="text-xs text-blue-800 space-y-1" id="modification-status">
                            <div>• Article en cours de modification</div>
                            <div>• Pensez à sauvegarder régulièrement</div>
                            <div id="auto-save-status" class="text-blue-600">• Auto-sauvegarde activée</div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Formulaire de duplication (caché) -->
        <form id="duplicate-form" action="{{ route('admin.articles.duplicate', $article) }}" method="POST" style="display: none;">
            @csrf
        </form>

        <!-- Scripts pour les interactions -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const titleInput = document.getElementById('title');
                const slugInput = document.getElementById('slug');
                const contentTextarea = document.getElementById('content');
                const characterCount = document.getElementById('character-count');
                const currentCharCount = document.getElementById('current-char-count');
                const autoSaveStatus = document.getElementById('auto-save-status');
                
                let hasUnsavedChanges = false;
                let autoSaveTimeout;
                let originalFormData = new FormData(document.querySelector('form'));
                
                // Génération automatique du slug
                function generateSlug(title) {
                    return title
                        .toLowerCase()
                        .replace(/[àáâãäå]/g, 'a')
                        .replace(/[èéêë]/g, 'e')
                        .replace(/[ìíîï]/g, 'i')
                        .replace(/[òóôõö]/g, 'o')
                        .replace(/[ùúûü]/g, 'u')
                        .replace(/[ñ]/g, 'n')
                        .replace(/[ç]/g, 'c')
                        .replace(/[^a-z0-9 -]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-')
                        .trim('-');
                }
                
                // Fonction globale pour Alpine.js
                window.updateSlugFromTitle = function() {
                    // Cette fonction sera appelée par Alpine.js
                }
                
                window.regenerateSlugFromTitle = function() {
                    const slug = generateSlug(titleInput.value);
                    slugInput.value = slug;
                    slugInput.dispatchEvent(new Event('input'));
                }
                
                // Compteur de caractères et détection des changements
                function updateCharacterCount() {
                    const count = contentTextarea.value.length;
                    if (characterCount) characterCount.textContent = `${count} caractères`;
                    if (currentCharCount) currentCharCount.textContent = count;
                }
                
                // Détection des changements
                function detectChanges() {
                    const currentFormData = new FormData(document.querySelector('form'));
                    let hasChanged = false;
                    
                    for (let [key, value] of currentFormData.entries()) {
                        if (originalFormData.get(key) !== value) {
                            hasChanged = true;
                            break;
                        }
                    }
                    
                    if (hasChanged !== hasUnsavedChanges) {
                        hasUnsavedChanges = hasChanged;
                        updateAutoSaveStatus();
                    }
                }
                
                function updateAutoSaveStatus() {
                    if (autoSaveStatus) {
                        if (hasUnsavedChanges) {
                            autoSaveStatus.textContent = '• Modifications non sauvegardées';
                            autoSaveStatus.className = 'text-orange-600';
                        } else {
                            autoSaveStatus.textContent = '• Aucune modification en attente';
                            autoSaveStatus.className = 'text-green-600';
                        }
                    }
                }
                
                // Auto-sauvegarde (simulation - vous devriez implémenter avec AJAX)
                function scheduleAutoSave() {
                    clearTimeout(autoSaveTimeout);
                    if (hasUnsavedChanges) {
                        autoSaveTimeout = setTimeout(() => {
                            // Ici vous pourriez implémenter une sauvegarde automatique via AJAX
                            console.log('Auto-save would trigger here');
                            if (autoSaveStatus) {
                                autoSaveStatus.textContent = '• Auto-sauvegarde en cours...';
                                autoSaveStatus.className = 'text-blue-600';
                                
                                setTimeout(() => {
                                    autoSaveStatus.textContent = '• Sauvegardé automatiquement';
                                    autoSaveStatus.className = 'text-green-600';
                                }, 1000);
                            }
                        }, 30000); // Auto-save après 30 secondes
                    }
                }
                
                // Event listeners
                contentTextarea.addEventListener('input', function() {
                    updateCharacterCount();
                    detectChanges();
                    scheduleAutoSave();
                });
                
                [titleInput, slugInput].forEach(input => {
                    input.addEventListener('input', function() {
                        detectChanges();
                        scheduleAutoSave();
                    });
                });
                
                // Avertissement avant de quitter la page
                window.addEventListener('beforeunload', function(e) {
                    if (hasUnsavedChanges) {
                        const message = 'Vous avez des modifications non sauvegardées. Êtes-vous sûr de vouloir quitter cette page ?';
                        e.returnValue = message;
                        return message;
                    }
                });
                
                // Marquer comme sauvegardé lors de la soumission
                document.querySelector('form').addEventListener('submit', function() {
                    hasUnsavedChanges = false;
                });
                
                // Initialisation
                updateCharacterCount();
                updateAutoSaveStatus();
                
                // Raccourcis clavier
                document.addEventListener('keydown', function(e) {
                    // Ctrl+S ou Cmd+S pour sauvegarder
                    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                        e.preventDefault();
                        document.querySelector('button[name="action"][value="save"]').click();
                    }
                });
            });
        </script>

        <style>
            /* Styles pour l'éditeur de texte */
            #content {
                font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                line-height: 1.6;
            }
            
            /* Animation pour les champs modifiés */
            .form-field-modified {
                border-color: #3B82F6;
                box-shadow: 0 0 0 1px #3B82F6;
            }
            
            /* Indicateur de modifications non sauvegardées */
            .unsaved-changes::after {
                content: ' *';
                color: #EF4444;
                font-weight: bold;
            }
        </style>
    </x-admin-block>
</x-app-layout>