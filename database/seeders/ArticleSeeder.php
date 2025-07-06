<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\User;
use App\Enums\ArticlesCategoryEnum;
use App\Enums\ArticlesStatusEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupère le premier utilisateur ou crée un utilisateur par défaut
        $user = User::first() ?? User::factory()->create([
            'name' => 'Admin CTT',
            'email' => 'admin@ctt-ottignies.be',
        ]);

        $articles = [
            [
                'title' => 'Victoire éclatante en championnat régional',
                'content' => 'Le CTT Ottignies-Blocry a remporté une victoire décisive face au TC Wavre avec un score de 16-2. Nos joueurs ont fait preuve d\'une excellente coordination et d\'une technique irréprochable. Marc Delcroix s\'est particulièrement distingué en remportant ses trois simples sans concéder un seul set. Cette victoire nous place en tête du classement de la division 3 avec trois points d\'avance sur nos poursuivants.',
                'category' => ArticlesCategoryEnum::COMPETITION,
            ],
            [
                'title' => 'Nouveau partenariat avec Decathlon Wavre',
                'content' => 'Le club est fier d\'annoncer son nouveau partenariat avec Decathlon Wavre. Cette collaboration permettra à nos membres de bénéficier de 15% de réduction sur tout l\'équipement de tennis de table, ainsi que d\'un accès privilégié aux dernières nouveautés. En échange, notre club participera aux journées portes ouvertes du magasin avec des démonstrations et des initiations gratuites.',
                'category' => ArticlesCategoryEnum::PARTNERSHIP,
            ],
            [
                'title' => 'Portrait : Sarah Lemoine, la révélation de la saison',
                'content' => 'À seulement 16 ans, Sarah Lemoine collectionne déjà les victoires en championnat jeunes. Arrivée au club il y a deux ans, elle a rapidement gravi les échelons grâce à son style de jeu offensif et sa détermination. "Mon objectif est de rejoindre l\'équipe première l\'année prochaine", confie-t-elle. Son entraîneur, Philippe Durand, la voit déjà représenter la Belgique dans les compétitions internationales.',
                'category' => ArticlesCategoryEnum::PORTRAIT,
            ],
            [
                'title' => 'Tournoi de Noël : une belle réussite',
                'content' => 'Le traditionnel tournoi de Noël du CTT s\'est déroulé dans une ambiance chaleureuse le 15 décembre. Plus de 50 participants se sont affrontés dans différentes catégories. Le buffet préparé par les bénévoles a été très apprécié, ainsi que la tombola qui a permis de récolter 800 euros pour l\'achat de nouveau matériel. Rendez-vous l\'année prochaine pour une nouvelle édition !',
                'category' => ArticlesCategoryEnum::EVENT,
            ],
            [
                'title' => 'Stage d\'été : perfectionnement technique au programme',
                'content' => 'Le stage d\'été organisé du 15 au 19 juillet a accueilli 25 jeunes joueurs. Encadrés par trois entraîneurs diplômés, ils ont travaillé les fondamentaux : service, coup droit, revers et déplacements. "J\'ai appris à varier mes services et à mieux attaquer", témoigne Lucas, 12 ans. Le stage s\'est terminé par un mini-tournoi où chaque participant a reçu une médaille.',
                'category' => ArticlesCategoryEnum::TRAINING,
            ],
            [
                'title' => 'Assemblée générale : nouveaux projets à l\'horizon',
                'content' => 'L\'assemblée générale du 20 janvier a réuni une cinquantaine de membres. Le président Jean-Luc Bertrand a présenté les comptes 2024 et les projets pour 2025, notamment la rénovation du sol de la salle principale et l\'organisation d\'un tournoi inter-clubs. Le budget d\'investissement de 15 000 euros a été voté à l\'unanimité.',
                'category' => ArticlesCategoryEnum::NEWS,
            ],
            [
                'title' => 'Défaite honorable face au leader',
                'content' => 'Malgré une belle résistance, notre équipe première s\'est inclinée 16-8 face au TC Louvain-la-Neuve, leader du championnat. Les doubles ont été particulièrement disputés, avec deux victoires à l\'arraché. Antoine Moreau et Cédric Vanheule ont montré un excellent niveau, ne s\'inclinant qu\'au 5e set de leurs matchs respectifs.',
                'category' => ArticlesCategoryEnum::COMPETITION,
            ],
            [
                'title' => 'Collaboration avec l\'école Saint-Joseph',
                'content' => 'Le CTT étend son action vers les jeunes en proposant des cours d\'initiation à l\'école Saint-Joseph d\'Ottignies. Chaque mardi, une quinzaine d\'élèves de 5e et 6e primaire découvrent notre sport sous la houlette de moniteurs qualifiés. Plusieurs enfants ont déjà manifesté leur intérêt pour rejoindre le club.',
                'category' => ArticlesCategoryEnum::PARTNERSHIP,
            ],
            [
                'title' => 'Pierre Vandenberghe, 40 ans de passion',
                'content' => 'Membre fondateur du club, Pierre Vandenberghe fête ses 40 ans d\'engagement. Joueur, entraîneur, puis président durant 10 ans, il a marqué l\'histoire du CTT. "J\'ai vu le club grandir de 15 à 120 membres", se souvient-il. Aujourd\'hui encore, à 68 ans, il continue de jouer en vétérans et de transmettre sa passion aux jeunes générations.',
                'category' => ArticlesCategoryEnum::PORTRAIT,
            ],
            [
                'title' => 'Soirée karaoké : ambiance garantie !',
                'content' => 'La soirée karaoké du 8 février a fait salle comble. Entre deux chansons, les membres ont pu participer à des défis ping-pong amusants. Le duo surprise formé par le président et le trésorier sur "Les Lacs du Connemara" restera dans les mémoires ! Cette soirée conviviale a permis de renforcer les liens entre les générations du club.',
                'category' => ArticlesCategoryEnum::EVENT,
            ],
            [
                'title' => 'Nouveau cours débutants le mercredi',
                'content' => 'Face à la demande croissante, le club ouvre un nouveau cours débutants le mercredi de 19h30 à 21h. Encadré par Julie Delcroix, ce cours s\'adresse aux adultes souhaitant découvrir le tennis de table dans une ambiance détendue. Les inscriptions sont ouvertes, matériel fourni pour les premiers cours.',
                'category' => ArticlesCategoryEnum::TRAINING,
            ],
            [
                'title' => 'Don de matériel à l\'ASBL Télé-Accueil',
                'content' => 'Dans le cadre de son action sociale, le CTT a fait don d\'anciennes tables et de matériel d\'entraînement à l\'ASBL Télé-Accueil de Wavre. Cette association utilise le sport comme outil d\'insertion sociale. "C\'est important pour notre club de s\'engager dans la communauté", souligne la responsable communication, Marie Dupuis.',
                'category' => ArticlesCategoryEnum::NEWS,
            ],
            [
                'title' => 'Qualification pour les interclubs provinciaux',
                'content' => 'Grâce à leur excellente saison, nos jeunes se sont qualifiés pour les interclubs provinciaux. Emma Delforge, Thomas Willems et Maxime Boulanger représenteront fièrement les couleurs du CTT les 15 et 16 mars à Charleroi. Cette participation récompense le travail de formation mené par le club ces dernières années.',
                'category' => ArticlesCategoryEnum::COMPETITION,
            ],
            [
                'title' => 'La brasserie du Château, nouveau sponsor',
                'content' => 'La brasserie du Château de Blocry devient partenaire officiel du club. En plus du soutien financier, elle fournira les boissons pour nos événements. "Nous partageons les mêmes valeurs de convivialité et de tradition", explique le gérant, Michel Lejeune. Le logo de la brasserie ornera désormais nos maillots d\'équipe.',
                'category' => ArticlesCategoryEnum::PARTNERSHIP,
            ],
            [
                'title' => 'Michel Dumont, l\'entraîneur qui fait la différence',
                'content' => 'Arrivé il y a trois ans, Michel Dumont a révolutionné la formation au CTT. Ancien joueur de nationale, il a apporté ses méthodes modernes et sa rigueur. Sous sa direction, le niveau général du club a nettement progressé. "Michel sait motiver chaque joueur selon son niveau", témoigne Sylvie Mortier, responsable des jeunes.',
                'category' => ArticlesCategoryEnum::PORTRAIT,
            ],
            [
                'title' => 'Portes ouvertes : le club se dévoile',
                'content' => 'Les portes ouvertes du 12 mars ont attiré plus de 80 visiteurs. Démonstrations, initiations gratuites et présentation des équipes ont ponctué cette journée. Quinze nouvelles inscriptions ont été enregistrées, preuve de l\'attractivité de notre club. Les parents ont particulièrement apprécié l\'accueil chaleureux et les explications détaillées sur l\'organisation.',
                'category' => ArticlesCategoryEnum::EVENT,
            ],
            [
                'title' => 'Stage de perfectionnement avec un champion',
                'content' => 'Le club organise un stage exceptionnel avec Jean-Michel Saive, légende belge du tennis de table. Les 15 et 16 avril, 30 joueurs confirmés pourront bénéficier de ses conseils. "C\'est une opportunité unique de progresser avec un joueur de ce niveau", se réjouit l\'entraîneur principal. Les inscriptions sont limitées et se font par ordre d\'arrivée.',
                'category' => ArticlesCategoryEnum::TRAINING,
            ],
            [
                'title' => 'Nouveau site internet en ligne',
                'content' => 'Le CTT dévoile son nouveau site internet, plus moderne et interactif. Résultats en temps réel, galerie photos, inscription en ligne et boutique du club sont désormais accessibles. "Nous voulions moderniser notre communication", explique Kevin Martens, responsable informatique. Une application mobile est également en développement.',
                'category' => ArticlesCategoryEnum::NEWS,
            ],
            [
                'title' => 'Remontée spectaculaire en play-offs',
                'content' => 'Menés 8-4 à la mi-temps, nos joueurs ont réalisé une remontée extraordinaire pour s\'imposer 16-12 face au TC Jodoigne. Cette victoire nous qualifie pour les play-offs d\'accession en division supérieure. L\'ambiance était électrique dans la salle, avec un public venu nombreux encourager l\'équipe lors de ce match décisif.',
                'category' => ArticlesCategoryEnum::COMPETITION,
            ],
            [
                'title' => 'Hommage à André Libert, figure emblématique',
                'content' => 'Le club rend hommage à André Libert, décédé récemment à l\'âge de 75 ans. Membre durant 35 ans, il avait marqué le CTT par son dévouement et sa bonne humeur. Responsable du matériel pendant 20 ans, il était présent à chaque entraînement et chaque compétition. Une minute de silence a été observée avant le dernier match à domicile en son honneur.',
                'category' => ArticlesCategoryEnum::PORTRAIT,
            ],
        ];

        $images = [
            '01JYSRDBJEQY3RCNC7QBMXFRK9.png',
            '01JYSTDP26TJW0JBD46FDR6JY1.png',
            '01JYSTG4288SMPV4F2S7FQZQ78.png',
            '01JYSTK3Q30QKFC6ZS8PDMPSAV.png',
            '01JYSTPA7QMG4MS2FJX1WVMHFA.png',
            '01JYSTT5A2AXH3CN2PFVYF7SNR.png',
            '01JYZQJGE27KQ2CH4CA3ZCPFFW.jpg',
            '01JZ6G5NYEHDFQW20XW282Y68Y.png',
            '01JZ6HWJA1T5P01JZTHN9T5ZM0.png',
            '01JZE7JSAE4NTJX2RCDMDPFK53.png',
        ];

        // Génération de dates variées sur 2 années (2023-2024)
        $createdDates = $this->generateVariedDates(count($articles));

        foreach ($articles as $index => $articleData) {
            $createdAt = $createdDates[$index];
            
            Article::create([
                'title' => $articleData['title'],
                'slug' => Str::slug($articleData['title']),
                'content' => $articleData['content'],
                'category' => $articleData['category'],
                'image' => 'public/articles/images/' . fake()->randomElement($images),
                'status' => ArticlesStatusEnum::PUBLISHED,
                'is_public' => true,
                'user_id' => $user->id,
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addMinutes(rand(0, 30)), // Légère différence entre création et modification
            ]);
        }

        $this->command->info('20 articles créés avec succès pour le CTT Ottignies-Blocry avec des dates variées !');
    }

    /**
     * Génère des dates de création variées simulant une publication humaine
     */
    private function generateVariedDates(int $count): array
    {
        $dates = [];
        
        // Définir la plage de dates (janvier 2023 à décembre 2024)
        $startDate = Carbon::create(2023, 1, 1);
        $endDate = Carbon::create(2024, 12, 31);
        
        for ($i = 0; $i < $count; $i++) {
            // Générer une date aléatoire dans la plage
            $randomDate = Carbon::createFromTimestamp(
                rand($startDate->timestamp, $endDate->timestamp)
            );
            
            // Ajuster l'heure pour simuler une publication humaine
            $hour = $this->getRealisticPublicationHour();
            $minute = rand(0, 59);
            
            $randomDate->setTime($hour, $minute);
            
            $dates[] = $randomDate;
        }
        
        // Trier les dates par ordre chronologique
        usort($dates, function($a, $b) {
            return $a->timestamp - $b->timestamp;
        });
        
        return $dates;
    }

    /**
     * Retourne une heure réaliste de publication (simulation humaine)
     */
    private function getRealisticPublicationHour(): int
    {
        // Pondération des heures pour simuler un comportement humain
        $hourWeights = [
            6 => 1,   // 6h - rare
            7 => 2,   // 7h - peu fréquent
            8 => 5,   // 8h - matin
            9 => 8,   // 9h - début de journée
            10 => 10, // 10h - matinée
            11 => 12, // 11h - fin de matinée
            12 => 8,  // 12h - pause déjeuner
            13 => 6,  // 13h - après déjeuner
            14 => 10, // 14h - après-midi
            15 => 12, // 15h - milieu d'après-midi
            16 => 10, // 16h - fin d'après-midi
            17 => 8,  // 17h - fin de journée
            18 => 6,  // 18h - soirée
            19 => 8,  // 19h - soirée
            20 => 10, // 20h - soirée
            21 => 6,  // 21h - fin de soirée
            22 => 3,  // 22h - tard
            23 => 1,  // 23h - très tard
        ];
        
        // Créer un tableau avec répétition selon les poids
        $weightedHours = [];
        foreach ($hourWeights as $hour => $weight) {
            for ($i = 0; $i < $weight; $i++) {
                $weightedHours[] = $hour;
            }
        }
        
        return $weightedHours[array_rand($weightedHours)];
    }
}