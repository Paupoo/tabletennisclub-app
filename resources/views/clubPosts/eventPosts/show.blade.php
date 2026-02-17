{{-- resources/views/clubAdmin/eventPosts/show.blade.php --}}
<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <!-- En-tête avec informations principales -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-4 space-y-4 sm:space-y-0">
                <div class="flex-1">
                    <div class="flex items-center space-x-4 mb-3">
                        <div class="flex-shrink-0 h-16 w-16">
                            <div class="h-16 w-16 rounded-full bg-club-blue flex items-center justify-center text-2xl">
                                {{ $eventPost->icon }}
                            </div>
                        </div>
                        <div>
                            <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">
                                {{ $eventPost->title }}
                                @if($eventPost->featured)
                                    <span class="ml-3 inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                        ⭐ Mis en avant
                                    </span>
                                @endif
                            </h2>
                            <p class="text-gray-600 text-sm sm:text-base leading-relaxed">
                                {{ $eventPost->description }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Status et Category Badges -->
                <div class="flex flex-col space-y-2">
                    <span class="{{ $eventPost->getStatusBadgeClasses() }} px-3 py-1 rounded-full text-xs sm:text-sm font-medium text-center">
                        {{ $eventPost->status_label }}
                    </span>
                    <span class="{{ $eventPost->getCategoryBadgeClasses() }} px-3 py-1 rounded-full text-xs sm:text-sm font-medium text-center">
                        {{ $eventPost->category_label }}
                    </span>
                </div>
            </div>

            <!-- Actions principales -->
            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                <!-- Actions de statut -->
                @if($eventPost->status === 'draft')
                    <form action="{{ route('clubPosts.eventPosts.publish', $eventPost) }}" method="POST" class="inline">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Publier maintenant
                        </button>
                    </form>
                @elseif($eventPost->status === 'published')
                    <form action="{{ route('clubPosts.eventPosts.archive', $eventPost) }}" method="POST" class="inline">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                                class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8l6 6 6-6"></path>
                            </svg>
                            Archiver
                        </button>
                    </form>
                @endif

                <!-- Modifier -->
                <a href="{{ route('clubPosts.eventPosts.edit', $eventPost) }}"
                   class="bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto text-center inline-flex items-center justify-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Modifier
                </a>

                <!-- Dupliquer -->
                <form action="{{ route('clubPosts.eventPosts.duplicate', $eventPost) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                        Dupliquer
                    </button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Informations détaillées -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Détails de l'événement -->
                <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                    <h3 class="text-lg sm:text-xl font-bold text-club-blue mb-4">Détails de l'événement</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div class="border-b border-gray-100 pb-3">
                                <span class="text-xs sm:text-sm font-medium text-gray-500">Date</span>
                                <p class="text-sm sm:text-base text-gray-800 mt-1 flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a2 2 0 012-2h8a2 2 0 012 2v4m-6 12h8a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    {{ $eventPost->formatted_date }}
                                    @if($eventPost->is_upcoming)
                                        <span class="ml-2 text-green-600 text-xs">À venir</span>
                                    @elseif($eventPost->is_past)
                                        <span class="ml-2 text-gray-500 text-xs">Passé</span>
                                    @endif
                                </p>
                            </div>

                            <div class="border-b border-gray-100 pb-3">
                                <span class="text-xs sm:text-sm font-medium text-gray-500">Horaires</span>
                                <p class="text-sm sm:text-base text-gray-800 mt-1 flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    {{ $eventPost->formatted_time }}
                                </p>
                            </div>

                            <div class="border-b border-gray-100 pb-3">
                                <span class="text-xs sm:text-sm font-medium text-gray-500">Lieu</span>
                                <p class="text-sm sm:text-base text-gray-800 mt-1 flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    {{ $eventPost->location }}
                                </p>
                            </div>

                            @if($eventPost->price)
                                <div class="border-b border-gray-100 pb-3">
                                    <span class="text-xs sm:text-sm font-medium text-gray-500">Prix</span>
                                    <p class="text-sm sm:text-base text-gray-800 mt-1 flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                        </svg>
                                        {{ $eventPost->price }}
                                    </p>
                                </div>
                            @endif
                        </div>

                        <div class="space-y-4">
                            @if($eventPost->max_participants)
                                <div class="border-b border-gray-100 pb-3">
                                    <span class="text-xs sm:text-sm font-medium text-gray-500">Participants max</span>
                                    <p class="text-sm sm:text-base text-gray-800 mt-1 flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                        {{ $eventPost->max_participants }} personnes
                                    </p>
                                </div>
                            @endif

                            <div class="border-b border-gray-100 pb-3">
                                <span class="text-xs sm:text-sm font-medium text-gray-500">Créé le</span>
                                <p class="text-sm sm:text-base text-gray-800 mt-1">
                                    {{ $eventPost->created_at->format('d/m/Y à H:i') }}
                                </p>
                            </div>

                            @if($eventPost->updated_at != $eventPost->created_at)
                                <div class="border-b border-gray-100 pb-3">
                                    <span class="text-xs sm:text-sm font-medium text-gray-500">Dernière modification</span>
                                    <p class="text-sm sm:text-base text-gray-800 mt-1">
                                        {{ $eventPost->updated_at->format('d/m/Y à H:i') }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Notes privées -->
                @if($eventPost->notes)
                    <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                        <h3 class="text-lg sm:text-xl font-bold text-club-blue mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                            </svg>
                            Notes privées
                        </h3>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $eventPost->notes }}</p>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Sidebar avec actions et informations -->
            <div class="space-y-6">
                <!-- Aperçu public -->
                <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                    <h3 class="text-lg font-bold text-club-blue mb-4">Aperçu public</h3>
                    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg p-4">
                        <div class="bg-white rounded-lg p-4 shadow-sm">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-club-blue rounded-full flex items-center justify-center text-lg">
                                        {{ $eventPost->icon }}
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-lg font-semibold text-gray-900 mb-1">{{ $eventPost->title }}</h4>
                                    <p class="text-gray-600 text-sm mb-2">{{ Str::limit($eventPost->description, 80) }}</p>
                                    <div class="space-y-1 text-xs text-gray-500">
                                        <div class="flex items-center space-x-1">
                                            <span>📅</span>
                                            <span>{{ $eventPost->formatted_date }}</span>
                                        </div>
                                        <div class="flex items-center space-x-1">
                                            <span>⏰</span>
                                            <span>{{ $eventPost->formatted_time }}</span>
                                        </div>
                                        <div class="flex items-center space-x-1">
                                            <span>📍</span>
                                            <span>{{ $eventPost->location }}</span>
                                        </div>
                                        @if($eventPost->price)
                                            <div class="flex items-center space-x-1">
                                                <span>💰</span>
                                                <span>{{ $eventPost->price }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        @if($eventPost->status === 'published')
                            ✅ Cet événement est visible par le public
                        @elseif($eventPost->status === 'draft')
                            👁️ Visible uniquement par les administrateurs
                        @else
                            📦 Cet événement est archivé et masqué
                        @endif
                    </p>
                </div>

                <!-- Actions rapides -->
                <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                    <h3 class="text-lg font-bold text-club-blue mb-4">Actions rapides</h3>
                    <div class="space-y-3">
                        @if($eventPost->status !== 'published')
                            <form action="{{ route('clubPosts.eventPosts.publish', $eventPost) }}" method="POST" class="w-full">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center">
                                    🚀 Publier
                                </button>
                            </form>
                        @endif

                        @if($eventPost->canBeDeleted())
                            <form action="{{ route('clubPosts.eventPosts.destroy', $eventPost) }}" method="POST" class="w-full">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm flex items-center justify-center"
                                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ? Cette action est irréversible.')">
                                    🗑️ Supprimer définitivement
                                </button>
                            </form>
                        @endif

                        <a href="{{ route('clubPosts.eventPosts.index') }}"
                           class="w-full bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm text-center block">
                            ← Retour à la liste
                        </a>
                    </div>
                </div>

                <!-- Statistiques rapides -->
                <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                    <h3 class="text-lg font-bold text-club-blue mb-4">Informations</h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Statut :</span>
                            <span class="{{ $eventPost->getStatusBadgeClasses() }} px-2 py-1 rounded-full text-xs font-medium">
                                {{ $eventPost->status_label }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Catégorie :</span>
                            <span class="{{ $eventPost->getCategoryBadgeClasses() }} px-2 py-1 rounded-full text-xs font-medium">
                                {{ $eventPost->category_label }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Visibilité :</span>
                            <span class="text-gray-800">
                                @if($eventPost->featured)
                                    ⭐ Mis en avant
                                @else
                                    👁️ Normal
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-admin-block>
</x-app-layout>
