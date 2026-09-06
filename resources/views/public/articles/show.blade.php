<x-guest-layout
    :title="($article->title ?? 'Article') . ' - ' . config('club.name')"
    :description="\Illuminate\Support\Str::limit(strip_tags($article->content ?? ''), 160)"
>

    {{-- Header --}}
    <div class="bg-white border-b border-gray-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-8">

            {{-- Breadcrumb --}}
            <nav class="flex items-center gap-2 text-sm text-gray-400 mb-6">
                <a href="{{ route('home') }}" class="hover:text-gray-600 transition-colors">Accueil</a>
                <span>/</span>
                <a href="{{ route('public.clubPosts.index') }}" class="hover:text-gray-600 transition-colors">{{ __('News') }}</a>
                <span>/</span>
                <span class="text-gray-600 truncate">{{ Str::words($article->title ?? 'Article', 6, '[...]') }}</span>
            </nav>

            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 leading-tight max-w-4xl">
                {{ $article->title ?? 'Titre de l\'article' }}
            </h1>
        </div>
    </div>

    {{-- Featured Image --}}
    @if($article->image)
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
            <div class="h-72 md:h-96 rounded-2xl overflow-hidden shadow-sm">
                <img src="{{ Storage::url($article->image) }}" alt="{{ $article->title ?? 'Image de l\'article' }}"
                     class="w-full h-full object-cover" style="object-position: {{ $article->image_position }}">
            </div>
        </div>
    @endif

    {{-- Three-part layout: meta card (top on mobile), content, rest of sidebar --}}
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="flex flex-col lg:grid lg:grid-cols-3 lg:gap-14">

            {{-- Meta card: first on mobile, right column row 1 on desktop --}}
            <div class="lg:col-start-3 lg:row-start-1 lg:self-start mb-8 lg:mb-0">
                <div class="sticky lg:top-8">
                    <div class="bg-gray-50 rounded-2xl p-6 space-y-4">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">À propos</h3>

                        <div class="space-y-3 text-sm text-gray-600">
                            @if($article->category)
                                <div class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                    <span>{{ $article->category->getLabel() }}</span>
                                </div>
                            @endif
                            <div class="flex items-center gap-3">
                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>{{ $article->created_at?->translatedFormat('d F Y') ?? date('d F Y') }}</span>
                            </div>
                            @if($article->user)
                                <div class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    <span>{{ $article->user->fullName }}</span>
                                </div>
                            @endif
                            @if($article->reading_time)
                                <div class="flex items-center gap-3">
                                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>{{ $article->reading_time }} min de lecture</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Main content: second on mobile, left 2 columns on desktop --}}
            <div class="lg:col-span-2 lg:col-start-1 lg:row-start-1 lg:row-span-2">
                <div class="prose prose-lg max-w-none
                    prose-headings:font-bold prose-headings:text-gray-900
                    prose-p:text-gray-700 prose-p:leading-relaxed
                    prose-blockquote:border-club-blue prose-blockquote:text-gray-600
                    prose-li:text-gray-700
                    prose-a:text-club-blue">
                    {!! $renderedContent ?? '<p>Contenu de l\'article à venir...</p>' !!}
                </div>
            </div>

            {{-- Rest of sidebar: last on mobile, right column row 2 on desktop --}}
            <div class="lg:col-start-3 lg:row-start-2 lg:self-start mt-8 lg:mt-0">
                <div class="sticky lg:top-8 space-y-6">

                    {{-- Share card --}}
                    <div x-data="{ copied: false }" class="bg-gray-50 rounded-2xl p-6">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Partager</h3>
                        <div class="flex flex-col gap-2">
                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->fullUrl()) }}"
                                target="_blank" rel="noopener"
                                class="flex items-center gap-3 bg-[#1877F2] text-white text-sm font-medium px-4 py-2.5 rounded-xl hover:brightness-110 transition">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                Partager sur Facebook
                            </a>
                            <a href="https://wa.me/?text={{ urlencode(($article->title ?? '') . ' — ' . request()->fullUrl()) }}"
                                target="_blank" rel="noopener"
                                class="flex items-center gap-3 bg-[#25D366] text-white text-sm font-medium px-4 py-2.5 rounded-xl hover:brightness-110 transition">
                                <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                Partager sur WhatsApp
                            </a>
                            <button @click="navigator.clipboard.writeText(window.location.href); copied = true; setTimeout(() => copied = false, 2000)"
                                class="flex items-center gap-3 bg-white border border-gray-200 text-gray-700 text-sm font-medium px-4 py-2.5 rounded-xl hover:bg-gray-100 transition">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                <span x-text="copied ? 'Lien copié !' : 'Copier le lien'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Join Facebook group --}}
                    <a href="https://www.facebook.com/groups/23609449662" target="_blank" rel="noopener"
                        class="flex items-center gap-3 border border-[#1877F2] text-[#1877F2] text-sm font-medium px-4 py-3 rounded-2xl hover:bg-blue-50 transition">
                        <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        <div>
                            <div class="font-semibold">Rejoindre notre groupe</div>
                            <div class="text-xs text-gray-400">{{ config('club.name') }} sur Facebook</div>
                        </div>
                    </a>

                    {{-- Back to news --}}
                    <a href="{{ route('public.clubPosts.index') }}"
                        class="flex items-center gap-2 text-sm text-gray-500 hover:text-club-blue transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Retour aux articles
                    </a>
                </div>
            </div>

        </div>
    </div>

    {{-- Related Articles --}}
    @if(isset($relatedArticles) && count($relatedArticles) > 0)
        <div class="bg-gray-50 py-16 mt-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">Articles Similaires</h2>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($relatedArticles as $relatedArticle)
                        <x-public.news-card :article="$relatedArticle" />
                    @endforeach
                </div>
            </div>
        </div>
    @endif

</x-guest-layout>
