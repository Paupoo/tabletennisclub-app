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
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Admin CTT',
            'email' => 'admin@ctt-ottignies.be',
        ]);

        // === Articles 2023-2024 ===
        $articles = [
            [
                'title' => 'Victoire éclatante en championnat régional',
                'content' => <<<'MD'
                    Le CTT Ottignies-Blocry a remporté une **victoire décisive** face au TC Wavre lors de la 7e journée du championnat provincial.

                    ## Résultat final : 16-2

                    ### Performances individuelles

                    - **Marc Delcroix** — 3 victoires en simple, aucun set concédé
                    - **Antoine Moreau** — 2 victoires en simple, dont une en 3 sets
                    - **Double Moreau/Vanheule** — victoire en 4 sets

                    > "On a joué notre meilleur tennis de la saison. L'équipe était concentrée dès le premier point." — *Marc Delcroix, capitaine*

                    Cette victoire nous place en **tête du classement** de la division 3, avec trois points d'avance sur nos poursuivants. Prochain match à domicile samedi prochain.
                    MD,
                'category' => NewsPostCategoryEnum::COMPETITION,
            ],
            [
                'title' => 'Nouveau partenariat avec Decathlon Wavre',
                'content' => <<<'MD'
                    Le club est fier d'annoncer son nouveau **partenariat officiel avec Decathlon Wavre**, à partir du mois d'octobre.

                    ## Ce que ça change pour vous

                    - **15 % de réduction** sur tout l'équipement de tennis de table
                    - Accès privilégié aux **dernières nouveautés** avant leur mise en rayon
                    - Invitation aux journées découverte organisées en magasin

                    ## Nos engagements en retour

                    En échange, le CTT participera aux **journées portes ouvertes de Decathlon** avec des démonstrations et des initiations gratuites pour le grand public.

                    > "C'est un partenariat gagnant-gagnant. Decathlon bénéficie de notre expertise terrain, nos membres profitent de tarifs préférentiels." — *La direction du club*

                    Pour activer votre réduction, présentez votre **carte de membre à jour** en caisse.
                    MD,
                'category' => NewsPostCategoryEnum::PARTNERSHIP,
            ],
            [
                'title' => 'Portrait : Sarah Lemoine, la révélation de la saison',
                'content' => <<<'MD'
                    À seulement **16 ans**, Sarah Lemoine s'est imposée comme la grande révélation de cette saison au CTT Ottignies-Blocry.

                    ## Son parcours

                    Arrivée au club **il y a deux ans**, elle a rapidement gravi les échelons grâce à un style de jeu offensif et une détermination sans faille.

                    ## En chiffres cette saison

                    - **23 victoires** en simple sur 27 matchs joués
                    - Classement passé de **E4 à E2** en une saison
                    - Finaliste du tournoi provincial jeunes filles

                    > "Mon objectif est de rejoindre l'équipe première l'année prochaine." — *Sarah Lemoine*

                    Son entraîneur, **Philippe Durand**, la voit déjà représenter la Belgique dans les compétitions internationales U18.

                    ## La suite

                    Sarah participera au stage national de sélection en juillet. Toute l'équipe lui souhaite bonne chance !
                    MD,
                'category' => NewsPostCategoryEnum::PORTRAIT,
            ],
            [
                'title' => 'Tournoi de Noël : une belle réussite',
                'content' => <<<'MD'
                    Le traditionnel **Tournoi de Noël du CTT** s'est déroulé dans une ambiance chaleureuse et festive le samedi 15 décembre.

                    ## Les lauréats

                    | Catégorie | Vainqueur |
                    |---|---|
                    | Messieurs | Marc Delcroix |
                    | Dames | Julie Fontaine |
                    | Vétérans | Pierre Vandenberghe |
                    | Jeunes | Thomas Willems |

                    ## En coulisses

                    Le **buffet préparé par les bénévoles** a remporté un franc succès, tout comme la **tombola** qui a permis de récolter **800 €** pour l'achat de nouveau matériel.

                    Avec plus de **50 participants** répartis en 4 catégories, cette édition restera l'une des plus animées. Rendez-vous l'année prochaine pour une nouvelle édition encore plus festive !
                    MD,
                'category' => NewsPostCategoryEnum::EVENT,
            ],
            [
                'title' => 'Stage d\'été : perfectionnement technique au programme',
                'content' => <<<'MD'
                    Du **15 au 19 juillet**, le CTT Ottignies-Blocry a organisé son stage d'été annuel, accueillant **25 jeunes joueurs** de 10 à 17 ans.

                    ## Programme de la semaine

                    - **Lundi–Mardi** : Fondamentaux — service, coup droit, revers
                    - **Mercredi** : Déplacements et tactique en simple
                    - **Jeudi** : Jeu en double et communication
                    - **Vendredi** : Mini-tournoi de clôture

                    Encadrés par **trois entraîneurs diplômés**, les jeunes ont progressé dans une ambiance studieuse et bienveillante.

                    > "J'ai appris à varier mes services et à mieux attaquer. Je me sens beaucoup plus à l'aise en match." — *Lucas, 12 ans*

                    Le stage s'est terminé par un mini-tournoi où **chaque participant a reçu une médaille**. Déjà hâte de l'édition 2024 !
                    MD,
                'category' => NewsPostCategoryEnum::TRAINING,
            ],
            [
                'title' => 'Assemblée générale : nouveaux projets à l\'horizon',
                'content' => <<<'MD'
                    L'**assemblée générale annuelle** du CTT Ottignies-Blocry s'est tenue le 20 janvier et a réuni une **cinquantaine de membres**.

                    ## Bilan 2024

                    Le président **Jean-Luc Bertrand** a présenté les comptes de l'exercice écoulé :

                    - Recettes : **18 400 €** (cotisations, tournois, partenariats)
                    - Dépenses : **16 200 €** (salle, matériel, déplacements)
                    - Excédent : **2 200 €**

                    ## Projets 2025 votés à l'unanimité

                    1. **Rénovation du sol** de la salle principale — budget : 8 000 €
                    2. **Organisation d'un tournoi inter-clubs** en mars — ouvert aux clubs de la province

                    > "Ces investissements reflètent notre volonté de toujours améliorer les conditions d'entraînement." — *Jean-Luc Bertrand, président*

                    Le **budget d'investissement total de 15 000 euros** a été voté à l'unanimité. Prochain point d'étape en juin.
                    MD,
                'category' => NewsPostCategoryEnum::NEWS,
            ],
            [
                'title' => 'Défaite honorable face au leader',
                'content' => <<<'MD'
                    Notre équipe première s'est inclinée **16-8** face au TC Louvain-la-Neuve, leader incontesté du championnat provincial.

                    ## Analyse du match

                    ### Ce qui a fonctionné

                    - **Antoine Moreau** — 2 victoires, s'incline seulement au 5e set
                    - **Cédric Vanheule** — belle performance, battu de justesse au 5e set
                    - Le **double Moreau/Vanheule** — victoire en 4 sets

                    ### Les points à améliorer

                    - Régularité en début de match — 4 défaites concédées dès le 3e set
                    - Gestion du stress face à un adversaire de niveau supérieur

                    > "On a montré qu'on peut tenir tête aux meilleurs. C'est encourageant pour la suite." — *Cédric Vanheule*

                    Le championnat reprend dans **deux semaines** avec un déplacement à Nivelles.
                    MD,
                'category' => NewsPostCategoryEnum::COMPETITION,
            ],
            [
                'title' => 'Collaboration avec l\'école Saint-Joseph',
                'content' => <<<'MD'
                    Le CTT Ottignies-Blocry étend son action vers la jeunesse avec un nouveau **programme d'initiation à l'école Saint-Joseph d'Ottignies**.

                    ## Comment ça fonctionne ?

                    Chaque **mardi de 13h30 à 14h30**, une quinzaine d'élèves de **5e et 6e primaire** découvrent le tennis de table sous la houlette de deux moniteurs qualifiés du club. Le matériel est entièrement fourni par le CTT.

                    ## Résultats après deux mois

                    - **60 élèves** sensibilisés depuis le début du programme
                    - **4 inscriptions** de jeunes ayant rejoint le club après les séances
                    - Demande de la direction pour étendre le programme à d'autres classes

                    > "C'est un plaisir de transmettre notre passion à des enfants qui n'auraient peut-être jamais touché une raquette." — *Julie Delcroix, monitrice*

                    Le programme est reconduit pour l'année scolaire 2024-2025. Si vous souhaitez **contribuer comme bénévole**, contactez le club !
                    MD,
                'category' => NewsPostCategoryEnum::PARTNERSHIP,
            ],
            [
                'title' => 'Pierre Vandenberghe, 40 ans de passion',
                'content' => <<<'MD'
                    **Pierre Vandenberghe** fête cette année ses **40 ans d'engagement** au CTT Ottignies-Blocry. Un anniversaire qui mérite qu'on s'arrête sur une vie dédiée au club.

                    ## Un parcours complet

                    | Période | Rôle |
                    |---|---|
                    | 1984–1995 | Joueur en équipe première |
                    | 1995–2005 | Entraîneur des jeunes |
                    | 2005–2015 | Président du club |
                    | 2015– | Joueur vétérans + mentor |

                    > "J'ai vu le club grandir de 15 à 120 membres. Chaque nouveau visage est une victoire." — *Pierre Vandenberghe*

                    ## Aujourd'hui

                    À **68 ans**, Pierre continue de jouer en vétérans tous les jeudis soirs. Il est aussi la mémoire vivante du club : c'est lui qui garde les archives, les trophées, et surtout les anecdotes.

                    Merci Pierre, pour tout ce que tu représentes pour le CTT !
                    MD,
                'category' => NewsPostCategoryEnum::PORTRAIT,
            ],
            [
                'title' => 'Soirée karaoké : ambiance garantie !',
                'content' => <<<'MD'
                    La **soirée karaoké** du 8 février est passée à la postérité dans les annales du club. Retour sur une soirée inoubliable.

                    ## Le programme

                    - **19h** — Accueil et apéritif
                    - **19h30** — Ouverture du karaoké
                    - **20h30** — Défis ping-pong en parallèle
                    - **22h** — Remise du prix "meilleur performeur"
                    - **23h** — Clôture festive

                    ## Le moment mémorable

                    Sans conteste, le **duo surprise** formé par le président et le trésorier sur *"Les Lacs du Connemara"* restera dans les mémoires collectives du club pour de nombreuses années.

                    > "Je ne suis pas chanteur, mais je me suis quand même lâché !" — *Le président, qui préfère rester anonyme sur ce point*

                    Cette soirée conviviale a permis de **renforcer les liens entre les générations** du club. Rendez-vous l'an prochain !
                    MD,
                'category' => NewsPostCategoryEnum::EVENT,
            ],
            [
                'title' => 'Nouveau cours débutants le mercredi',
                'content' => <<<'MD'
                    Face à une **demande croissante**, le club ouvre un nouveau créneau pour les adultes souhaitant découvrir le tennis de table.

                    ## Les infos pratiques

                    | | |
                    |---|---|
                    | **Jour** | Mercredi |
                    | **Horaire** | 19h30 – 21h00 |
                    | **Lieu** | Salle Demeester -1 |
                    | **Encadrement** | Julie Delcroix (BE2) |
                    | **Public** | Adultes débutants |
                    | **Tarif** | Inclus dans la cotisation |

                    ## Au programme

                    - Prise en main de la raquette et position de base
                    - Coups fondamentaux : coup droit, revers, service
                    - Échanges et premiers points joués
                    - Progression à votre rythme, sans pression

                    **Le matériel est fourni** pour les 3 premières séances. Les places sont **limitées à 12 participants** — inscriptions via le secrétariat du club.
                    MD,
                'category' => NewsPostCategoryEnum::TRAINING,
            ],
            [
                'title' => 'Don de matériel à l\'ASBL Télé-Accueil',
                'content' => <<<'MD'
                    Dans le cadre de son **engagement social**, le CTT Ottignies-Blocry a remis une donation de matériel à l'**ASBL Télé-Accueil de Wavre**.

                    ## Ce qui a été donné

                    - 2 **tables de ping-pong** en bon état de fonctionnement
                    - 20 **raquettes** de différents niveaux
                    - 8 **boîtes de balles** neuves
                    - Filets et poteaux de rechange

                    ## L'ASBL Télé-Accueil

                    Cette association utilise le **sport comme outil d'insertion sociale** auprès de personnes en situation de précarité. Le tennis de table, accessible et peu coûteux, s'intègre parfaitement dans leurs activités.

                    > "Ce don va permettre à une vingtaine de personnes de pratiquer une activité sportive régulière." — *Directrice de l'ASBL Télé-Accueil*

                    > "C'est important pour notre club de s'engager dans la communauté au-delà du sport pur." — *Marie Dupuis, responsable communication CTT*

                    Le CTT envisage également d'**envoyer des bénévoles** pour animer des séances d'initiation dans les locaux de l'ASBL.
                    MD,
                'category' => NewsPostCategoryEnum::NEWS,
            ],
            [
                'title' => 'Qualification pour les interclubs provinciaux',
                'content' => <<<'MD'
                    Excellente nouvelle : **trois de nos jeunes joueurs** se sont qualifiés pour les interclubs provinciaux qui se tiendront les **15 et 16 mars à Charleroi** !

                    ## Les qualifiés

                    - **Emma Delforge** — classée E0, qualifiée en dames U18
                    - **Thomas Willems** — classé E2, qualifié en messieurs U18
                    - **Maxime Boulanger** — classé E4, qualifié en messieurs U15

                    ## Comment ils se sont qualifiés

                    Les trois joueurs ont terminé dans le **top 3 de leur catégorie** lors du tournoi de qualification du mois dernier. Leurs performances régulières tout au long de la saison ont été récompensées.

                    > "On est fiers de représenter le club. On va tout donner !" — *Thomas Willems*

                    ## Soutien du club

                    Le CTT prend en charge les **frais de déplacement et d'inscription**. Un entraînement spécial de préparation est prévu la semaine avant la compétition.
                    MD,
                'category' => NewsPostCategoryEnum::COMPETITION,
            ],
            [
                'title' => 'La brasserie du Château, nouveau sponsor',
                'content' => <<<'MD'
                    Le CTT Ottignies-Blocry accueille un nouveau partenaire de choix : la **Brasserie du Château de Blocry** devient sponsor officiel du club pour la saison à venir.

                    ## Ce que ce partenariat apporte

                    - **Soutien financier** à hauteur de 2 500 €/an
                    - **Boissons fournies** pour tous nos événements (tournois, AG, soirées)
                    - **Logo de la brasserie** sur nos maillots d'équipe officielle

                    > "Nous partageons les mêmes valeurs de convivialité, de tradition et d'ancrage local. Ce partenariat était une évidence." — *Michel Lejeune, gérant de la Brasserie du Château*

                    ## Prochaine étape

                    Une **soirée de lancement** du partenariat est organisée le 15 mars à la brasserie. Tous les membres sont invités — places limitées, inscription obligatoire via le secrétariat.
                    MD,
                'category' => NewsPostCategoryEnum::PARTNERSHIP,
            ],
            [
                'title' => 'Michel Dumont, l\'entraîneur qui fait la différence',
                'content' => <<<'MD'
                    Arrivé il y a **trois ans**, **Michel Dumont** a profondément transformé l'approche de l'entraînement au CTT Ottignies-Blocry.

                    ## Son parcours

                    Ancien joueur de **division nationale 3** pendant 8 ans, Michel est titulaire du **diplôme d'entraîneur fédéral BE3**, la plus haute certification provinciale.

                    ## Sa méthode

                    Depuis son arrivée, il a introduit :

                    - L'**analyse vidéo** des matchs
                    - Des **tests physiques** trimestriels
                    - Un suivi **individualisé** par joueur

                    ## Les résultats

                    - **+3 divisions** pour 4 joueurs du club en 3 ans
                    - Niveau moyen du club passé de **E4 à E2**
                    - **2 sélections nationales** jeunes

                    > "Michel sait motiver chaque joueur selon son niveau et sa personnalité. Il ne fait pas du copier-coller." — *Sylvie Mortier, responsable jeunes*
                    MD,
                'category' => NewsPostCategoryEnum::PORTRAIT,
            ],
            [
                'title' => 'Portes ouvertes : le club se dévoile',
                'content' => <<<'MD'
                    Le **12 mars**, le CTT Ottignies-Blocry a ouvert grand ses portes au public. Une journée réussie à tous les niveaux !

                    ## Au programme

                    - **10h–12h** : Initiations gratuites pour les enfants (6–12 ans)
                    - **12h–13h** : Démonstration par les joueurs de compétition
                    - **14h–17h** : Initiations adultes + visite des installations
                    - **17h** : Présentation officielle des équipes et du staff

                    ## Les chiffres

                    - **80+ visiteurs** sur la journée
                    - **15 nouvelles inscriptions**, dont 8 jeunes
                    - **3 familles** inscrites en entier

                    > "On ne s'attendait pas à autant de monde. L'équipe bénévole a été formidable." — *La direction du club*

                    Les parents ont particulièrement apprécié les **explications claires sur l'organisation** du club : niveaux d'entraînement, frais de cotisation, planning annuel.
                    MD,
                'category' => NewsPostCategoryEnum::EVENT,
            ],
            [
                'title' => 'Stage de perfectionnement avec un champion',
                'content' => <<<'MD'
                    Le CTT Ottignies-Blocry a le plaisir d'annoncer un **stage exceptionnel** avec **Jean-Michel Saive**, légende vivante du tennis de table belge.

                    ## Détails du stage

                    | | |
                    |---|---|
                    | **Dates** | 15 et 16 avril |
                    | **Lieu** | Salle Demeester, Ottignies |
                    | **Participants** | 30 joueurs max (classés E2+) |
                    | **Programme** | Technique avancée, tactique, mental |
                    | **Tarif** | 80 €/personne (2 jours) |

                    ## Jean-Michel Saive en quelques chiffres

                    - **17 fois** champion de Belgique
                    - **Numéro 1 mondial** en 1994
                    - Recordman mondial de la longévité au plus haut niveau

                    > "C'est une opportunité unique de progresser avec un joueur de ce niveau. Ces occasions sont rarissimes en province." — *Michel Dumont, entraîneur principal*

                    **Places limitées** — inscriptions par ordre d'arrivée. Acompte de 20 € requis à l'inscription.
                    MD,
                'category' => NewsPostCategoryEnum::TRAINING,
            ],
            [
                'title' => 'Nouveau site internet en ligne',
                'content' => <<<'MD'
                    Le CTT Ottignies-Blocry fait peau neuve sur le web ! Notre **nouveau site internet** est en ligne et propose de nombreuses fonctionnalités inédites.

                    ## Ce qui change

                    ### Pour les membres
                    - **Espace membre** avec accès aux résultats, classements et planning
                    - **Inscription en ligne** aux entraînements et tournois
                    - **Boutique** : commandez vos maillots directement en ligne

                    ### Pour les visiteurs
                    - Présentation du club et des équipes
                    - **Calendrier des événements** en temps réel
                    - Galerie photos des compétitions

                    ## Dans les coulisses

                    Le projet a été mené par **Kevin Martens**, membre du club et développeur web professionnel, sur une période de **6 mois de travail bénévole**.

                    > "On voulait moderniser notre communication et offrir un outil utile aux membres au quotidien." — *Kevin Martens*

                    Une **application mobile** est en cours de développement. Restez connectés !
                    MD,
                'category' => NewsPostCategoryEnum::NEWS,
            ],
            [
                'title' => 'Remontée spectaculaire en play-offs',
                'content' => <<<'MD'
                    Scénario incroyable lors des play-offs d'accession : **menés 8-4 à la mi-temps**, nos joueurs ont réalisé une remontée extraordinaire pour s'imposer **16-12** face au TC Jodoigne !

                    ## Le match en deux actes

                    ### Première mi-temps : la douche froide (4-8)
                    - 3 défaites en simple concédées
                    - Un double perdu de justesse au 5e set
                    - Ambiance tendue dans le camp CTT

                    ### Deuxième mi-temps : le retour des guerriers (12-4)
                    - 4 victoires en simple consécutives
                    - Le double de la délivrance remporté 11-9 au 5e set
                    - Salle en délire

                    ## Les héros du soir

                    | Joueur | Bilan |
                    |---|---|
                    | Arnaud Ghysens | 3V / 1D |
                    | Marc Delcroix | 2V / 1D |
                    | Sébastien Vandevyver | 2V / 1D |

                    > "Je n'ai jamais vécu ça en 20 ans de compétition. Incroyable." — *Marc Delcroix*

                    Cette victoire nous **qualifie pour les play-offs d'accession** en division supérieure. Prochains matchs dans trois semaines.
                    MD,
                'category' => NewsPostCategoryEnum::COMPETITION,
            ],
            [
                'title' => 'Hommage à André Libert, figure emblématique',
                'content' => <<<'MD'
                    C'est avec une profonde tristesse que le CTT Ottignies-Blocry annonce le décès d'**André Libert**, survenu le mois dernier à l'âge de **75 ans**.

                    ## Une vie au service du club

                    André était membre du CTT depuis **35 ans**. Jamais le plus flamboyant sur le terrain, il était pourtant l'une des chevilles ouvrières du club :

                    - **Responsable du matériel** pendant 20 ans
                    - Présent à **chaque entraînement** et **chaque compétition**
                    - Organisateur discret de dizaines de tournois et événements
                    - Bénévole infatigable, toujours le premier arrivé, le dernier parti

                    > "André, c'était la fiabilité incarnée. On savait qu'il serait là, quoi qu'il arrive." — *Jean-Luc Bertrand, président*

                    > "Il m'a appris que le bénévolat, c'est une façon d'aimer. Il aimait notre club de tout son être." — *Pierre Vandenberghe*

                    ## Hommage

                    Une **minute de silence** a été observée avant le dernier match à domicile de la saison. Le club envisage de **nommer l'une de nos salles** en sa mémoire.

                    Nos pensées vont à sa famille et à ses proches.
                    MD,
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
                'content' => <<<'MD'
                    La nouvelle saison est lancée ! Après une saison 2025-2026 marquée par la montée en provinciale 3, le **CTT Ottignies-Blocry** revient avec une équipe renforcée et de grandes ambitions.

                    ## Les nouveautés de la saison

                    - **12 nouveaux membres** accueillis, dont 5 jeunes compétiteurs
                    - Acquisition de **3 nouvelles tables Tibhar** pour la salle principale
                    - Partenariat avec l'Optique Lemmens (20 % de réduction pour les membres)

                    ## Le planning des entraînements 2026-2027

                    | Créneau | Public | Encadrant |
                    |---|---|---|
                    | Lundi 18h30–20h | Jeunes 10–15 ans | Julie Delcroix |
                    | Mardi 20h–22h | Adultes tous niveaux | Arnaud Ghysens |
                    | Jeudi 20h–22h | Compétiteurs | Michel Dumont |
                    | Vendredi 20h–22h30 | Élite (E2+) | Arnaud Ghysens |
                    | Samedi 9h–11h | Jeunes 8–12 ans | Simon Beaumont |

                    ## Les objectifs sportifs

                    1. **Maintien** en provinciale 3 pour l'équipe première
                    2. **Montée** en division 2 pour l'équipe B
                    3. **3 qualifications** aux interclubs provinciaux jeunes

                    Bienvenue à tous les nouveaux membres, et bonne saison à toutes et tous !
                    MD,
                'category' => NewsPostCategoryEnum::NEWS,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Compte-rendu de l\'assemblée générale de début de saison',
                'content' => <<<'MD'
                    L'**assemblée générale annuelle** s'est tenue le **15 septembre 2026** à la salle polyvalente du Centre Jean Demeester. **38 membres** étaient présents ou représentés.

                    ## Bilan de la saison 2025-2026

                    - Sportif : **montée en provinciale 3** ✅
                    - Financier : **excédent de 1 850 €**
                    - Membres : **87 membres actifs** (+12 vs l'an passé)

                    ## Décisions votées

                    | Décision | Résultat |
                    |---|---|
                    | Budget prévisionnel 22 000 € | Approuvé à l'unanimité |
                    | Acquisition 2 tables Tibhar | Approuvé (34 pour, 4 abstentions) |
                    | Réfection vestiaires | Reporté à 2027-2028 |
                    | Cotisation inchangée | Approuvé à l'unanimité |

                    ## Composition du comité 2026-2027

                    - **Président** : Olivier Pauwels (reconduit)
                    - **Secrétaire** : Manon Patigny (reconduite)
                    - **Trésorier** : Gilles Herpigny (reconduit)
                    - **Responsable jeunes** : Simon Beaumont (nouveau)
                    - **Communication** : Julie Renard (nouvelle)

                    Prochaine réunion de comité : **12 octobre 2026**.
                    MD,
                'category' => NewsPostCategoryEnum::NEWS,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => false,
            ],
            [
                'title' => 'Nouveau partenariat avec l\'Optique Lemmens d\'Ottignies',
                'content' => <<<'MD'
                    Le CTT Ottignies-Blocry est heureux d'accueillir l'**Optique Lemmens** comme nouveau partenaire officiel pour la saison 2026-2027.

                    ## La réduction pour les membres

                    Dès **octobre 2026**, tous les membres en règle de cotisation bénéficient de :

                    - **20 % de réduction** sur les verres correcteurs
                    - **20 % de réduction** sur les montures de la collection standard
                    - Accès aux offres promotionnelles en avant-première

                    > **Mode d'emploi** : présentez votre carte de membre CTT à jour lors de votre visite en magasin.

                    ## En échange

                    Le logo Lemmens figurera sur nos **maillots d'entraînement** et sera visible sur notre site internet et nos communications officielles.

                    > "Nous voulions soutenir une association sportive locale active. Le CTT représente exactement les valeurs que nous défendons." — *Marion Lemmens, gérante*

                    **Optique Lemmens** — Rue des Combattants 14, Ottignies — Ouvert du mardi au samedi, 9h–18h.
                    MD,
                'category' => NewsPostCategoryEnum::PARTNERSHIP,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Portrait : Thomas Willems, la belle progression',
                'content' => <<<'MD'
                    À **17 ans**, **Thomas Willems** s'est imposé comme l'un des espoirs les plus sérieux du CTT Ottignies-Blocry. Portrait d'un joueur en plein envol.

                    ## Son parcours au CTT

                    Thomas a posé sa première raquette dans notre club à l'âge de **9 ans**, lors d'une journée portes ouvertes.

                    | Saison | Classement | Fait marquant |
                    |---|---|---|
                    | 2018-2019 | NC | Découverte du club |
                    | 2020-2021 | E6 | 1er match interclubs |
                    | 2022-2023 | E4 | Champion provincial U15 |
                    | 2025-2026 | E2 | Intégration équipe première |
                    | 2026-2027 | E0 | Objectif : classement D |

                    ## Son jeu

                    Thomas est un **attaquant pur**, adepte du coup droit en pivot. Son arme fatale : un **service long en revers** qui déconcerte tous ses adversaires.

                    > "Thomas a la mentalité du champion : il travaille deux fois plus que les autres et ne se plaint jamais." — *Arnaud Ghysens, entraîneur*

                    ## Ses objectifs cette saison

                    - Décrocher le classement **national D**
                    - Se qualifier pour les **championnats nationaux jeunes**
                    - Contribuer à la victoire de l'équipe première en provinciale 3

                    On croit en toi, Thomas !
                    MD,
                'category' => NewsPostCategoryEnum::PORTRAIT,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Stage de la Toussaint : 30 jeunes au programme',
                'content' => <<<'MD'
                    Du **26 au 28 octobre**, le CTT a organisé son **stage de la Toussaint**, qui a accueilli cette année **30 jeunes joueurs** entre 8 et 17 ans.

                    ## Organisation du stage

                    Les participants ont été répartis en **trois groupes selon le niveau** :

                    - **Groupe Découverte** (8–12 ans, NC à E6) — Simon Beaumont
                    - **Groupe Progression** (10–15 ans, E4 à E2) — Julie Delcroix
                    - **Groupe Compétition** (12–17 ans, E2 et +) — Arnaud Ghysens

                    ## Programme des 3 jours

                    | Jour | Matin | Après-midi |
                    |---|---|---|
                    | Lundi | Fondamentaux techniques | Jeu en situation |
                    | Mardi | Tactique et placement | Matchs analysés |
                    | Mercredi | Analyse vidéo | Mini-tournoi de clôture |

                    ## Le moment fort

                    La **séance d'analyse vidéo** de matchs professionnels ralentis à 25 % a particulièrement enthousiasmé les participants. Voir les gestes des pros permet de comprendre des détails invisibles à vitesse normale.

                    > "J'ai surtout travaillé mon revers, qui était mon point faible. Après 3 jours, je me sens beaucoup mieux." — *Léa, 13 ans*

                    Prochain stage : **vacances de Pâques 2027**. Inscriptions ouvertes en janvier.
                    MD,
                'category' => NewsPostCategoryEnum::TRAINING,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Solide entrée en matière pour nos équipes interclubs',
                'content' => <<<'MD'
                    Premier week-end de compétition et **toutes nos équipes ont répondu présent** ! Voici un tour d'horizon des résultats.

                    ## Équipe première (Provinciale 3)

                    **CTT Ottignies A 14 – PP Witterzee 4**

                    Un match maîtrisé de bout en bout. **Arnaud Ghysens** a été impérial (3 victoires en simple, aucun set concédé). La solidité du double Ghysens/Tilmans a fait la différence.

                    ## Équipe B (Provinciale 4)

                    **CTT Ottignies B 10 – CTT Hamme-Mille 10**

                    Un partage des points arraché dans les dernières rencontres. L'équipe B a montré du caractère en revenant de 4-8.

                    ## Vétérans

                    **CTT Ottignies Vétérans 16 – AS Beauchamp 2**

                    Une démonstration ! Pierre Vandenberghe et ses coéquipiers ont dominé dans tous les compartiments du jeu.

                    ## Classement provisoire (J1)

                    | Équipe | Pts |
                    |---|---|
                    | CTT Ottignies A | 2 |
                    | CTT Ottignies Vét. | 2 |
                    | CTT Ottignies B | 1 |

                    Prochain week-end de championnat dans **deux semaines**.
                    MD,
                'category' => NewsPostCategoryEnum::COMPETITION,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Journées portes ouvertes : le club fait salle comble',
                'content' => <<<'MD'
                    Les **journées portes ouvertes** organisées les **8 et 9 novembre** ont largement dépassé les espérances ! Retour sur un week-end exceptionnel.

                    ## Les chiffres clés

                    - **110+ visiteurs** sur deux journées
                    - **23 nouvelles inscriptions** (dont **15 jeunes**)
                    - **3 familles** inscrites en entier

                    ## Au programme

                    ### Samedi 8 novembre
                    - 10h–12h : Initiations enfants (6–12 ans)
                    - 14h–17h : Initiations adultes
                    - 17h30 : Démonstration par Thomas Willems et Arnaud Ghysens

                    ### Dimanche 9 novembre
                    - 9h–12h : Journée "famille" — 2 membres de la même famille = 1 inscription offerte
                    - 14h–16h : Conférence "Comment progresser rapidement au ping-pong ?"
                    - 16h : Verre de bienvenue

                    > "On a reçu plus de monde en deux jours qu'en toute une saison de portes ouvertes habituelles." — *Simon Beaumont, responsable jeunes*
                    MD,
                'category' => NewsPostCategoryEnum::EVENT,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Défaite courageuse face au CTT Limal-Wavre (8-16)',
                'content' => <<<'MD'
                    Notre équipe première s'est inclinée **8-16** à domicile face au **CTT Limal-Wavre**, l'une des équipes favorites du championnat provincial 3.

                    ## Analyse du match

                    ### Ce qui a fonctionné

                    - **Xavier Coenen** — 2 victoires en simple, dont une magnifique en 4 sets
                    - **Double Ghysens/Tilmans** — victoire arrachée 11-9 au 5e set
                    - L'état d'esprit : jamais l'équipe n'a baissé les bras

                    ### Les points à retravailler

                    - **Régularité sous pression** — 3 matchs perdus au 5e set alors qu'on menait
                    - **Service court côté revers** — exploité par leurs deux meilleurs joueurs

                    > "Le score est sévère mais on a appris beaucoup. Limal-Wavre est clairement candidat au titre." — *Olivier Tilmans, capitaine*

                    ## Classement après J4

                    | Équipe | Pts |
                    |---|---|
                    | CTT Limal-Wavre | 8 |
                    | **CTT Ottignies A** | **5** |
                    | PP Witterzee | 4 |

                    On reste dans le bon wagon. Prochain match dans **3 semaines**.
                    MD,
                'category' => NewsPostCategoryEnum::COMPETITION,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Tournoi de Noël 2026 : une édition mémorable',
                'content' => <<<'MD'
                    Le **Tournoi de Noël 2026** a battu tous les records de participation avec **64 joueurs** inscrits, soit le double de l'édition précédente !

                    ## Les résultats

                    | Catégorie | Vainqueur | Finaliste |
                    |---|---|---|
                    | Messieurs | Arnaud Ghysens | Thomas Willems |
                    | Dames | Emma Delforge | Sophie Marchal |
                    | Vétérans | Pierre Vandenberghe | Henri Collin |
                    | Jeunes –15 | Léa Fontaine | Romain Dubois |
                    | Poussins | Mathis Renard | Clara Dupont |

                    ## La finale messieurs : un duel entre maître et élève

                    La finale opposant **Arnaud Ghysens à Thomas Willems** a été le moment fort de la journée. Cinq sets, des échanges de grande qualité, un public en délire. Victoire d'Arnaud **3-2** (11-8, 8-11, 11-9, 6-11, 11-7).

                    > "Battre mon entraîneur aurait été la plus belle victoire de ma carrière... Pour l'instant !" — *Thomas Willems, magnanime dans la défaite*

                    ## En dehors des courts

                    - **Visite du Père Noël** pour les poussins
                    - **Tombola** : 1 200 € récoltés pour le renouvellement des filets
                    - **Buffet de Noël** préparé par 8 bénévoles

                    Rendez-vous en **décembre 2027** pour une édition encore plus festive !
                    MD,
                'category' => NewsPostCategoryEnum::EVENT,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Bilan financier du premier trimestre — réservé aux membres',
                'content' => <<<'MD'
                    À l'issue du premier trimestre 2026-2027, les finances du CTT Ottignies-Blocry sont **solides et bien orientées**.

                    ## Recettes (sept–déc 2026)

                    | Source | Montant |
                    |---|---|
                    | Cotisations membres | 5 640 € |
                    | Tournoi de Noël (inscriptions + tombola) | 1 760 € |
                    | Partenariat Optique Lemmens | 800 € |
                    | Locations de salle | 250 € |
                    | **Total recettes** | **8 450 €** |

                    ## Dépenses (sept–déc 2026)

                    | Poste | Montant |
                    |---|---|
                    | Location salle Demeester | 2 400 € |
                    | Matériel (balles, filets, etc.) | 840 € |
                    | Déplacements interclubs | 620 € |
                    | Assurances | 980 € |
                    | Communication et site web | 360 € |
                    | **Total dépenses** | **6 200 €** |

                    ## Situation nette

                    - **Excédent du trimestre** : 2 250 €
                    - **Réserve de trésorerie** : 14 300 €

                    ## Proposition du comité

                    Affecter **2 000 €** à l'achat de revêtements de raquettes destinés aux jeunes en formation (programme "Raquette pour tous"). **Vote lors de la prochaine réunion de comité — 10 février 2027.**
                    MD,
                'category' => NewsPostCategoryEnum::NEWS,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => false,
            ],
            [
                'title' => 'Présentation du nouveau bureau et des ambitions 2027',
                'content' => <<<'MD'
                    Suite aux élections de janvier, le bureau du CTT Ottignies-Blocry se renouvelle partiellement pour la seconde moitié de saison.

                    ## Composition du bureau 2027

                    - **Président** : Olivier Pauwels (reconduit)
                    - **Vice-président** : Thierry Regnier (reconduit)
                    - **Secrétaire** : Manon Patigny (reconduite)
                    - **Trésorier** : Gilles Herpigny (reconduit)
                    - **Responsable communication** : Julie Renard (nouvelle)
                    - **Responsable jeunes** : Simon Beaumont (reconduit)

                    ## Projets du second semestre

                    ### Court terme (jan–mars 2027)
                    - Refonte du site internet (en cours)
                    - Lancement du programme "Raquette pour tous"
                    - Organisation du tournoi de printemps (mars 2027)

                    ### Moyen terme (avr–juin 2027)
                    - Développement de la section féminine
                    - Partenariat avec Decathlon LLN (en négociation)
                    - Préparation de la saison 2027-2028

                    *Article à finaliser avant publication.*
                    MD,
                'category' => NewsPostCategoryEnum::NEWS,
                'status' => NewsPostStatusEnum::DRAFT,
                'is_public' => false,
            ],
            [
                'title' => 'Un nouveau créneau d\'entraînement le vendredi soir',
                'content' => <<<'MD'
                    Bonne nouvelle pour nos compétiteurs : le club ouvre un **nouveau créneau d'entraînement le vendredi soir**, en réponse aux demandes répétées de nos joueurs.

                    ## Les infos pratiques

                    | | |
                    |---|---|
                    | **Jour** | Vendredi |
                    | **Horaire** | 20h00 – 22h30 |
                    | **Lieu** | Salle Demeester -1 |
                    | **Encadrement** | Arnaud Ghysens |
                    | **Niveau requis** | E2 et au-dessus |
                    | **Capacité** | 15 joueurs maximum |
                    | **Tarif** | Inclus dans la cotisation compétiteur |

                    ## Au programme de chaque séance

                    1. **Échauffement spécifique** (20 min) — placement et réflexes
                    2. **Travail technique ciblé** (45 min) — basé sur les points faibles identifiés
                    3. **Analyse vidéo** (20 min) — revue des matchs récents
                    4. **Matchs de simulation** (45 min) — conditions proches du match réel

                    **Inscription obligatoire** via l'application du club ou auprès d'Arnaud directement. Places limitées à 15.

                    Premier séance : **vendredi 16 janvier 2027**.
                    MD,
                'category' => NewsPostCategoryEnum::TRAINING,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Emma Delforge : cinq ans de dévouement au CTT',
                'content' => <<<'MD'
                    **Emma Delforge** fête cette année ses **5 ans au CTT Ottignies-Blocry**. Une belle occasion de revenir sur un parcours exemplaire, à la fois sportif et humain.

                    ## La progression sportive

                    Emma est arrivée à 14 ans avec un niveau débutant absolu. Cinq ans plus tard, la voilà classée **D6** et sur le point d'intégrer l'équipe dames en interclubs.

                    | Année | Classement |
                    |---|---|
                    | 2021 | NC |
                    | 2022 | E6 |
                    | 2023 | E4 |
                    | 2024 | E2 |
                    | 2025 | E0 |
                    | 2026 | D6 |

                    ## Une bénévole précieuse

                    Mais Emma, c'est bien plus que des chiffres. Elle est aussi :

                    - **Co-organisatrice** du Tournoi de Noël depuis 3 ans
                    - **Présente à chaque journée portes ouvertes** pour accueillir les nouveaux
                    - **Marraine** de deux jeunes joueuses en formation
                    - **Photographe officieuse** des événements du club

                    > "Emma incarne parfaitement ce qu'on cherche au CTT : progression, engagement, bonne humeur. Elle est un exemple pour tous." — *Manon Patigny, secrétaire*

                    Cinq ans, et visiblement ce n'est qu'un début. Merci Emma !
                    MD,
                'category' => NewsPostCategoryEnum::PORTRAIT,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Soirée quiz inter-générations : un vrai succès !',
                'content' => <<<'MD'
                    La **soirée quiz inter-générations** du 14 février a réuni une trentaine de membres dans une ambiance festive et compétitive.

                    ## Format de la soirée

                    - **10 équipes de 3–4 joueurs**, composées de joueurs de générations différentes
                    - **6 manches thématiques** de 5 questions chacune
                    - 1 manche bonus sur table (mini-tournoi)
                    - Remise des prix et buffet de clôture

                    ## Les thèmes du quiz

                    1. Histoire du tennis de table (règles, champions, anecdotes)
                    2. Histoire du CTT Ottignies-Blocry
                    3. Actualités sportives belges
                    4. Culture générale
                    5. Géographie du Brabant wallon
                    6. **Manche surprise** : reconnaître les membres du club sur des photos d'archives !

                    ## Le classement final

                    🥇 **Les Vétérans Invincibles** (Pierre V., Henri C., Josette M.) — 87 pts
                    🥈 Les Challengers (Thomas W., Emma D., Léa F.) — 82 pts
                    🥉 Team Comité (Olivier P., Manon P., Gilles H.) — 79 pts

                    > "On pensait que les jeunes gagneraient facilement. Ils ont sous-estimé nos années d'expérience !" — *Pierre Vandenberghe, vainqueur*

                    Le buffet, préparé par **8 bénévoles**, a clôturé une soirée mémorable.
                    MD,
                'category' => NewsPostCategoryEnum::EVENT,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Victoire nette face au TT Perwez (16-6)',
                'content' => <<<'MD'
                    L'équipe première a livré **l'une de ses meilleures performances de la saison** en dominant TT Perwez **16-6** lors de la 9e journée.

                    ## Le match en chiffres

                    | | CTT Ottignies | TT Perwez |
                    |---|---|---|
                    | **Score final** | **16** | **6** |
                    | Victoires en simple | 12 | 3 |
                    | Victoires en double | 2 | 1 |

                    ## Les performances individuelles

                    - **Dariusz Sekula** — 3V/0D, dont une victoire contre leur n°1 en 3 sets secs ⭐
                    - **Arnaud Ghysens** — 3V/0D, dominateur en coup droit
                    - **Olivier Tilmans** — 2V/1D, solide capitaine
                    - **Double Ghysens/Tilmans** — 2V/0D

                    > "C'est notre meilleur match de la saison. L'équipe était connectée à 100 %." — *Olivier Tilmans, capitaine*

                    ## Impact sur le classement

                    | Rang | Équipe | Pts | J |
                    |---|---|---|---|
                    | 1 | CTT Limal-Wavre | 18 | 10 |
                    | **2** | **CTT Ottignies A** | **16** | **9** |
                    | 3 | RPAC Clabecq | 14 | 10 |

                    Deux points d'avance sur notre dauphin avec un match de moins joué. La course au titre est relancée !
                    MD,
                'category' => NewsPostCategoryEnum::COMPETITION,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Stage de Pâques avec Julien Sauvé, entraîneur national',
                'content' => <<<'MD'
                    Les vacances de Pâques ont été marquées par un **stage d'exception** avec **Julien Sauvé**, entraîneur fédéral et ancien joueur de ligue nationale.

                    ## Qui est Julien Sauvé ?

                    - **Ancien joueur** de Division Nationale 2 (10 saisons)
                    - **Entraîneur fédéral** BE3 depuis 2019
                    - Spécialisé dans la **technique de pointe** et la **préparation mentale**
                    - Coach de plusieurs joueurs aujourd'hui classés en division nationale

                    ## Le programme du stage (2 jours)

                    ### Jour 1 : Technique et physique
                    - Matin : Travail du jeu court — 15 exercices progressifs
                    - Après-midi : Technique de pointe et engagement au niveau supérieur

                    ### Jour 2 : Tactique et mental
                    - Matin : Construction du point — schémas tactiques par profil de joueur
                    - Après-midi : Simulation de match avec debriefing immédiat

                    > "Julien nous a montré des exercices qu'on ne pratique jamais en entraînement habituel. Ma façon d'aborder un match a complètement changé." — *Sébastien Vandevyver*

                    > "Le travail sur le mental m'a ouvert les yeux. Je ne savais pas à quel point ma tête me coûtait des points." — *Thomas Willems*

                    Le stage s'est conclu par un **match exhibition** très applaudi. On espère revoir Julien dès la saison prochaine !
                    MD,
                'category' => NewsPostCategoryEnum::TRAINING,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Le CTT rejoint le réseau Sport & Santé Brabant wallon',
                'content' => <<<'MD'
                    Le CTT Ottignies-Blocry est officiellement membre du **réseau Sport & Santé Brabant wallon**, une initiative provinciale visant à rendre l'activité physique accessible au plus grand nombre.

                    ## Ce que ça implique concrètement

                    À partir de **septembre 2027**, le CTT proposera des séances adaptées :

                    | Séance | Public | Fréquence |
                    |---|---|---|
                    | "Ping & Santé" | Seniors 65+ | 1×/semaine |
                    | "Ping & Inclusion" | Personnes en réinsertion | 1×/semaine |
                    | "Ping Adapté" | Personnes à mobilité réduite | 1×/quinzaine |

                    Ces séances sont **co-financées par la Province du Brabant wallon**.

                    > "C'est une belle façon de rendre le tennis de table accessible au plus grand nombre, tout en renforçant l'ancrage social de notre club." — *Manon Patigny, secrétaire*

                    ## Bénévoles recherchés

                    Nous cherchons **3 bénévoles** pour encadrer ces séances dès la prochaine saison. **Formation prise en charge** par le réseau. Contactez-nous dès maintenant !
                    MD,
                'category' => NewsPostCategoryEnum::PARTNERSHIP,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Montée historique : le CTT accède à la provinciale 2 !',
                'content' => <<<'MD'
                    C'est **officiel** : notre équipe première est promue en provinciale 2 ! Une montée historique qui n'était même pas l'objectif affiché en début de saison.

                    ## La saison en chiffres

                    | | Résultat |
                    |---|---|
                    | Victoires | **13** |
                    | Nuls | 2 |
                    | Défaites | 1 |
                    | Points | **28 / 32** |
                    | Position finale | **1er** |

                    ## Le match de la confirmation (J14)

                    La montée a été validée lors de l'avant-dernière journée avec une victoire **16-4** face à CTT Tubize. La salle du Demeester a vibré comme rarement.

                    > "C'est l'aboutissement de trois ans de travail collectif. On ne réalise pas encore." — *Olivier Tilmans, capitaine*

                    > "Ces joueurs méritent tout ce qu'il leur arrive. Leur investissement est exemplaire." — *Michel Dumont, entraîneur*

                    ## La suite

                    Le comité se réunit en juin pour préparer **l'arrivée en provinciale 2** : renforcement de l'effectif, planning des entraînements, implications budgétaires. Toutes les décisions seront présentées lors de l'assemblée générale de fin de saison.

                    **Félicitations à toute l'équipe !** 🏆
                    MD,
                'category' => NewsPostCategoryEnum::COMPETITION,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'is_public' => true,
            ],
            [
                'title' => 'Projet de partenariat avec Decathlon Louvain-la-Neuve',
                'content' => <<<'MD'
                    Le club est en discussion avancée avec le **Decathlon de Louvain-la-Neuve** pour établir un partenariat matériel sur la saison 2027-2028.

                    ## Ce que prévoit le projet

                    - **20 % de réduction** sur les raquettes, balles et équipements pour tous les membres
                    - Mise à disposition de **matériel de démonstration** lors des journées portes ouvertes
                    - Présence du logo Decathlon sur nos supports de communication

                    ## Prochaines étapes

                    - Réunion de finalisation prévue : **mi-juin 2027**
                    - Signature du contrat : avant l'AG de fin de saison
                    - Entrée en vigueur : **septembre 2027**

                    *Article à mettre à jour après signature du contrat.*
                    MD,
                'category' => NewsPostCategoryEnum::PARTNERSHIP,
                'status' => NewsPostStatusEnum::DRAFT,
                'is_public' => false,
            ],
            [
                'title' => 'Convocation à l\'assemblée générale de fin de saison',
                'content' => <<<'MD'
                    Les membres du CTT Ottignies-Blocry sont convoqués à l'**assemblée générale de fin de saison**.

                    ## Informations pratiques

                    | | |
                    |---|---|
                    | **Date** | Jeudi 12 juin 2027 |
                    | **Heure** | 19h30 |
                    | **Lieu** | Salle polyvalente, Centre Sportif Jean Demeester |

                    ## Ordre du jour

                    1. Bilan sportif de la saison 2026-2027
                    2. Rapport financier du trésorier
                    3. **Montée en provinciale 2** — implications budgétaires et organisationnelles
                    4. Renouvellement partiel du comité
                    5. Présentation du partenariat Decathlon LLN
                    6. Questions diverses

                    La **présence de chaque membre est vivement souhaitée**. Un quorum de 30 % des membres est requis pour la validité des votes.

                    Un **verre de l'amitié** clôturera la soirée.

                    *Répondez à l'invitation envoyée par e-mail avant le 5 juin.*
                    MD,
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
            6 => 1,
            7 => 2,
            8 => 5,
            9 => 8,
            10 => 10,
            11 => 12,
            12 => 8,
            13 => 6,
            14 => 10,
            15 => 12,
            16 => 10,
            17 => 8,
            18 => 6,
            19 => 8,
            20 => 10,
            21 => 6,
            22 => 3,
            23 => 1,
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
