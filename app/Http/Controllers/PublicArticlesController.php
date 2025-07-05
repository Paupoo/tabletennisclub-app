<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use League\CommonMark\MarkdownConverter;

class PublicArticlesController extends Controller
{
        public function index(Request $request)
    {
        return view('public.articles.index');
    }

    public function show($slug)
    {
       
        $article = Article::whereSlug($slug)
            ->with('user')
            ->firstOrFail();

        // Articles similaires (même catégorie)
        $relatedArticles = Article::where('category', $article->category)
            ->where('slug', '!=', $slug)
            ->take(3)
            ->get();

            return view('livewire.public.articles.show', compact('article', 'relatedArticles'));
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
