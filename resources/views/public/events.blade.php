<x-guest-layout title="Événements - Ace Table Tennis Club">

    <!-- Header -->
    <div class="relative h-auto pt-16 text-white flex items-center overflow-hidden">
        <!-- Image de fond -->
        <div class="absolute inset-0">
            <img src="{{ asset('images/background_events.webp') }}" alt="Tennis table background" class="w-full h-full object-cover">
            <!-- Overlay avec votre dégradé + opacité -->
            <div class="absolute inset-0 bg-gradient-to-br from-club-blue/85 via-club-blue/80 to-club-blue-light/85"></div>
        </div>

        <!-- Contenu -->
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <h1 class="text-4xl md:text-5xl font-bold mb-4 drop-shadow-lg">Événements à venir</h1>
            <p class="text-xl opacity-90 drop-shadow-md">Rejoignez-nous pour des tournois, des séances d'entraînement et des événements communautaires</p>
        </div>
    </div>

    <!-- EventPost Filters -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="{ selectedCategory: 'all' }">
        <div class="flex flex-wrap gap-2 mb-8">
            <button @click="selectedCategory = 'all'"
                    :class="selectedCategory === 'all' ? 'bg-club-blue text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                    class="px-4 py-2 rounded-lg border transition-colors">
                Tous les Événements
            </button>
            <button @click="selectedCategory = 'tournament'"
                    :class="selectedCategory === 'tournament' ? 'bg-club-blue text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                    class="px-4 py-2 rounded-lg border transition-colors">
                Tournois
            </button>
            <button @click="selectedCategory = 'training'"
                    :class="selectedCategory === 'training' ? 'bg-club-blue text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                    class="px-4 py-2 rounded-lg border transition-colors">
                Entraînement
            </button>
            <button @click="selectedCategory = 'club-life'"
                    :class="selectedCategory === 'club-life' ? 'bg-club-blue text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                    class="px-4 py-2 rounded-lg border transition-colors">
                Vie du club
            </button>
        </div>

        <!-- Events Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-16">
            @forelse($events ?? [] as $event)
                <x-public.event-card :event="$event" />
            @empty
                <!-- Default events if no data provided -->
                <x-public.event-card :event="[
                    'category' => 'tournament',
                    'title' => 'Championnat du Nouvel An',
                    'description' => 'Championnat annuel du club ouvert à tous les membres. Catégories simple et double disponibles.',
                    'date' => '15 Janvier 2025',
                    'time' => '9h00 - 18h00',
                    'location' => 'Salle Principale',
                    'price' => '25€ d\'inscription',
                    'icon' => '🏆'
                ]" />

                <x-public.event-card :event="[
                    'category' => 'training',
                    'title' => 'Atelier Techniques Avancées',
                    'description' => 'Maîtrisez les services avancés, les effets et le jeu tactique avec notre entraîneur professionnel.',
                    'date' => 'Tous les samedis',
                    'time' => '14h00 - 16h00',
                    'location' => 'Salle d\'Entraînement A',
                    'price' => 'Max 8 participants',
                    'icon' => '🎯'
                ]" />

                <x-public.event-card :event="[
                    'category' => 'club-life',
                    'title' => 'Soirée Sociale Mensuelle',
                    'description' => 'Jeux décontractés, pizza et amusement ! Parfait pour rencontrer d\'autres membres et se détendre.',
                    'date' => 'Premier vendredi de chaque mois',
                    'time' => '19h00 - 22h00',
                    'location' => 'Salon du Club',
                    'price' => 'Nourriture et boissons incluses',
                    'icon' => '🎉'
                ]" />
            @endforelse
        </div>

        <!-- Call to Action -->
        <div class="bg-club-blue rounded-lg p-8 text-white text-center">
            <h2 class="text-3xl font-bold mb-4">Ne Ratez Rien !</h2>
            <p class="text-xl mb-6 opacity-90">
                Rejoignez nos événements et devenez membre de la communauté Ace TTC. Tous les niveaux sont les bienvenus !
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('home') }}#join" class="bg-club-yellow text-club-blue px-8 py-3 rounded-lg font-semibold hover:bg-club-yellow-light transition-colors">
                    Devenir Membre
                </a>
                <a href="{{ route('home') }}#contact" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-club-blue transition-colors">
                    Nous Contacter
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>
