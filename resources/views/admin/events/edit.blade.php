{{-- resources/views/admin/events/edit.blade.php --}}
<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-club-blue">Modifier l'événement</h2>
                    <p class="text-gray-600 mt-1">{{ $event->title }}</p>
                </div>
                <div class="flex space-x-2">
                    <a href="{{ route('admin.events.show', $event) }}" 
                       class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                        ← Annuler
                    </a>
                </div>
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

            <form action="{{ route('admin.events.update', $event) }}" method="POST" id="eventUpdate"
                  x-data="{ 
                      category: '{{ old('category', $event->category) }}',
                      status: '{{ old('status', $event->status) }}',
                      icons: @js(\App\Models\Event::ICONS),
                      showPreview: false,
                      updateIcon() {
                          if (this.icons[this.category]) {
                              this.$refs.iconInput.value = this.icons[this.category];
                          }
                      }
                  }">
                @csrf
                @method('PUT')

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
                                   value="{{ old('title', $event->title) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
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
                                      required>{{ old('description', $event->description) }}</textarea>
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
                                        <option value="{{ $key }}" {{ old('category', $event->category) === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
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
                                       value="{{ old('icon', $event->icon) }}"
                                       x-ref="iconInput"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent text-center text-2xl"
                                       maxlength="10">
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
                                   value="{{ old('location', $event->location) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
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
                                   value="{{ old('price', $event->price) }}"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
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
                                   value="{{ old('event_date', $event->event_date->format('Y-m-d')) }}"
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
                                       value="{{ old('start_time', $event->start_time->format('H:i')) }}"
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
                                       value="{{ old('end_time', $event->end_time?->format('H:i')) }}"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                            </div>
                        </div>

                        <!-- Statut avec alertes -->
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
                                    <option value="{{ $key }}" {{ old('status', $event->status) === $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>

                            <!-- Alertes de changement de statut -->
                            @if($event->status === 'published')
                                <div x-show="status === 'archived'" x-transition
                                     class="mt-2 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                                    <p class="text-sm text-orange-700">
                                        ⚠️ Attention : Passer ce événement en "Archivé" le masquera du public.
                                    </p>
                                </div>
                            @endif

                            @if($event->status === 'draft')
                                <div x-show="status === 'published'" x-transition
                                     class="mt-2 p-3 bg-green-50 border border-green-200 rounded-lg">
                                    <p class="text-sm text-green-700">
                                        ✅ Publier cet événement le rendra visible sur le site public.
                                    </p>
                                </div>
                            @endif
                        </div>

                        <!-- Nombre maximum de participants -->
                        <div>
                            <label for="max_participants" class="block text-sm font-medium text-gray-700 mb-2">
                                Nombre maximum de participants
                            </label>
                            <input type="number" 
                                   id="max_participants" 
                                   name="max_participants" 
                                   value="{{ old('max_participants', $event->max_participants) }}"
                                   min="1"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                        </div>

                        <!-- Options avancées -->
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Options</h3>
                            
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" 
                                       name="featured" 
                                       value="1"
                                       {{ old('featured', $event->featured) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-club-blue focus:ring-club-blue">
                                <span class="text-sm text-gray-700">
                                    ⭐ Mettre en avant cet événement
                                </span>
                            </label>
                            
                            @if($event->featured && !old('featured'))
                                <p class="text-xs text-yellow-600 mt-1 ml-6">
                                    Actuellement mis en avant
                                </p>
                            @endif
                        </div>

                        <!-- Notes privées -->
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                Notes privées (admin uniquement)
                            </label>
                            <textarea id="notes" 
                                      name="notes" 
                                      rows="3"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent resize-y">{{ old('notes', $event->notes) }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Informations de modification -->
                <div class="mt-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-center space-x-2 text-sm text-blue-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <strong>Créé le :</strong> {{ $event->created_at->format('d/m/Y à H:i') }}
                            @if($event->updated_at != $event->created_at)
                                • <strong>Dernière modification :</strong> {{ $event->updated_at->format('d/m/Y à H:i') }}
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row justify-between items-center space-y-2 sm:space-y-0 sm:space-x-3 mt-8 pt-6 border-t">
                    <!-- Actions de suppression (si possible) -->
                    <div>
                        @if($event->canBeDeleted())
                            <form action="{{ route('admin.events.destroy', $event) }}" method="POST" class="inline" id="eventDeletion">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                        for='eventDeletion'
                                        class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm"
                                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ? Cette action est irréversible.')">
                                    🗑️ Supprimer définitivement
                                </button>
                            </form>
                        @else
                            <p class="text-xs text-gray-500">
                                ℹ️ Cet événement ne peut pas être supprimé car il est publié.
                            </p>
                        @endif
                    </div>

                    <!-- Actions principales -->
                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                        <a href="{{ route('admin.events.show', $event) }}" 
                           class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-medium transition-colors text-center">
                            Annuler
                        </a>
                        <button type="submit" 
                                for="eventUpdate"
                                class="bg-club-blue hover:bg-club-blue-light text-white px-6 py-2 rounded-lg font-medium transition-colors">
                            💾 Enregistrer les modifications
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </x-admin-block>
</x-app-layout>