<div>
    <x-guest-layout title="Actualités - CTT Ottignies-Blocry">
        <x-navigation :fixed="false" />
        
        <!-- Header -->
<div class="relative h-auto pt-16 text-white flex items-center overflow-hidden">
    <!-- Image de fond -->
    <div class="absolute inset-0">
        <img src="{{ asset('images/background_news.webp') }}" alt="Tennis table background" class="w-full h-full object-cover">
        <!-- Overlay avec votre dégradé + opacité -->
        <div class="absolute inset-0 bg-gradient-to-br from-club-blue/85 via-club-blue/80 to-club-blue-light/85"></div>
    </div>
    
    <!-- Contenu -->
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <h1 class="text-4xl md:text-5xl font-bold mb-4 drop-shadow-lg">Actualités du Club</h1>
        <p class="text-xl opacity-90 drop-shadow-md">Toutes les dernières nouvelles et infos</p>
    </div>
</div>

        <livewire:public.articles.article-list />

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
    </x-guest-layout>
</div>