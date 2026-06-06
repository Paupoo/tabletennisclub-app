<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Shared\Enums\NewsPostCategoryEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NewsPostSeeder extends Seeder
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
                'category' => NewsPostCategoryEnum::COMPETITION,
            ],
            [
                'title' => 'Nouveau partenariat avec Decathlon Wavre',
                'content' => 'Le club est fier d\'annoncer son nouveau partenariat avec Decathlon Wavre. Cette collaboration permettra à nos membres de bénéficier de 15% de réduction sur tout l\'équipement de tennis de table, ainsi que d\'un accès privilégié aux dernières nouveautés. En échange, notre club participera aux journées portes ouvertes du magasin avec des démonstrations et des initiations gratuites.',
                'category' => NewsPostCategoryEnum::PARTNERSHIP,
            ],
            [
                'title' => 'Portrait : Sarah Lemoine, la révélation de la saison',
                'content' => 'À seulement 16 ans, Sarah Lemoine collectionne déjà les victoires en championnat jeunes. Arrivée au club il y a deux ans, elle a rapidement gravi les échelons grâce à son style de jeu offensif et sa détermination. "Mon objectif est de rejoindre l\'équipe première l\'année prochaine", confie-t-elle. Son entraîneur, Philippe Durand, la voit déjà représenter la Belgique dans les compétitions internationales.',
                'category' => NewsPostCategoryEnum::PORTRAIT,
            ],
            [
                'title' => 'Tournoi de Noël : une belle réussite',
                'content' => 'Le traditionnel tournoi de Noël du CTT s\'est déroulé dans une ambiance chaleureuse le 15 décembre. Plus de 50 participants se sont affrontés dans différentes catégories. Le buffet préparé par les bénévoles a été très apprécié, ainsi que la tombola qui a permis de récolter 800 euros pour l\'achat de nouveau matériel. Rendez-vous l\'année prochaine pour une nouvelle édition !',
                'category' => NewsPostCategoryEnum::EVENT,
            ],
            [
                'title' => 'Stage d\'été : perfectionnement technique au programme',
                'content' => 'Le stage d\'été organisé du 15 au 19 juillet a accueilli 25 jeunes joueurs. Encadrés par trois entraîneurs diplômés, ils ont travaillé les fondamentaux : service, coup droit, revers et déplacements. "J\'ai appris à varier mes services et à mieux attaquer", témoigne Lucas, 12 ans. Le stage s\'est terminé par un mini-tournoi où chaque participant a reçu une médaille.',
                'category' => NewsPostCategoryEnum::TRAINING,
            ],
            [
                'title' => 'Assemblée générale : nouveaux projets à l\'horizon',
                'content' => 'L\'assemblée générale du 20 janvier a réuni une cinquantaine de membres. Le président Jean-Luc Bertrand a présenté les comptes 2024 et les projets pour 2025, notamment la rénovation du sol de la salle principale et l\'organisation d\'un tournoi inter-clubs. Le budget d\'investissement de 15 000 euros a été voté à l\'unanimité.',
                'category' => NewsPostCategoryEnum::NEWS,
            ],
            [
                'title' => 'Défaite honorable face au leader',
                'content' => 'Malgré une belle résistance, notre équipe première s\'est inclinée 16-8 face au TC Louvain-la-Neuve, leader du championnat. Les doubles ont été particulièrement disputés, avec deux victoires à l\'arraché. Antoine Moreau et Cédric Vanheule ont montré un excellent niveau, ne s\'inclinant qu\'au 5e set de leurs matchs respectifs.',
                'category' => NewsPostCategoryEnum::COMPETITION,
            ],
            [
                'title' => 'Collaboration avec l\'école Saint-Joseph',
                'content' => 'Le CTT étend son action vers les jeunes en proposant des cours d\'initiation à l\'école Saint-Joseph d\'Ottignies. Chaque mardi, une quinzaine d\'élèves de 5e et 6e primaire découvrent notre sport sous la houlette de moniteurs qualifiés. Plusieurs enfants ont déjà manifesté leur intérêt pour rejoindre le club.',
                'category' => NewsPostCategoryEnum::PARTNERSHIP,
            ],
            [
                'title' => 'Pierre Vandenberghe, 40 ans de passion',
                'content' => 'Membre fondateur du club, Pierre Vandenberghe fête ses 40 ans d\'engagement. Joueur, entraîneur, puis président durant 10 ans, il a marqué l\'histoire du CTT. "J\'ai vu le club grandir de 15 à 120 membres", se souvient-il. Aujourd\'hui encore, à 68 ans, il continue de jouer en vétérans et de transmettre sa passion aux jeunes générations.',
                'category' => NewsPostCategoryEnum::PORTRAIT,
            ],
            [
                'title' => 'Soirée karaoké : ambiance garantie !',
                'content' => 'La soirée karaoké du 8 février a fait salle comble. Entre deux chansons, les membres ont pu participer à des défis ping-pong amusants. Le duo surprise formé par le président et le trésorier sur "Les Lacs du Connemara" restera dans les mémoires ! Cette soirée conviviale a permis de renforcer les liens entre les générations du club.',
                'category' => NewsPostCategoryEnum::EVENT,
            ],
            [
                'title' => 'Nouveau cours débutants le mercredi',
                'content' => 'Face à la demande croissante, le club ouvre un nouveau cours débutants le mercredi de 19h30 à 21h. Encadré par Julie Delcroix, ce cours s\'adresse aux adultes souhaitant découvrir le tennis de table dans une ambiance détendue. Les inscriptions sont ouvertes, matériel fourni pour les premiers cours.',
                'category' => NewsPostCategoryEnum::TRAINING,
            ],
            [
                'title' => 'Don de matériel à l\'ASBL Télé-Accueil',
                'content' => 'Dans le cadre de son action sociale, le CTT a fait don d\'anciennes tables et de matériel d\'entraînement à l\'ASBL Télé-Accueil de Wavre. Cette association utilise le sport comme outil d\'insertion sociale. "C\'est important pour notre club de s\'engager dans la communauté", souligne la responsable communication, Marie Dupuis.',
                'category' => NewsPostCategoryEnum::NEWS,
            ],
            [
                'title' => 'Qualification pour les interclubs provinciaux',
                'content' => 'Grâce à leur excellente saison, nos jeunes se sont qualifiés pour les interclubs provinciaux. Emma Delforge, Thomas Willems et Maxime Boulanger représenteront fièrement les couleurs du CTT les 15 et 16 mars à Charleroi. Cette participation récompense le travail de formation mené par le club ces dernières années.',
                'category' => NewsPostCategoryEnum::COMPETITION,
            ],
            [
                'title' => 'La brasserie du Château, nouveau sponsor',
                'content' => 'La brasserie du Château de Blocry devient partenaire officiel du club. En plus du soutien financier, elle fournira les boissons pour nos événements. "Nous partageons les mêmes valeurs de convivialité et de tradition", explique le gérant, Michel Lejeune. Le logo de la brasserie ornera désormais nos maillots d\'équipe.',
                'category' => NewsPostCategoryEnum::PARTNERSHIP,
            ],
            [
                'title' => 'Michel Dumont, l\'entraîneur qui fait la différence',
                'content' => 'Arrivé il y a trois ans, Michel Dumont a révolutionné la formation au CTT. Ancien joueur de nationale, il a apporté ses méthodes modernes et sa rigueur. Sous sa direction, le niveau général du club a nettement progressé. "Michel sait motiver chaque joueur selon son niveau", témoigne Sylvie Mortier, responsable des jeunes.',
                'category' => NewsPostCategoryEnum::PORTRAIT,
            ],
            [
                'title' => 'Portes ouvertes : le club se dévoile',
                'content' => 'Les portes ouvertes du 12 mars ont attiré plus de 80 visiteurs. Démonstrations, initiations gratuites et présentation des équipes ont ponctué cette journée. Quinze nouvelles inscriptions ont été enregistrées, preuve de l\'attractivité de notre club. Les parents ont particulièrement apprécié l\'accueil chaleureux et les explications détaillées sur l\'organisation.',
                'category' => NewsPostCategoryEnum::EVENT,
            ],
            [
                'title' => 'Stage de perfectionnement avec un champion',
                'content' => 'Le club organise un stage exceptionnel avec Jean-Michel Saive, légende belge du tennis de table. Les 15 et 16 avril, 30 joueurs confirmés pourront bénéficier de ses conseils. "C\'est une opportunité unique de progresser avec un joueur de ce niveau", se réjouit l\'entraîneur principal. Les inscriptions sont limitées et se font par ordre d\'arrivée.',
                'category' => NewsPostCategoryEnum::TRAINING,
            ],
            [
                'title' => 'Nouveau site internet en ligne',
                'content' => 'Le CTT dévoile son nouveau site internet, plus moderne et interactif. Résultats en temps réel, galerie photos, inscription en ligne et boutique du club sont désormais accessibles. "Nous voulions moderniser notre communication", explique Kevin Martens, responsable informatique. Une application mobile est également en développement.',
                'category' => NewsPostCategoryEnum::NEWS,
            ],
            [
                'title' => 'Remontée spectaculaire en play-offs',
                'content' => 'Menés 8-4 à la mi-temps, nos joueurs ont réalisé une remontée extraordinaire pour s\'imposer 16-12 face au TC Jodoigne. Cette victoire nous qualifie pour les play-offs d\'accession en division supérieure. L\'ambiance était électrique dans la salle, avec un public venu nombreux encourager l\'équipe lors de ce match décisif.',
                'category' => NewsPostCategoryEnum::COMPETITION,
            ],
            [
                'title' => 'Hommage à André Libert, figure emblématique',
                'content' => 'Le club rend hommage à André Libert, décédé récemment à l\'âge de 75 ans. Membre durant 35 ans, il avait marqué le CTT par son dévouement et sa bonne humeur. Responsable du matériel pendant 20 ans, il était présent à chaque entraînement et chaque compétition. Une minute de silence a été observée avant le dernier match à domicile en son honneur.',
                'category' => NewsPostCategoryEnum::PORTRAIT,
            ],
        ];

        $createdDates = $this->generateVariedDates(count($articles), Carbon::create(2023, 1, 1), Carbon::create(2024, 12, 31));

        foreach ($articles as $index => $articleData) {
            $createdAt = $createdDates[$index];

            NewsPost::updateOrCreate(
                ['slug' => Str::slug($articleData['title'])],
                [
                    'title' => $articleData['title'],
                    'content' => $articleData['content'],
                    'category' => $articleData['category'],
                    'image' => $this->downloadPicsumImage($index + 1),
                    'status' => NewsPostStatusEnum::PUBLISHED,
                    'is_public' => true,
                    'user_id' => $user->id,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt->copy()->addMinutes(rand(0, 30)),
                ]
            );
        }

        // === Articles saison 2026-2027 ===
        $articles2627 = [
            [
                'title' => 'Coup d\'envoi de la saison 2026-2027 !',
                'content' => 'La nouvelle saison démarre avec de belles ambitions pour le CTT Ottignies-Blocry ! Après une saison 2025-2026 riche en émotions, le club revient avec une équipe renforcée et des objectifs ambitieux. Les entraînements reprennent dès le 8 septembre avec de nouveaux créneaux horaires adaptés à toutes les catégories. Bienvenue à nos 12 nouveaux membres et bonne saison à tous !',
                'category' => NewsPostCategoryEnum::NEWS,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Compte-rendu de l\'assemblée générale de début de saison',
                'content' => 'L\'assemblée générale annuelle s\'est tenue le 15 septembre en présence de 38 membres. Le président Olivier Pauwels a présenté le bilan de la saison écoulée et les objectifs 2026-2027. Le budget prévisionnel de 22 000 euros a été approuvé à l\'unanimité. Parmi les décisions : acquisition de 2 nouvelles tables Tibhar, réfection des vestiaires et lancement d\'une section jeunes renforcée. Gilles Herpigny est reconduit à la trésorerie.',
                'category' => NewsPostCategoryEnum::NEWS,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => false,
            ],
            [
                'title' => 'Nouveau partenariat avec l\'Optique Lemmens d\'Ottignies',
                'content' => 'Le CTT est heureux d\'accueillir l\'Optique Lemmens comme nouveau partenaire officiel du club. Dès octobre 2026, tous les membres en règle de cotisation bénéficieront de 20% de réduction sur les verres correcteurs et les montures. En retour, le logo Lemmens figurera sur nos maillots d\'entraînement. "Nous voulions soutenir une association sportive locale active", explique Marion Lemmens, gérante du magasin.',
                'category' => NewsPostCategoryEnum::PARTNERSHIP,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Portrait : Thomas Willems, la belle progression',
                'content' => 'À 17 ans, Thomas Willems s\'est imposé comme l\'un des espoirs les plus sérieux du club. Pratiquant le tennis de table depuis l\'âge de 9 ans au CTT, il a franchi un cap décisif cette année en intégrant l\'équipe première. Son coup droit dévastateur et sa lecture du jeu font l\'admiration de ses coéquipiers. "Thomas a la mentalité du champion, il travaille deux fois plus que les autres", confie son entraîneur. Objectif de la saison : décrocher un classement national D.',
                'category' => NewsPostCategoryEnum::PORTRAIT,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Stage de la Toussaint : 30 jeunes au programme',
                'content' => 'Du 26 au 28 octobre, 30 jeunes joueurs entre 8 et 17 ans ont participé au stage de la Toussaint. Réparti en trois groupes selon le niveau, le stage a mis l\'accent sur les services variés, les placements tactiques et le mental compétitif. Une séance vidéo d\'analyse de matchs professionnels a particulièrement enthousiasmé les participants. "J\'ai surtout travaillé mon revers, qui était mon point faible", témoigne Léa, 13 ans.',
                'category' => NewsPostCategoryEnum::TRAINING,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Solide entrée en matière pour nos équipes interclubs',
                'content' => 'Premier week-end de compétition interclubs et nos équipes ont répondu présent ! L\'équipe première s\'est imposée 14-4 face à PP Witterzee, portée par un Arnaud Ghysens en grande forme (3 victoires en simple). L\'équipe B a partagé les points face à CTT Hamme-Mille (10-10) dans un match très disputé. Les vétérans ont eux remporté une nette victoire 16-2. Une belle entrée en matière qui augure bien pour la suite de la saison.',
                'category' => NewsPostCategoryEnum::COMPETITION,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Journées portes ouvertes : le club fait salle comble',
                'content' => 'Les portes ouvertes organisées les 8 et 9 novembre ont dépassé toutes les espérances : plus de 110 visiteurs ont découvert notre club sur deux journées. Les initiations gratuites pour les enfants ont rencontré un vif succès, tout comme les démonstrations de nos joueurs de haut niveau. Au total, 23 nouvelles inscriptions ont été enregistrées dont 15 jeunes. Un beau renouvellement pour le club !',
                'category' => NewsPostCategoryEnum::EVENT,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Défaite courageuse face au CTT Limal-Wavre (8-16)',
                'content' => 'Malgré une résistance acharnée, notre équipe première s\'est inclinée à domicile face au CTT Limal-Wavre, l\'une des équipes favorites du championnat. Le score (8-16) est sévère mais ne reflète pas la qualité des échanges. Xavier Coenen a livré deux magnifiques sets pour s\'imposer en simple, et le double Ghysens/Tilmans a arraché une victoire au 5e set. La rencontre a mis en évidence notre axe de progression : la régularité sous pression.',
                'category' => NewsPostCategoryEnum::COMPETITION,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Tournoi de Noël 2026 : une édition mémorable',
                'content' => 'Le traditionnel tournoi de Noël a rassemblé 64 participants cette année, un record pour l\'événement ! Cinq catégories étaient au programme, des poussins aux vétérans. La finale messieurs a opposé deux membres du club dans un match d\'anthologie remporté en 5 sets. Le père Noël a distribué des cadeaux aux plus jeunes et la tombola a permis de récolter 1 200 euros pour le renouvellement des filets. Rendez-vous en décembre 2027 !',
                'category' => NewsPostCategoryEnum::EVENT,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Bilan financier du premier trimestre — réservé aux membres',
                'content' => 'À mi-parcours du premier trimestre, les finances du club sont saines. Les recettes s\'élèvent à 8 450 euros (cotisations, tournoi de Noël, partenariats) pour des dépenses de 6 200 euros (location salle, matériel, déplacements). La réserve de trésorerie est de 14 300 euros. Le comité propose d\'affecter 2 000 euros à l\'achat de revêtements de raquettes pour les jeunes en formation. Vote lors de la prochaine réunion de comité.',
                'category' => NewsPostCategoryEnum::NEWS,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => false,
            ],
            [
                'title' => 'Présentation du nouveau bureau et des ambitions 2027',
                'content' => 'Suite aux élections de janvier, le bureau du CTT Ottignies-Blocry se renouvelle partiellement. Olivier Pauwels reste président, Manon Patigny est reconduite au secrétariat. Julie Renard rejoint le comité comme responsable communication, et Simon Beaumont prend en charge la coordination des entraînements jeunes. Les nouveaux membres du bureau ont présenté leurs projets pour le second semestre, notamment la refonte du site internet et le développement de la section féminine.',
                'category' => NewsPostCategoryEnum::NEWS,
                'status' => NewsPostStatusEnum::DRAFT,
                'is_public' => false,
            ],
            [
                'title' => 'Un nouveau créneau d\'entraînement le vendredi soir',
                'content' => 'Face aux demandes répétées de nos compétiteurs, le club ouvre un nouveau créneau d\'entraînement le vendredi de 20h à 22h30. Encadré par Arnaud Ghysens et réservé aux joueurs classés E2 et plus, ce créneau sera axé sur la préparation aux matchs et l\'analyse vidéo. Six tables seront disponibles. Capacité limitée à 15 joueurs, inscription obligatoire via l\'application du club.',
                'category' => NewsPostCategoryEnum::TRAINING,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Emma Delforge : cinq ans de dévouement au CTT',
                'content' => 'Emma Delforge fête cette année ses cinq ans au CTT Ottignies-Blocry, et quelle progression ! Arrivée à 14 ans avec un niveau débutant, elle est aujourd\'hui classée D6 et s\'apprête à intégrer l\'équipe dames en interclubs. Mais Emma, c\'est aussi une bénévole précieuse : co-organisatrice du tournoi de Noël, présente à chaque journée portes ouvertes, elle incarne les valeurs de solidarité et d\'engagement du club.',
                'category' => NewsPostCategoryEnum::PORTRAIT,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Soirée quiz inter-générations : un vrai succès !',
                'content' => 'La soirée quiz organisée le 14 février a mélangé les générations dans une ambiance festive. Dix équipes de quatre joueurs ont concouru sur des thèmes variés : tennis de table, histoire du club, actualités sportives et culture générale. L\'équipe "Les Vétérans Invincibles" (moyenne d\'âge 58 ans) a créé la surprise en l\'emportant face aux favoris. La soirée s\'est terminée autour d\'un buffet convivial préparé par les bénévoles.',
                'category' => NewsPostCategoryEnum::EVENT,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Victoire nette face au TT Perwez (16-6)',
                'content' => 'Notre équipe première a signé l\'une de ses meilleures performances de la saison en dominant largement TT Perwez (16-6). Dariusz Sekula a été impérial avec trois victoires en simple, dont une contre leur meilleur joueur en 3 sets secs. Les deux doubles ont été remportés sans trembler. Cette victoire nous propulse à la 2e place du classement avec deux matchs d\'avance sur notre dauphin. L\'esprit de groupe et la communication sur le terrain ont fait la différence.',
                'category' => NewsPostCategoryEnum::COMPETITION,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Stage de Pâques avec Julien Sauvé, entraîneur national',
                'content' => 'Les vacances de Pâques ont été marquées par un stage d\'exception avec Julien Sauvé, entraîneur fédéral et ancien joueur de ligue nationale. Pendant deux jours, 20 joueurs ont travaillé la technique de pointe, le jeu court et la préparation mentale. "Julien nous a montré des exercices qu\'on ne pratique jamais en entraînement habituel", explique Sébastien Vandevyver. Le stage s\'est conclu par un match exhibition très applaudi.',
                'category' => NewsPostCategoryEnum::TRAINING,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Le CTT rejoint le réseau Sport & Santé Brabant wallon',
                'content' => 'Le CTT Ottignies-Blocry est désormais membre du réseau Sport & Santé Brabant wallon, une initiative provinciale promouvant l\'activité physique pour tous. Ce partenariat nous permettra d\'accueillir des personnes en réinsertion et des seniors via des séances adaptées, financées en partie par la Province. "C\'est une belle façon de rendre le tennis de table accessible au plus grand nombre", commente Manon Patigny, secrétaire du club.',
                'category' => NewsPostCategoryEnum::PARTNERSHIP,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Montée historique : le CTT accède à la provinciale 3 !',
                'content' => 'C\'est officiel : notre équipe première est promue en provinciale 3 ! Après une saison quasi parfaite (13 victoires, 2 nuls, 1 défaite), nos joueurs ont validé leur montée lors de l\'avant-dernière journée. Les félicitations ont fusé dans le vestiaire : "C\'est l\'aboutissement de trois ans de travail", s\'est ému le capitaine Olivier Tilmans. Une montée qui récompense l\'investissement de tout le groupe et du staff technique.',
                'category' => NewsPostCategoryEnum::COMPETITION,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Projet de partenariat avec Decathlon Louvain-la-Neuve',
                'content' => 'Le club est en discussion avancée avec le Decathlon de Louvain-la-Neuve pour établir un partenariat matériel sur la saison 2027-2028. Le projet prévoit une remise de 20% sur les achats de raquettes, balles et équipements pour tous les membres, ainsi que la mise à disposition de matériel de démonstration lors des journées portes ouvertes. Finalisation du contrat prévue d\'ici fin juin 2027.',
                'category' => NewsPostCategoryEnum::PARTNERSHIP,
                'status' => NewsPostStatusEnum::DRAFT,
                'is_public' => false,
            ],
            [
                'title' => 'Convocation à l\'assemblée générale de fin de saison',
                'content' => 'Les membres du CTT Ottignies-Blocry sont convoqués à l\'assemblée générale de fin de saison le jeudi 12 juin 2027 à 19h30, salle polyvalente du Centre Sportif Jean Demeester. Ordre du jour : bilan sportif et financier de la saison 2026-2027, rapport du trésorier, montée en provinciale 3 et implications budgétaires, renouvellement partiel du comité, divers. La présence de chaque membre est vivement souhaitée. Un verre de l\'amitié clôturera la soirée.',
                'category' => NewsPostCategoryEnum::NEWS,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => false,
            ],
        ];

        $createdDates2627 = $this->generateVariedDates(count($articles2627), Carbon::create(2026, 9, 1), Carbon::create(2027, 6, 30));

        foreach ($articles2627 as $index => $articleData) {
            $createdAt = $createdDates2627[$index];

            NewsPost::updateOrCreate(
                ['slug' => Str::slug($articleData['title'])],
                [
                    'title' => $articleData['title'],
                    'content' => $articleData['content'],
                    'category' => $articleData['category'],
                    'image' => $this->downloadPicsumImage($index + 21),
                    'status' => $articleData['status'],
                    'is_public' => $articleData['is_public'],
                    'user_id' => $user->id,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt->copy()->addMinutes(rand(0, 30)),
                ]
            );
        }

        $this->command->info('40 articles créés avec succès pour le CTT Ottignies-Blocry (20 × 2023-2024 + 20 × 2026-2027) !');
    }

    private function downloadPicsumImage(int $seed): string
    {
        $path = "public/articles/images/picsum-{$seed}.jpg";

        if (! Storage::exists($path)) {
            $response = Http::get("https://picsum.photos/seed/{$seed}/1200/630");
            Storage::put($path, $response->body());
        }

        return $path;
    }

    private function generateVariedDates(int $count, Carbon $start, Carbon $end): array
    {
        $dates = [];

        for ($i = 0; $i < $count; $i++) {
            $randomDate = Carbon::createFromTimestamp(
                rand($start->timestamp, $end->timestamp)
            );

            $hour = $this->getRealisticPublicationHour();
            $minute = rand(0, 59);

            $randomDate->setTime($hour, $minute);

            $dates[] = $randomDate;
        }

        usort($dates, function ($a, $b) {
            return $a->timestamp - $b->timestamp;
        });

        return $dates;
    }

    private function getRealisticPublicationHour(): int
    {
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

        $weightedHours = [];
        foreach ($hourWeights as $hour => $weight) {
            for ($i = 0; $i < $weight; $i++) {
                $weightedHours[] = $hour;
            }
        }

        return $weightedHours[array_rand($weightedHours)];
    }
}
