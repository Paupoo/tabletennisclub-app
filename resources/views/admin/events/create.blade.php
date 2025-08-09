{{-- resources/views/admin/events/create.blade.php --}}
<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-club-blue">Créer un nouvel événement</h2>
                    <p class="text-gray-600 mt-1">Remplissez les informations ci-dessous pour créer un événement.</p>
                </div>
                <a href="{{ route('admin.events.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                    ← Retour à la liste
                </a>
            </div>

            {{-- Affichage des erreurs --}}
            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                    <strong class="font-bold">Erreur{{ $errors->count() > 1 ? 's' : '' }} :</strong>
                    <ul class="mt-2 list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('admin.events.store') }}" method="POST" 
                  x-data="{ 
                      category: '{{ old('category', 'club-life') }}',
                      status: '{{ old('status', 'draft') }}',
                      icons: @js(\App\Models\Event::ICONS),
                      showPreview: false,
                      updateIcon() {
                          if (this.icons[this.category]) {
                              this.$refs.iconInput.value = this.icons[this.category];
                          }
                      }
                  }" 
                  x-init="updateIcon()">
                @csrf

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Colonne gauche : Informations principales -->
                    <div class="space-y-6">
                        <!-- Titre -->
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                                Titre de l'événement *
                            </label>
                            <input type="text" 
                                   id="title" 
                                   name="title" 
                                   value="{{ old('title') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                                   placeholder="AG de rentrée, Championnat du Nouvel An..."
                                   required>
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                                Description *
                            </label>
                            <textarea id="description" 
                                      name="description" 
                                      rows="4"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent resize-y"
                                      placeholder="Décrivez votre événement..."
                                      required>{{ old('description') }}</textarea>
                        </div>

                        <!-- Catégorie et Icône -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="category" class="block text-sm font-medium text-gray-700 mb-2">
                                    Catégorie *
                                </label>
                                <select id="category" 
                                        name="category" 
                                        x-model="category"
                                        @change="updateIcon()"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                                        required>
                                    @foreach(\App\Models\Event::CATEGORIES as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="icon" class="block text-sm font-medium text-gray-700 mb-2">
                                    Icône
                                </label>
                                <input type="text" 
                                       id="icon" 
                                       name="icon" 
                                       value="{{ old('icon') }}"
                                       x-ref="iconInput"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent text-center text-2xl"
                                       placeholder="📅"
                                       maxlength="10">
                                <p class="text-xs text-gray-500 mt-1">Emoji ou texte court (se remplit automatiquement)</p>
                            </div>
                        </div>

                        <!-- Lieu -->
                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700 mb-2">
                                Lieu *
                            </label>
                            <input type="text" 
                                   id="location" 
                                   name="location" 
                                   value="{{ old('location') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                                   placeholder="Demeester, Salle principale..."
                                   required>
                        </div>

                        <!-- Prix -->
                        <div>
                            <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                                Prix/Information tarifaire
                            </label>
                            <input type="text" 
                                   id="price" 
                                   name="price" 
                                   value="{{ old('price') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                                   placeholder="Gratuit, 25€, Nourriture incluse...">
                            <p class="text-xs text-gray-500 mt-1">Laissez vide si gratuit ou non applicable</p>
                        </div>
                    </div>

                    <!-- Colonne droite : Paramètres et options -->
                    <div class="space-y-6">
                        <!-- Date et heures -->
                        <div>
                            <label for="event_date" class="block text-sm font-medium text-gray-700 mb-2">
                                Date de l'événement *
                            </label>
                            <input type="date" 
                                   id="event_date" 
                                   name="event_date" 
                                   value="{{ old('event_date') }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                                   required>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">
                                    Heure de début *
                                </label>
                                <input type="time" 
                                       id="start_time" 
                                       name="start_time" 
                                       value="{{ old('start_time') }}"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                                       required>
                            </div>

                            <div>
                                <label for="end_time" class="block text-sm font-medium text-gray-700 mb-2">
                                    Heure de fin
                                </label>
                                <input type="time" 
                                       id="end_time" 
                                       name="end_time" 
                                       value="{{ old('end_time') }}"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                            </div>
                        </div>

                        <!-- Statut -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                Statut *
                            </label>
                            <select id="status" 
                                    name="status" 
                                    x-model="status"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                                    required>
                                @foreach(\App\Models\Event::STATUSES as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">
                                <span x-show="status === 'draft'" class="text-gray-600">💾 Brouillon : visible seulement par les admins</span>
                                <span x-show="status === 'published'" class="text-green-600">✅ Publié : visible par tout le monde</span>
                                <span x-show="status === 'archived'" class="text-red-600">📦 Archivé : masqué mais conservé</span>
                            </p>
                        </div>

                        <!-- Nombre maximum de participants -->
                        <div>
                            <label for="max_participants" class="block text-sm font-medium text-gray-700 mb-2">
                                Nombre maximum de participants
                            </label>
                            <input type="number" 
                                   id="max_participants" 
                                   name="max_participants" 
                                   value="{{ old('max_participants') }}"
                                   min="1"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                                   placeholder="50">
                            <p class="text-xs text-gray-500 mt-1">Laissez vide si illimité</p>
                        </div>

                        <!-- Options avancées -->
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Options</h3>
                            
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" 
                                       name="featured" 
                                       value="1"
                                       {{ old('featured') ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-club-blue focus:ring-club-blue">
                                <span class="text-sm text-gray-700">
                                    ⭐ Mettre en avant cet événement
                                </span>
                            </label>
                            <p class="text-xs text-gray-500 mt-1 ml-6">Les événements mis en avant apparaissent en premier</p>
                        </div>

                        <!-- Notes privées -->
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                Notes privées (admin uniquement)
                            </label>
                            <textarea id="notes" 
                                      name="notes" 
                                      rows="3"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent resize-y"
                                      placeholder="Notes internes, rappels, contacts...">{{ old('notes') }}</textarea>
                            <p class="text-xs text-gray-500 mt-1">Ces notes ne sont visibles que par les administrateurs</p>
                        </div>
                    </div>
                </div>

                <!-- Aperçu -->
                <div class="mt-8 border-t pt-6">
                    <button type="button" 
                            @click="showPreview = !showPreview"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors mb-4">
                        <span x-text="showPreview ? 'Masquer l\'aperçu' : 'Voir l\'aperçu'"></span>
                    </button>

                    <div x-show="showPreview" 
                         x-transition
                         class="p-6 border border-gray-200 rounded-lg bg-gradient-to-br from-blue-50 to-indigo-50">
                        <div class="bg-white rounded-lg p-6 shadow-sm">
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-16 h-16 bg-club-blue rounded-full flex items-center justify-center text-2xl">
                                        <span x-text="$refs.iconInput.value || '📅'"></span>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-xl font-semibold text-gray-900 mb-2" x-text="$refs.title?.value || 'Titre de l\'événement'"></h3>
                                    <p class="text-gray-600 mb-3" x-text="$refs.description?.value || 'Description de l\'événement'"></p>
                                    <div class="space-y-2 text-sm text-gray-500">
                                        <div class="flex items-center space-x-2">
                                            <span>📅</span>
                                            <span x-text="$refs.event_date?.value ? new Date($refs.event_date.value).toLocaleDateString('fr-FR') : 'Date non définie'"></span>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span>⏰</span>
                                            <span x-text="$refs.start_time?.value ? $refs.start_time.value + ($refs.end_time?.value ? ' - ' + $refs.end_time.value : '') : 'Heure non définie'"></span>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span>📍</span>
                                            <span x-text="$refs.location?.value || 'Lieu non défini'"></span>
                                        </div>
                                        <div class="flex items-center space-x-2" x-show="$refs.price?.value">
                                            <span>💰</span>
                                            <span x-text="$refs.price?.value"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-col space-y-2">
                                    <span class="px-3 py-1 text-xs font-medium rounded-full" 
                                          :class="{
                                              'bg-gray-100 text-gray-800': status === 'draft',
                                              'bg-green-100 text-green-800': status === 'published',
                                              'bg-red-100 text-red-800': status === 'archived'
                                          }"
                                          x-text="status === 'draft' ? 'Brouillon' : status === 'published' ? 'Publié' : 'Archivé'"></span>
                                    <span class="px-3 py-1 text-xs font-medium rounded-full"
                                          :class="{
                                              'bg-blue-100 text-blue-800': category === 'club-life',
                                              'bg-orange-100 text-orange-800': category === 'tournament',
                                              'bg-purple-100 text-purple-800': category === 'training'
                                          }"
                                          x-text="category === 'club-life' ? 'Vie du club' : category === 'tournament' ? 'Tournoi' : 'Entraînement'"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3 mt-8 pt-6 border-t">
                    <a href="{{ route('admin.events.index') }}" 
                       class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-medium transition-colors text-center">
                        Annuler
                    </a>
                    <button type="submit" 
                            name="action"
                            value="save_draft"
                            class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                        💾 Enregistrer comme brouillon
                    </button>
                    <button type="submit" 
                            name="action"
                            value="save_publish"
                            class="bg-club-blue hover:bg-club-blue-light text-white px-6 py-2 rounded-lg font-medium transition-colors">
                        🚀 Enregistrer et publier
                    </button>
                </div>

                <!-- Références cachées pour l'aperçu Alpine.js -->
                <div style="display: none;">
                    <input x-ref="title" :value="document.getElementById('title').value">
                    <input x-ref="description" :value="document.getElementById('description').value">
                    <input x-ref="event_date" :value="document.getElementById('event_date').value">
                    <input x-ref="start_time" :value="document.getElementById('start_time').value">
                    <input x-ref="end_time" :value="document.getElementById('end_time').value">
                    <input x-ref="location" :value="document.getElementById('location').value">
                    <input x-ref="price" :value="document.getElementById('price').value">
                </div>
            </form>
        </div>
    </x-admin-block>
</x-app-layout>

{{-- resources/views/admin/events/edit.blade.php --}}
{{-- Cette vue sera identique au create mais avec quelques modifications --}}