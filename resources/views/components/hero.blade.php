<section id="home" class="h-auto pt-16 bg-linear-to-br from-club-blue to-club-blue-light text-white flex items-center">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <div class="text-center">
            <h1 class="text-5xl md:text-7xl font-bold mb-6 fade-in-up">
                {{ $title ?? 'CTT Ottignies-Blocry' }}
            </h1>
            <p class="text-xl md:text-2xl mb-8 max-w-3xl mx-auto opacity-90 fade-in-up" style="animation-delay: 0.2s;">
                {{ $subtitle ?? 'Rejoignez notre communauté passionnée de joueurs de tennis de table. Des débutants aux champions, tout le monde est le bienvenu au sein de notre club.' }}
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center fade-in-up" style="animation-delay: 0.4s;">
                <a href="#join" class="bg-club-yellow text-club-blue px-8 py-4 rounded-lg text-lg font-semibold hover:bg-club-yellow-light transition-colors transform hover:scale-105">
                    Rejoindre le Club
                </a>
                <a href="#about" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-club-blue transition-colors">
                    En Savoir Plus
                </a>
            </div>
        </div>
    </div>
    <div class="text-center pb-8">
        <div class="animate-bounce">
            <svg class="w-6 h-6 mx-auto text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
            </svg>
        </div>
    </div>
</section>
