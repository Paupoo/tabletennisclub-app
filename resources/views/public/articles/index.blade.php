<div>
    <x-guest-layout title="Actualités - Ace Table Tennis Club">
        <x-navigation :fixed="false" />
        
        <!-- Header -->
        <div class="bg-linear-to-r from-club-blue to-club-blue-light text-white py-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h1 class="text-4xl md:text-5xl font-bold mb-4">Actualités du Club</h1>
                <p class="text-xl opacity-90">Toutes les dernières nouvelles et événements d'Ace TTC</p>
            </div>
        </div>

        @livewire('public.articles.article-list')

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