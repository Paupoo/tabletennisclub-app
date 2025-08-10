<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <!-- En-tête de page -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-4 sm:space-y-0">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">{{ __('Create new article') }}</h2>
                    <p class="text-gray-600 text-sm sm:text-base">{{ __('Fill out the form below to create a new article.') }}</p>
                </div>
                
                <a href="{{ route('admin.articles.index') }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    {{ __('Back to list') }}
                </a>
            </div>
        </div>

        <!-- Formulaire de création -->
        <form action="{{ route('admin.articles.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

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
                                       value="{{ old('title') }}"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-club-blue focus:border-club-blue @error('title') border-red-500 @enderror"
                                       placeholder="Entrez le titre de l'article"
                                       required
                                       x-data="{ title: '{{ old('title') }}' }"
                                       x-model="title"
                                       @input="generateSlug">
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
                                           value="{{ old('slug') }}"
                                           class="flex-1 border border-gray-300 rounded-r-lg px-3 py-2 focus:ring-club-blue focus:border-club-blue @error('slug') border-red-500 @enderror"
                                           placeholder="url-de-l-article"
                                           required
                                           x-data="{ slug: '{{ old('slug') }}' }"
                                           x-model="slug">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">L'URL sera automatiquement générée à partir du titre</p>
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
                                      required>{{ old('content') }}</textarea>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>Vous pouvez utiliser du HTML basique pour la mise en forme</span>
                                <span id="character-count">0 caractères</span>
                            </div>
                            @error('content')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Image -->
                    <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Image de l'article</h3>
                        
                        <div x-data="{ imagePreview: null }" class="space-y-4">
                            <div>
                                <label for="image" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Article Image') }}
                                </label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-gray-400 transition-colors">
                                    <div class="space-y-1 text-center">
                                        <div x-show="!imagePreview">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg>
                                            <div class="flex text-sm text-gray-600">
                                                <label for="image" class="relative cursor-pointer bg-white rounded-md font-medium text-club-blue hover:text-club-blue-light focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-club-blue">
                                                    <span>Télécharger une image</span>
                                                    <input id="image" 
                                                           name="image" 
                                                           type="file" 
                                                           accept="image/*"
                                                           class="sr-only"
                                                           @change="const file = $event.target.files[0]; if (file) { const reader = new FileReader(); reader.onload = e => imagePreview = e.target.result; reader.readAsDataURL(file); }">
                                                </label>
                                                <p class="pl-1">ou glissez-déposez</p>
                                            </div>
                                            <p class="text-xs text-gray-500">PNG, JPG, GIF jusqu'à 2MB</p>
                                        </div>
                                        <div x-show="imagePreview" class="relative">
                                            <img :src="imagePreview" class="mx-auto h-32 w-auto rounded-lg shadow-sm">
                                            <button @click="imagePreview = null; document.getElementById('image').value = ''" type="button" class="absolute top-0 right-0 -mt-2 -mr-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600 transition-colors">
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
                                        <option value="{{ $statusEnum->value }}" {{ old('status', 'draft') === $statusEnum->value ? 'selected' : '' }}>
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
                                               {{ old('is_public', '1') == '1' ? 'checked' : '' }}>
                                        <span class="ml-2 text-sm text-gray-700">
                                            <span class="font-medium">Public</span> - Visible par tous les visiteurs
                                        </span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" 
                                               name="is_public" 
                                               value="0" 
                                               class="text-club-blue focus:ring-club-blue" 
                                               {{ old('is_public') == '0' ? 'checked' : '' }}>
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
                                    Enregistrer l'article
                                </button>
                                
                                <button type="submit" 
                                        name="action" 
                                        value="save_and_continue"
                                        class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Enregistrer et modifier
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
                                    <option value="{{ $categoryEnum->value }}" {{ old('category') === $categoryEnum->value ? 'selected' : '' }}>
                                        {{ $categoryEnum->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Auteur (caché, sera l'utilisateur connecté) -->
                    <input type="hidden" name="user_id" value="{{ auth()->id() }}">

                    <!-- Aperçu en temps réel -->
                    <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Aperçu</h3>
                        
                        <div class="space-y-3 text-sm">
                            <div>
                                <span class="font-medium text-gray-700">Titre:</span>
                                <span x-text="title || 'Sans titre'" class="text-gray-600"></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">URL:</span>
                                <span x-text="slug ? '{{ url('/articles') }}/' + slug : 'Non définie'" class="text-gray-600 font-mono text-xs"></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Auteur:</span>
                                <span class="text-gray-600">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Contenu:</span>
                                <span id="content-preview" class="text-gray-600">Aucun contenu</span>
                            </div>
                        </div>
                    </div>

                    <!-- Conseils -->
                    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                        <h4 class="text-sm font-semibold text-blue-900 mb-2">💡 Conseils</h4>
                        <ul class="text-xs text-blue-800 space-y-1">
                            <li>• Utilisez un titre accrocheur et descriptif</li>
                            <li>• L'URL (slug) sera générée automatiquement</li>
                            <li>• Sauvegardez régulièrement en tant que brouillon</li>
                            <li>• Choisissez une image de qualité pour illustrer l'article</li>
                            <li>• Vérifiez la catégorie avant de publier</li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>

        <!-- Scripts pour les interactions -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const titleInput = document.getElementById('title');
                const slugInput = document.getElementById('slug');
                const contentTextarea = document.getElementById('content');
                const characterCount = document.getElementById('character-count');
                const contentPreview = document.getElementById('content-preview');
                
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
                
                titleInput.addEventListener('input', function() {
                    const slug = generateSlug(this.value);
                    slugInput.value = slug;
                });
                
                // Compteur de caractères
                function updateCharacterCount() {
                    const count = contentTextarea.value.length;
                    characterCount.textContent = `${count} caractères`;
                    
                    // Aperçu du contenu
                    const preview = contentTextarea.value.slice(0, 100);
                    contentPreview.textContent = preview ? preview + (contentTextarea.value.length > 100 ? '...' : '') : 'Aucun contenu';
                }
                
                contentTextarea.addEventListener('input', updateCharacterCount);
                
                // Auto-sauvegarde (optionnel - vous pouvez l'implémenter avec AJAX)
                let autoSaveTimeout;
                function autoSave() {
                    clearTimeout(autoSaveTimeout);
                    autoSaveTimeout = setTimeout(() => {
                        // Ici vous pourriez implémenter une sauvegarde automatique via AJAX
                        console.log('Auto-save would trigger here');
                    }, 30000); // Auto-save après 30 secondes d'inactivité
                }
                
                [titleInput, slugInput, contentTextarea].forEach(input => {
                    input.addEventListener('input', autoSave);
                });
                
                // Initialiser le compteur
                updateCharacterCount();
            });
        </script>

        <style>
            /* Styles pour l'éditeur de texte */
            #content {
                font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                line-height: 1.6;
            }
            
            /* Animation pour les champs de formulaire */
            .form-group {
                transition: all 0.3s ease;
            }
            
            .form-group:focus-within {
                transform: translateY(-1px);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }
        </style>
    </x-admin-block>
</x-app-layout>