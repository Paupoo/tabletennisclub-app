<section id="home" class="relative h-auto pt-16 text-white flex items-center overflow-hidden">
    <!-- Image de fond -->
    <div class="absolute inset-0">
        <img src="{{ asset('images/background_home.jpg') }}" alt="Tennis table background" class="w-full h-full object-cover">
        <!-- Overlay avec votre dégradé + opacité -->
        <div class="absolute inset-0 bg-gradient-to-br from-club-blue/85 via-club-blue/80 to-club-blue-light/85"></div>
    </div>
    
    <!-- Contenu -->
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <div class="text-center">
            <h1 class="text-5xl md:text-7xl font-bold mb-6 fade-in-up drop-shadow-lg">
                {{ $title ?? 'CTT Ottignies-Blocry' }}
            </h1>
            <p class="text-xl md:text-2xl mb-8 max-w-3xl mx-auto opacity-90 fade-in-up drop-shadow-md" style="animation-delay: 0.2s;">
                {{ $subtitle ?? 'Rejoignez notre communauté passionnée de joueurs de tennis de table. Des débutants aux champions, tout le monde est le bienvenu au sein de notre club.' }}
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center fade-in-up" style="animation-delay: 0.4s;">
                <a href="#join" class="bg-club-yellow text-club-blue px-8 py-4 rounded-lg text-lg font-semibold hover:bg-club-yellow-light transition-colors transform hover:scale-105 shadow-lg">
                    Rejoindre le Club
                </a>
                <a href="#about" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-club-blue transition-colors backdrop-blur-sm">
                    En Savoir Plus
                </a>
            </div>
        </div>
    </div>
</section>