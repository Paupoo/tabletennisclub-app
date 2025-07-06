<x-guest-layout :title="($article['title'] ?? 'Article') . ' - Ace Table Tennis Club'">
    <x-navigation :fixed="false" />
    
    <!-- Article Header -->
    <div class="bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-8">
            <!-- Breadcrumb -->
            <nav class="flex items-center space-x-2 text-sm text-gray-500 mb-8">
                <a href="{{ route('home') }}" class="hover:text-club-blue transition-colors">Accueil</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
                <a href="{{ route('articles.index') }}" class="hover:text-club-blue transition-colors">Actualités</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
                <span class="text-gray-900">{{ $article['title'] ?? 'Article' }}</span>
            </nav>
            
            <!-- Article Meta -->
            <div class="flex flex-wrap items-center gap-4 mb-6">
                <span class="@if(($article['category'] ?? '') === 'Compétition') bg-club-blue text-white @elseif(($article['category'] ?? '') === 'Formation') bg-club-yellow text-club-blue @else bg-gray-100 text-gray-800 @endif text-sm font-medium px-4 py-2 rounded-full">
                    {{ $article['category'] ?? 'Actualité' }}
                </span>
                <div class="flex items-center text-gray-500 text-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    {{ $article['date'] ?? date('d F Y') }}
                </div>
                @if(isset($article['author']))
                    <div class="flex items-center text-gray-500 text-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Par {{ $article['author'] }}
                    </div>
                @endif
                @if(isset($article['reading_time']))
                    <div class="flex items-center text-gray-500 text-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        {{ $article['reading_time'] }} min de lecture
                    </div>
                @endif
            </div>
            
            <!-- Title -->
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6 leading-tight">
                {{ $article['title'] ?? 'Titre de l\'article' }}
            </h1>
            
            <!-- Excerpt -->
            @if(isset($article['excerpt']))
                <p class="text-xl text-gray-600 leading-relaxed mb-8">
                    {{ $article['excerpt'] }}
                </p>
            @endif
        </div>
    </div>
    
    <!-- Featured Image -->
    @if(isset($article['image']))
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 mb-12">
            <div class="aspect-video bg-gray-100 rounded-2xl overflow-hidden">
                <img src="{{ Storage::url($article->image) }}" alt="{{ $article['title'] ?? 'Image de l\'article' }}" 
                     class="w-full h-full object-cover">
            </div>
            @if(isset($article['image_caption']))
                <p class="text-sm text-gray-500 text-center mt-4 italic">{{ $article['image_caption'] }}</p>
            @endif
        </div>
    @endif
    
    <!-- Article Content -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="prose prose-lg max-w-none">
            <div class="text-gray-700 leading-relaxed">
                {!! $article['content'] ?? '<p>Contenu de l\'article à venir...</p>' !!}
            </div>
        </div>
        
        <!-- Share -->
        <div class="mt-12 pt-8 border-t border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Partager cet article</h3>
            <div class="flex space-x-4">
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->fullUrl()) }}" 
                   target="_blank" rel="noopener"
                   class="flex items-center space-x-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    <span>Facebook</span>
                </a>
                <a href="https://twitter.com/intent/tweet?url={{ urlencode(request()->fullUrl()) }}&text={{ urlencode($article['title'] ?? '') }}" 
                   target="_blank" rel="noopener"
                   class="flex items-center space-x-2 bg-sky-500 text-white px-4 py-2 rounded-lg hover:bg-sky-600 transition-colors">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                    </svg>
                    <span>Twitter</span>
                </a>
                <button @click="copyToClipboard()" x-data="{ copied: false }" 
                        class="flex items-center space-x-2 bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    <span x-text="copied ? 'Copié !' : 'Copier le lien'"></span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Related Articles -->
    @if(isset($relatedArticles) && count($relatedArticles) > 0)
        <div class="bg-gray-50 py-16 mt-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">Articles Similaires</h2>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($relatedArticles as $relatedArticle)
                        <x-news-card :article="$relatedArticle" />
                    @endforeach
                </div>
            </div>
        </div>
    @endif
    
    <!-- Back to News -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center">
            <a href="{{ route('public.articles.index') }}" 
               class="inline-flex items-center space-x-2 bg-club-blue text-white px-6 py-3 rounded-lg hover:bg-club-blue-light transition-colors font-semibold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                <span>Retour aux actualités</span>
            </a>
        </div>
    </div>

    <script>
        function copyToClipboard() {
            navigator.clipboard.writeText(window.location.href).then(() => {
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            });
        }
    </script>
</x-guest-layout>
