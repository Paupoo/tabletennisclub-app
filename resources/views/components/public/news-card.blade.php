@props(['article'])

<article class="bg-white rounded-lg border border-gray-200 overflow-hidden hover:border-club-blue transition-colors animate-on-scroll group">
    <div class="aspect-video bg-gray-100 overflow-hidden">
        <img src="{{ Storage::url($article->image) }}" alt="{{ $article->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
    </div>

    <div class="p-6">
        <div class="flex items-center justify-between mb-3">
            @php
                $categoryTone = match($article->category) {
                    \App\Domains\Shared\Enums\NewsPostCategoryEnum::COMPETITION => 'primary',
                    \App\Domains\Shared\Enums\NewsPostCategoryEnum::TRAINING    => 'secondary',
                    default                                                      => 'neutral',
                };
            @endphp
            <x-badge :tone="$categoryTone" solid>{{ $article->category->getLabel() }}</x-badge>
            <time class="text-sm text-gray-500">{{ $article->created_at?->translatedFormat('d F Y') }}</time>
        </div>

        <h3 class="text-xl font-bold text-gray-900 mb-3 group-hover:text-club-blue transition-colors">
            <a href="{{ route('public.clubPosts.show', $article->slug) }}">
                {{ $article->title }}
            </a>
        </h3>

        <p class="text-gray-600 mb-4 line-clamp-3">
            {{ \Illuminate\Support\Str::words(strip_tags(\Illuminate\Support\Str::markdown($article->content ?? '')), 25, '…') }}
        </p>

        <div class="flex items-center justify-between">
            <a href="{{ route('public.clubPosts.show', $article->slug) }}" class="text-club-blue hover:text-club-blue-light font-semibold text-sm inline-flex items-center">
                Lire la suite
                <x-icon name="o-chevron-right" class="ml-1 w-4 h-4" />
            </a>

            @if($article->reading_time)
                <span class="text-xs text-gray-500">{{ $article->reading_time }} min de lecture</span>
            @endif
        </div>
    </div>
</article>
