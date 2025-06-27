<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicArticlesController extends Controller
{
        public function index(Request $request)
    {
        // // Récupérer les paramètres de filtrage
        // $year = $request->get('year');
        // $month = $request->get('month');
        // $category = $request->get('category');
        // $sort = $request->get('sort', 'desc');
        // $page = $request->get('page', 1);
        // $perPage = 9;

        // // Données d'exemple - à remplacer par une vraie requête de base de données
        // $allArticles = [
        //     [
        //         'title' => 'Victoire Éclatante en Championnat Régional',
        //         'excerpt' => 'Notre équipe A remporte le championnat régional avec un score impressionnant de 8-2 contre les favoris de Thunder TTC.',
        //         'content' => $this->getFullArticleContent('victoire-championnat-regional'),
        //         'date' => '15 Décembre 2024',
        //         'category' => 'Compétition',
        //         'image' => '/placeholder.svg?height=400&width=800',
        //         'slug' => 'victoire-championnat-regional',
        //         'author' => 'Pierre Martin',
        //         'reading_time' => 3,
        //         'tags' => ['championnat', 'victoire', 'équipe-a'],
        //         'year' => '2024',
        //         'month' => '12'
        //     ],
        //     [
        //         'title' => 'Nouveau Partenariat avec SportTech',
        //         'excerpt' => 'Nous sommes fiers d\'annoncer notre nouveau partenariat avec SportTech pour l\'équipement de nos joueurs.',
        //         'content' => $this->getFullArticleContent('partenariat-sporttech'),
        //         'date' => '10 Décembre 2024',
        //         'category' => 'Partenariat',
        //         'image' => '/placeholder.svg?height=400&width=800',
        //         'slug' => 'partenariat-sporttech',
        //         'author' => 'Sophie Dubois',
        //         'reading_time' => 2,
        //         'tags' => ['partenariat', 'équipement', 'sporttech'],
        //         'year' => '2024',
        //         'month' => '12'
        //     ],
        //     [
        //         'title' => 'Stage d\'Été pour les Jeunes',
        //         'excerpt' => 'Inscriptions ouvertes pour notre stage d\'été destiné aux jeunes de 8 à 16 ans. Une semaine intensive de formation.',
        //         'content' => $this->getFullArticleContent('stage-ete-jeunes'),
        //         'date' => '5 Décembre 2024',
        //         'category' => 'Formation',
        //         'image' => '/placeholder.svg?height=400&width=800',
        //         'slug' => 'stage-ete-jeunes',
        //         'author' => 'Marc Leroy',
        //         'reading_time' => 4,
        //         'tags' => ['stage', 'jeunes', 'formation'],
        //         'year' => '2024',
        //         'month' => '12'
        //     ],
        //     [
        //         'title' => 'Rénovation des Installations',
        //         'excerpt' => 'Les travaux de rénovation de notre salle principale sont terminés. Découvrez nos nouvelles tables professionnelles.',
        //         'content' => $this->getFullArticleContent('renovation-installations'),
        //         'date' => '1 Décembre 2024',
        //         'category' => 'Infrastructure',
        //         'image' => '/placeholder.svg?height=400&width=800',
        //         'slug' => 'renovation-installations',
        //         'author' => 'Jean Dupont',
        //         'reading_time' => 3,
        //         'tags' => ['rénovation', 'installations', 'tables'],
        //         'year' => '2024',
        //         'month' => '12'
        //     ],
        //     [
        //         'title' => 'Portrait : Marie Dubois, Nouvelle Championne',
        //         'excerpt' => 'Rencontre avec Marie Dubois, 16 ans, qui vient de remporter le tournoi junior départemental.',
        //         'content' => $this->getFullArticleContent('portrait-marie-dubois'),
        //         'date' => '28 Novembre 2024',
        //         'category' => 'Portrait',
        //         'image' => '/placeholder.svg?height=400&width=800',
        //         'slug' => 'portrait-marie-dubois',
        //         'author' => 'Claire Martin',
        //         'reading_time' => 5,
        //         'tags' => ['portrait', 'championne', 'junior'],
        //         'year' => '2024',
        //         'month' => '11'
        //     ],
        //     [
        //         'title' => 'Assemblée Générale 2025',
        //         'excerpt' => 'L\'assemblée générale annuelle aura lieu le 20 janvier 2025. Tous les membres sont invités à participer.',
        //         'content' => $this->getFullArticleContent('assemblee-generale-2025'),
        //         'date' => '25 Novembre 2024',
        //         'category' => 'Vie du Club',
        //         'image' => '/placeholder.svg?height=400&width=800',
        //         'slug' => 'assemblee-generale-2025',
        //         'author' => 'Président du Club',
        //         'reading_time' => 2,
        //         'tags' => ['assemblée', 'générale', 'membres'],
        //         'year' => '2024',
        //         'month' => '11'
        //     ],
        //     // Articles de 2023
        //     [
        //         'title' => 'Bilan de la Saison 2023',
        //         'excerpt' => 'Retour sur une saison exceptionnelle avec de nombreuses victoires et nouveaux membres.',
        //         'content' => $this->getFullArticleContent('bilan-saison-2023'),
        //         'date' => '15 Décembre 2023',
        //         'category' => 'Vie du Club',
        //         'image' => '/placeholder.svg?height=400&width=800',
        //         'slug' => 'bilan-saison-2023',
        //         'author' => 'Bureau du Club',
        //         'reading_time' => 6,
        //         'tags' => ['bilan', 'saison', '2023'],
        //         'year' => '2023',
        //         'month' => '12'
        //     ],
        //     [
        //         'title' => 'Tournoi de Noël 2023',
        //         'excerpt' => 'Le traditionnel tournoi de Noël a rassemblé plus de 50 participants dans une ambiance festive.',
        //         'content' => $this->getFullArticleContent('tournoi-noel-2023'),
        //         'date' => '20 Décembre 2023',
        //         'category' => 'Compétition',
        //         'image' => '/placeholder.svg?height=400&width=800',
        //         'slug' => 'tournoi-noel-2023',
        //         'author' => 'Organisateurs',
        //         'reading_time' => 3,
        //         'tags' => ['tournoi', 'noël', 'festif'],
        //         'year' => '2023',
        //         'month' => '12'
        //     ]
        // ];

        // // Filtrer les articles
        // $filteredArticles = collect($allArticles);

        // if ($year) {
        //     $filteredArticles = $filteredArticles->where('year', $year);
        // }

        // if ($month) {
        //     $filteredArticles = $filteredArticles->where('month', $month);
        // }

        // if ($category) {
        //     $filteredArticles = $filteredArticles->where('category', $category);
        // }

        // // Trier les articles
        // $filteredArticles = $sort === 'asc' 
        //     ? $filteredArticles->sortBy('date') 
        //     : $filteredArticles->sortByDesc('date');

        // // Pagination
        // $total = $filteredArticles->count();
        // $articles = $filteredArticles->forPage($page, $perPage)->values();

        // // Générer les années disponibles
        // $years = collect($allArticles)->pluck('year')->unique()->sort()->values();

        // // Pagination info
        // $pagination = [
        //     'current_page' => $page,
        //     'total_pages' => ceil($total / $perPage),
        //     'total' => $total,
        //     'from' => ($page - 1) * $perPage + 1,
        //     'to' => min($page * $perPage, $total),
        //     'prev_url' => $page > 1 ? request()->fullUrlWithQuery(['page' => $page - 1]) : null,
        //     'next_url' => $page < ceil($total / $perPage) ? request()->fullUrlWithQuery(['page' => $page + 1]) : null,
        //     'pages' => range(max(1, $page - 2), min(ceil($total / $perPage), $page + 2)),
        //     'page_urls' => []
        // ];

        // // Générer les URLs pour chaque page
        // foreach ($pagination['pages'] as $pageNum) {
        //     $pagination['page_urls'][$pageNum] = request()->fullUrlWithQuery(['page' => $pageNum]);
        // }
        $articles = Article::where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->paginate(9);

        return view('articles.index', compact('articles'));
    }

    public function show($slug)
    {
        // Données d'exemple - à remplacer par une vraie requête de base de données
        // $articles = [
        //     'victoire-championnat-regional' => [
        //         'title' => 'Victoire Éclatante en Championnat Régional',
        //         'excerpt' => 'Notre équipe A remporte le championnat régional avec un score impressionnant de 8-2 contre les favoris de Thunder TTC.',
        //         'content' => $this->getFullArticleContent('victoire-championnat-regional'),
        //         'date' => '15 Décembre 2024',
        //         'category' => 'Compétition',
        //         'image' => '/placeholder.svg?height=600&width=1200',
        //         'image_caption' => 'L\'équipe A célèbre sa victoire au championnat régional',
        //         'slug' => 'victoire-championnat-regional',
        //         'author' => 'Pierre Martin',
        //         'reading_time' => 3,
        //         'tags' => ['championnat', 'victoire', 'équipe-a', 'régional', 'thunder-ttc']
        //     ],
        //     'partenariat-sporttech' => [
        //         'title' => 'Nouveau Partenariat avec SportTech',
        //         'excerpt' => 'Nous sommes fiers d\'annoncer notre nouveau partenariat avec SportTech pour l\'équipement de nos joueurs.',
        //         'content' => $this->getFullArticleContent('partenariat-sporttech'),
        //         'date' => '10 Décembre 2024',
        //         'category' => 'Partenariat',
        //         'image' => '/placeholder.svg?height=600&width=1200',
        //         'slug' => 'partenariat-sporttech',
        //         'author' => 'Sophie Dubois',
        //         'reading_time' => 2,
        //         'tags' => ['partenariat', 'équipement', 'sporttech', 'matériel']
        //     ],
        //     // Ajouter d'autres articles...
        // ];

        // $article = $articles[$slug] ?? null;

        $article = Article::whereSlug($slug)->firstOrFail();

        if (!$article) {
            abort(404);
        }

        // Articles similaires (même catégorie)
        $relatedArticles = Article::where('category', $article->category)
            ->where('slug', '!=', $slug)
            ->take(3)
            ->get();

            return view('articles.show', compact('article', 'relatedArticles'));
    }

    private function getFullArticleContent($slug)
    {
        // Contenu d'exemple - à remplacer par le vrai contenu depuis la base de données
        $contents = [
            'victoire-championnat-regional' => '
                <p>C\'est avec une immense fierté que nous annonçons la victoire éclatante de notre équipe A au championnat régional de tennis de table. Après des mois de préparation intensive, nos joueurs ont su démontrer leur excellence technique et leur mental d\'acier.</p>
                
                <h2>Un match mémorable</h2>
                <p>Face aux favoris de Thunder TTC, nos joueurs ont livré une performance exceptionnelle. Dès les premiers échanges, l\'équipe a montré sa détermination et sa cohésion. Le score final de 8-2 reflète parfaitement la domination exercée tout au long de la rencontre.</p>
                
                <h2>Les héros du jour</h2>
                <p>Chaque membre de l\'équipe a contribué à cette victoire historique :</p>
                <ul>
                    <li><strong>Jean Dupont</strong> - 3 victoires en simple, performance remarquable</li>
                    <li><strong>Marie Leroy</strong> - 2 victoires décisives en moments cruciaux</li>
                    <li><strong>Paul Martin</strong> - Victoire en double avec Jean, excellent jeu d\'équipe</li>
                    <li><strong>Sophie Bernard</strong> - Victoire importante qui a scellé le sort du match</li>
                </ul>
                
                <h2>Préparation et entraînement</h2>
                <p>Cette victoire n\'est pas le fruit du hasard. Depuis le début de la saison, l\'équipe s\'entraîne avec assiduité sous la direction de notre entraîneur Pierre Martin. Les séances techniques intensives et le travail mental ont porté leurs fruits.</p>
                
                <blockquote>
                    <p>"Je suis extrêmement fier de mes joueurs. Ils ont montré un niveau de jeu exceptionnel et un esprit d\'équipe remarquable. Cette victoire récompense des mois de travail acharné."</p>
                    <cite>- Pierre Martin, Entraîneur</cite>
                </blockquote>
                
                <h2>Prochains objectifs</h2>
                <p>Fort de cette victoire, l\'équipe A se qualifie automatiquement pour le championnat national qui aura lieu en mars prochain. L\'objectif est désormais de confirmer ce niveau d\'excellence sur la scène nationale.</p>
                
                <p>Félicitations à toute l\'équipe pour cette performance exceptionnelle qui fait honneur aux couleurs d\'Ace TTC !</p>
            ',
            'partenariat-sporttech' => '
                <p>Ace Table Tennis Club franchit une nouvelle étape dans son développement avec l\'annonce d\'un partenariat stratégique avec SportTech, leader européen dans l\'équipement de tennis de table professionnel.</p>
                
                <h2>Un partenariat d\'excellence</h2>
                <p>Ce partenariat permettra à nos joueurs de bénéficier des dernières innovations en matière d\'équipement sportif. SportTech mettra à disposition du club ses raquettes haut de gamme, balles de compétition et accessoires techniques.</p>
                
                <h2>Avantages pour nos membres</h2>
                <p>Grâce à ce partenariat, nos membres pourront :</p>
                <ul>
                    <li>Tester les derniers modèles de raquettes SportTech</li>
                    <li>Bénéficier de tarifs préférentiels sur tout l\'équipement</li>
                    <li>Accéder à des formations techniques avec les experts SportTech</li>
                    <li>Participer à des événements exclusifs</li>
                </ul>
                
                <p>Ce partenariat s\'inscrit dans notre volonté constante d\'offrir à nos membres les meilleures conditions de pratique et de progression.</p>
            '
        ];

        return $contents[$slug] ?? '<p>Contenu de l\'article en cours de rédaction...</p>';
    }
}
