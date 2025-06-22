<section id="news" class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 animate-on-scroll">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">Actualités du Club</h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                Restez informé des dernières nouvelles, événements et réussites de notre communauté
            </p>
        </div>
        
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            @forelse($articles ?? [] as $article)
                <x-news-card :article="$article" />
            @empty
                <!-- Articles par défaut si aucune donnée fournie -->
                <x-news-card :article="[
                    'title' => 'Victoire Éclatante en Championnat Régional',
                    'excerpt' => 'Notre équipe A remporte le championnat régional avec un score impressionnant de 8-2 contre les favoris de Thunder TTC.',
                    'date' => '15 Décembre 2024',
                    'category' => 'Compétition',
                    'image' => '/placeholder.svg?height=200&width=400',
                    'slug' => 'victoire-championnat-regional'
                ]" />
                
                <x-news-card :article="[
                    'title' => 'Nouveau Partenariat avec SportTech',
                    'excerpt' => 'Nous sommes fiers d\'annoncer notre nouveau partenariat avec SportTech pour l\'équipement de nos joueurs.',
                    'date' => '10 Décembre 2024',
                    'category' => 'Partenariat',
                    'image' => '/placeholder.svg?height=200&width=400',
                    'slug' => 'partenariat-sporttech'
                ]" />
                
                <x-news-card :article="[
                    'title' => 'Stage d\'Été pour les Jeunes',
                    'excerpt' => 'Inscriptions ouvertes pour notre stage d\'été destiné aux jeunes de 8 à 16 ans. Une semaine intensive de formation.',
                    'date' => '5 Décembre 2024',
                    'category' => 'Formation',
                    'image' => '/placeholder.svg?height=200&width=400',
                    'slug' => 'stage-ete-jeunes'
                ]" />
                
                <x-news-card :article="[
                    'title' => 'Rénovation des Installations',
                    'excerpt' => 'Les travaux de rénovation de notre salle principale sont terminés. Découvrez nos nouvelles tables professionnelles.',
                    'date' => '1 Décembre 2024',
                    'category' => 'Infrastructure',
                    'image' => '/placeholder.svg?height=200&width=400',
                    'slug' => 'renovation-installations'
                ]" />
                
                <x-news-card :article="[
                    'title' => 'Portrait : Marie Dubois, Nouvelle Championne',
                    'excerpt' => 'Rencontre avec Marie Dubois, 16 ans, qui vient de remporter le tournoi junior départemental.',
                    'date' => '28 Novembre 2024',
                    'category' => 'Portrait',
                    'image' => '/placeholder.svg?height=200&width=400',
                    'slug' => 'portrait-marie-dubois'
                ]" />
                
                <x-news-card :article="[
                    'title' => 'Assemblée Générale 2025',
                    'excerpt' => 'L\'assemblée générale annuelle aura lieu le 20 janvier 2025. Tous les membres sont invités à participer.',
                    'date' => '25 Novembre 2024',
                    'category' => 'Vie du Club',
                    'image' => '/placeholder.svg?height=200&width=400',
                    'slug' => 'assemblee-generale-2025'
                ]" />
            @endforelse
        </div>
        
        <div class="text-center mt-12 animate-on-scroll">
            <a href="{{ route('articles.index') ?? '#' }}" class="bg-club-blue text-white px-8 py-3 rounded-lg font-semibold hover:bg-club-blue-light transition-colors inline-flex items-center">
                Voir Toutes les Actualités
                <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>
    </div>
</section>
