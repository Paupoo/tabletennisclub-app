{{-- resources/views/admin/events/create.blade.php --}}
<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
            {{-- Header --}}
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-club-blue">{{ __('Create a new event') }}</h2>
                    <p class="text-gray-600 mt-1">{{ __('Fill in the information below to create an event.') }}</p>
                </div>
                <a href="{{ route('admin.events.index') }}" 
                   class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors">
                    ← {{ __('Back to list') }}
                </a>
            </div>

            {{-- Affichage des erreurs --}}
            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
                    <strong class="font-bold">{{ __('Error') }}{{ $errors->count() > 1 ? 's' : '' }} :</strong>
                    <ul class="mt-2 list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('admin.events.store') }}" 
                  method="POST" 
                  x-data="eventForm()"
                  x-init="init()">
                @csrf

                {{-- Étape 1 : Choix du type d'événement --}}
                <div class="mb-8 p-6 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg border-2 border-blue-200">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        {{ __('Step 1: Choose the type of event') }}
                    </h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        @foreach(\App\Enums\EventTypeEnum::cases() as $eventType)
                            <label class="relative cursor-pointer">
                                <input type="radio" 
                                       name="type" 
                                       value="{{ $eventType->value }}"
                                       x-model="type"
                                       @change="onTypeChange()"
                                       class="peer sr-only"
                                       {{ old('type') === $eventType->value ? 'checked' : '' }}>
                                
                                <div class="p-6 bg-white border-2 border-gray-200 rounded-lg transition-all
                                            peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:shadow-lg
                                            hover:border-blue-300 hover:shadow-md">
                                    <div class="text-4xl text-center mb-3">{{ $eventType->getIcon() }}</div>
                                    <div class="text-center font-semibold text-gray-800">{{ $eventType->getLabel() }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    
                    <p class="text-sm text-gray-600 mt-4" x-show="!type">
                        {{ __('Please select a type to continue') }}
                    </p>
                </div>

                {{-- Contenu principal (visible seulement si un type est sélectionné) --}}
                <div x-show="type" x-transition class="space-y-8">
                    
                    {{-- Étape 2 : Informations communes --}}
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        {{-- Colonne gauche --}}
                        <div class="space-y-6">
                            {{-- Titre --}}
                            <div>
                                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Event title') }} *
                                </label>
                                <input type="text" 
                                       id="title" 
                                       name="title" 
                                       value="{{ old('title') }}"
                                       x-model="title"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                                       :placeholder="getPlaceholder('title')"
                                       required>
                            </div>

                            {{-- Description --}}
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Description') }} *
                                </label>
                                <textarea id="description" 
                                          name="description" 
                                          rows="4"
                                          x-model="description"
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent resize-y"
                                          :placeholder="getPlaceholder('description')"
                                          required>{{ old('description') }}</textarea>
                            </div>

                            {{-- Icône --}}
                            <div>
                                <label for="icon" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Icon') }}
                                </label>
                                <input type="text" 
                                       id="icon" 
                                       name="icon" 
                                       value="{{ old('icon') }}"
                                       x-model="icon"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent text-center text-2xl"
                                       maxlength="10">
                                <p class="text-xs text-gray-500 mt-1">{{ __('Emoji or short text (auto-filled based on type)') }}</p>
                            </div>

                            {{-- Lieu --}}
                            <div>
                                <label for="location" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Location') }} *
                                </label>
                                <input type="text" 
                                       id="location" 
                                       name="location" 
                                       value="{{ old('location') }}"
                                       x-model="location"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                                       :placeholder="getPlaceholder('location')"
                                       required>
                            </div>

                            {{-- Prix --}}
                            <div>
                                <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Price / pricing information') }}
                                </label>
                                <input type="text" 
                                       id="price" 
                                       name="price" 
                                       value="{{ old('price') }}"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                                       placeholder="{{ __('Free, 25€, Food included...') }}">
                                <p class="text-xs text-gray-500 mt-1">{{ __('Leave empty if free or not applicable') }}</p>
                            </div>
                        </div>

                        {{-- Colonne droite --}}
                        <div class="space-y-6">
                            {{-- Date --}}
                            <div>
                                <label for="event_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Event date') }} *
                                </label>
                                <input type="date" 
                                       id="event_date" 
                                       name="event_date" 
                                       value="{{ old('event_date') }}"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                                       required>
                            </div>

                            {{-- Heures --}}
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">
                                        {{ __('Start time') }} *
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
                                        {{ __('End time') }}
                                    </label>
                                    <input type="time" 
                                           id="end_time" 
                                           name="end_time" 
                                           value="{{ old('end_time') }}"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                                </div>
                            </div>

                            {{-- Statut --}}
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Status') }} *
                                </label>
                                <select id="status" 
                                        name="status" 
                                        x-model="status"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                                        required>
                                    @foreach(\App\Enums\EventStatusEnum::cases() as $eventStatus)
                                        <option value="{{ $eventStatus->value }}">{{ $eventStatus->getLabel() }}</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-500 mt-1">
                                    <span x-show="status === 'DRAFT'" class="text-gray-600">💾 {{ __('Draft: visible only by admins') }}</span>
                                    <span x-show="status === 'PUBLISHED'" class="text-green-600">✅ {{ __('Published: visible by everyone') }}</span>
                                    <span x-show="status === 'ARCHIVED'" class="text-red-600">📦 {{ __('Archived: hidden but kept') }}</span>
                                </p>
                            </div>

                            {{-- Nombre max de participants --}}
                            <div>
                                <label for="max_participants" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Maximum number of participants') }}
                                </label>
                                <input type="number" 
                                       id="max_participants" 
                                       name="max_participants" 
                                       value="{{ old('max_participants') }}"
                                       min="1"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent"
                                       placeholder="50">
                                <p class="text-xs text-gray-500 mt-1">{{ __('Leave empty if unlimited') }}</p>
                            </div>

                            {{-- Options avancées --}}
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <h3 class="text-sm font-medium text-gray-700 mb-3">{{ __('Options') }}</h3>
                                
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input type="checkbox" 
                                           name="featured" 
                                           value="1"
                                           {{ old('featured') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-club-blue focus:ring-club-blue">
                                    <span class="text-sm text-gray-700">
                                        ⭐ {{ __('Feature this event') }}
                                    </span>
                                </label>
                                <p class="text-xs text-gray-500 mt-1 ml-6">{{ __('Featured events appear first') }}</p>
                            </div>

                            {{-- Notes privées --}}
                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ __('Private notes (admin only)') }}
                                </label>
                                <textarea id="notes" 
                                          name="notes" 
                                          rows="3"
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent resize-y"
                                          placeholder="{{ __('Internal notes, reminders, contacts...') }}">{{ old('notes') }}</textarea>
                                <p class="text-xs text-gray-500 mt-1">{{ __('These notes are only visible to administrators') }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Étape 3 : Champs spécifiques selon le type --}}
                    
                    {{-- TRAINING --}}
                    <div x-show="type === 'TRAINING'" x-transition class="border-t pt-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                            <span class="text-2xl mr-2">🎯</span>
                            {{ __('Training specific information') }}
                        </h3>
                        
                        @include('admin.events.partials.training-fields')
                    </div>

                    {{-- INTERCLUB --}}
                    <div x-show="type === 'INTERCLUB'" x-transition class="border-t pt-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                            <span class="text-2xl mr-2">🏓</span>
                            {{ __('Interclub specific information') }}
                        </h3>
                        
                        @include('admin.events.partials.interclub-fields')
                    </div>

                    {{-- TOURNAMENT --}}
                    <div x-show="type === 'TOURNAMENT'" x-transition class="border-t pt-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-6 flex items-center">
                            <span class="text-2xl mr-2">🏆</span>
                            {{ __('Tournament specific information') }}
                        </h3>
                        
                        @include('admin.events.partials.tournament-fields')
                    </div>

                    {{-- Actions --}}
                    <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-3 pt-6 border-t">
                        <a href="{{ route('admin.events.index') }}" 
                           class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-medium transition-colors text-center">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" 
                                name="action"
                                value="draft"
                                class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                            💾 {{ __('Save as draft') }}
                        </button>
                        <button type="submit" 
                                name="action"
                                value="publish"
                                class="bg-club-blue hover:bg-club-blue-light text-white px-6 py-2 rounded-lg font-medium transition-colors">
                            🚀 {{ __('Save and publish') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </x-admin-block>

    <script>
        function eventForm() {
            return {
                type: '{{ old("type") }}',
                status: '{{ old("status", "DRAFT") }}',
                title: '{{ old("title") }}',
                description: '{{ old("description") }}',
                location: '{{ old("location") }}',
                icon: '{{ old("icon") }}',
                isHome: {{ old('is_home') ? 'true' : 'false' }},
                
                init() {
                    this.updateIcon();
                },
                
                onTypeChange() {
                    this.updateIcon();
                    this.updatePlaceholders();
                },
                
                updateIcon() {
                    const icons = {
                        'TRAINING': '🎯',
                        'INTERCLUB': '🏓',
                        'TOURNAMENT': '🏆'
                    };
                    
                    if (this.type && icons[this.type] && !this.icon) {
                        this.icon = icons[this.type];
                    }
                },
                
                onHomeChange() {
                    // Reset fields when toggling home/away
                    if (this.isHome) {
                        const addressField = document.getElementById('interclub_address');
                        if (addressField) addressField.value = '';
                    } else {
                        const roomField = document.getElementById('interclub_room_id');
                        if (roomField) roomField.value = '';
                    }
                },
                
                getPlaceholder(field) {
                    const placeholders = {
                        'TRAINING': {
                            title: '{{ __("Weekly training - Beginners") }}',
                            description: '{{ __("Training session focused on technique and strategy...") }}',
                            location: '{{ __("Main hall, Demeester") }}'
                        },
                        'INTERCLUB': {
                            title: '{{ __("Interclub vs Club X") }}',
                            description: '{{ __("Interclub match for the championship...") }}',
                            location: '{{ __("Club address or home") }}'
                        },
                        'TOURNAMENT': {
                            title: '{{ __("New Year Championship") }}',
                            description: '{{ __("Annual tournament open to all members...") }}',
                            location: '{{ __("Main hall, Demeester") }}'
                        }
                    };
                    
                    return placeholders[this.type]?.[field] || '';
                }
            }
        }
    </script>
</x-app-layout>
