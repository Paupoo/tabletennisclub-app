@props(['articles' => collect([])])

<section id="news" class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 animate-on-scroll">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">Actualités du Club</h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                Restez informé des dernières nouvelles, événements et réussites de notre communauté
            </p>
        </div>
        
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            @foreach($articles as $article)
                <x-news-card :article="$article" />
            @endforeach
        </div>
        
        <div class="text-center mt-12 animate-on-scroll">
            <a href="{{ route('public.articles.index') ?? '#' }}" class="bg-club-blue text-white px-8 py-3 rounded-lg font-semibold hover:bg-club-blue-light transition-colors inline-flex items-center">
                Voir Toutes les Actualités
                <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>
    </div>
</section>
