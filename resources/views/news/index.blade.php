<x-app-layout title="Actualités - Ace Table Tennis Club">
    <x-navigation :fixed="false" />
    
    <!-- Header -->
    <div class="bg-gradient-to-r from-club-blue to-club-blue-light text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">Actualités du Club</h1>
            <p class="text-xl opacity-90">Toutes les dernières nouvelles et événements d'Ace TTC</p>
        </div>
    </div>

    <!-- Filtres -->
    <div class="bg-white border-b sticky top-16 z-40" x-data="newsFilters">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center space-y-4 lg:space-y-0">
                <!-- Filtres par date -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-4 sm:space-y-0 sm:space-x-4">
                    <div class="flex items-center space-x-2">
                        <label for="year" class="text-sm font-medium text-gray-700">Année:</label>
                        <select x-model="selectedYear" @change="updateFilters()" 
                                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                            <option value="">Toutes les années</option>
                            @foreach($years ?? ['2024', '2023', '2022'] as $year)
                                <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <label for="month" class="text-sm font-medium text-gray-700">Mois:</label>
                        <select x-model="selectedMonth" @change="updateFilters()" 
                                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                            <option value="">Tous les mois</option>
                            <option value="01" {{ request('month') == '01' ? 'selected' : '' }}>Janvier</option>
                            <option value="02" {{ request('month') == '02' ? 'selected' : '' }}>Février</option>
                            <option value="03" {{ request('month') == '03' ? 'selected' : '' }}>Mars</option>
                            <option value="04" {{ request('month') == '04' ? 'selected' : '' }}>Avril</option>
                            <option value="05" {{ request('month') == '05' ? 'selected' : '' }}>Mai</option>
                            <option value="06" {{ request('month') == '06' ? 'selected' : '' }}>Juin</option>
                            <option value="07" {{ request('month') == '07' ? 'selected' : '' }}>Juillet</option>
                            <option value="08" {{ request('month') == '08' ? 'selected' : '' }}>Août</option>
                            <option value="09" {{ request('month') == '09' ? 'selected' : '' }}>Septembre</option>
                            <option value="10" {{ request('month') == '10' ? 'selected' : '' }}>Octobre</option>
                            <option value="11" {{ request('month') == '11' ? 'selected' : '' }}>Novembre</option>
                            <option value="12" {{ request('month') == '12' ? 'selected' : '' }}>Décembre</option>
                        </select>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <label for="category" class="text-sm font-medium text-gray-700">Catégorie:</label>
                        <select x-model="selectedCategory" @change="updateFilters()" 
                                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                            <option value="">Toutes les catégories</option>
                            <option value="Compétition" {{ request('category') == 'Compétition' ? 'selected' : '' }}>Compétition</option>
                            <option value="Formation" {{ request('category') == 'Formation' ? 'selected' : '' }}>Formation</option>
                            <option value="Partenariat" {{ request('category') == 'Partenariat' ? 'selected' : '' }}>Partenariat</option>
                            <option value="Vie du Club" {{ request('category') == 'Vie du Club' ? 'selected' : '' }}>Vie du Club</option>
                            <option value="Portrait" {{ request('category') == 'Portrait' ? 'selected' : '' }}>Portrait</option>
                            <option value="Infrastructure" {{ request('category') == 'Infrastructure' ? 'selected' : '' }}>Infrastructure</option>
                        </select>
                    </div>
                </div>
                
                <!-- Résultats et tri -->
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600">
                        {{ count($articles ?? []) }} article{{ count($articles ?? []) > 1 ? 's' : '' }} trouvé{{ count($articles ?? []) > 1 ? 's' : '' }}
                    </span>
                    <select x-model="sortOrder" @change="updateFilters()" 
                            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent text-sm">
                        <option value="desc" {{ request('sort') == 'desc' ? 'selected' : '' }}>Plus récent</option>
                        <option value="asc" {{ request('sort') == 'asc' ? 'selected' : '' }}>Plus ancien</option>
                    </select>
                </div>
            </div>
            
            <!-- Filtres actifs -->
            <div class="mt-4 flex flex-wrap gap-2" x-show="hasActiveFilters()">
                <span class="text-sm text-gray-600">Filtres actifs:</span>
                <template x-if="selectedYear">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-club-blue text-white">
                        Année: <span x-text="selectedYear"></span>
                        <button @click="selectedYear = ''; updateFilters()" class="ml-2 hover:text-club-yellow">×</button>
                    </span>
                </template>
                <template x-if="selectedMonth">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-club-blue text-white">
                        Mois: <span x-text="getMonthName(selectedMonth)"></span>
                        <button @click="selectedMonth = ''; updateFilters()" class="ml-2 hover:text-club-yellow">×</button>
                    </span>
                </template>
                <template x-if="selectedCategory">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-club-blue text-white">
                        <span x-text="selectedCategory"></span>
                        <button @click="selectedCategory = ''; updateFilters()" class="ml-2 hover:text-club-yellow">×</button>
                    </span>
                </template>
                <button @click="clearAllFilters()" class="text-xs text-club-blue hover:text-club-blue-light font-medium">
                    Effacer tous les filtres
                </button>
            </div>
        </div>
    </div>

    <!-- Articles -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @if(count($articles ?? []) > 0)
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($articles as $index => $article)
                    <x-news-card-full :article="$article" :index="$index" />
                @endforeach
            </div>
            
            <!-- Pagination -->
            @if(isset($pagination) && $pagination['total_pages'] > 1)
                <div class="mt-12">
                    <x-pagination :pagination="$pagination" />
                </div>
            @endif
        @else
            <!-- Aucun résultat -->
            <div class="text-center py-16">
                <div class="w-24 h-24 mx-auto mb-6 bg-gray-100 rounded-full flex items-center justify-center">
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9.5a2.5 2.5 0 00-2.5-2.5H15"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Aucun article trouvé</h3>
                <p class="text-gray-600 mb-6">Essayez de modifier vos critères de recherche ou consultez toutes les actualités.</p>
                <button @click="clearAllFilters()" class="bg-club-blue text-white px-6 py-3 rounded-lg hover:bg-club-blue-light transition-colors">
                    Voir toutes les actualités
                </button>
            </div>
        @endif
    </div>

    <!-- Newsletter Signup -->
    <div class="bg-gray-50 py-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold text-gray-900 mb-4">Restez Informé</h2>
            <p class="text-xl text-gray-600 mb-8">
                Recevez les dernières actualités du club directement dans votre boîte mail
            </p>
            <form class="flex flex-col sm:flex-row gap-4 max-w-md mx-auto">
                <input type="email" placeholder="Votre adresse email" 
                       class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                <button type="submit" 
                        class="bg-club-blue text-white px-6 py-3 rounded-lg hover:bg-club-blue-light transition-colors font-semibold">
                    S'abonner
                </button>
            </form>
        </div>
    </div>
</x-app-layout>
