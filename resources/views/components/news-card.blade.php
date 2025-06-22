@props(['article'])

<article class="bg-white rounded-lg border border-gray-200 overflow-hidden hover:border-club-blue transition-colors animate-on-scroll group">
    <div class="aspect-video bg-gray-100 overflow-hidden">
        <img src="{{ asset($article['image']) }}" alt="{{ $article['title'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
    </div>
    
    <div class="p-6">
        <div class="flex items-center justify-between mb-3">
            <span class="@if($article['category'] === 'Compétition') bg-club-blue text-white @elseif($article['category'] === 'Formation') bg-club-yellow text-club-blue @else bg-gray-100 text-gray-800 @endif text-xs font-medium px-3 py-1 rounded-full">
                {{ $article['category'] }}
            </span>
            <time class="text-sm text-gray-500">{{ $article['date'] }}</time>
        </div>
        
        <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-club-blue transition-colors">
            <a href="{{ route('articles.show', $article['slug']) ?? '#' }}">
                {{ $article['title'] }}
            </a>
        </h3>
        
        <p class="text-gray-600 mb-4 line-clamp-3">
            {{ $article['excerpt'] }}
        </p>
        
        <a href="{{ route('articles.show', $article['slug']) ?? '#' }}" class="text-club-blue hover:text-club-blue-light font-semibold text-sm inline-flex items-center">
            Lire la suite
            <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </a>
    </div>
</article>
