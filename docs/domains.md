# Domaines de l'application

Carte du périmètre fonctionnel : ce que fait l'application, découpé comme le code
l'est (`app/Domains/*`), et qui peut quoi dans chaque domaine.

Ce fichier est écrit à la main et décrit des choses stables. La matrice exacte des
droits, elle, est **générée** — voir [permissions.md](permissions.md), qu'un test
garde synchronisée avec le code.

## Comment lire les droits

Trois familles cohabitent, et **une seule décide** :

- **Titre statutaire** (`users.committee_role`) — président, secrétaire, trésorier,
  vice-président. Un mandat d'assemblée générale. Il s'affiche sur le profil et
  dans la liste du comité ; il ne donne accès à rien.
- **Délégation** (rôle Spatie) — une charge opérationnelle. Cumulable, et
  attribuable à n'importe quel membre, qu'il siège au comité ou non. C'est elle
  qui décide.
- **Équipement confié** (`users.has_key`, caisses détenues) — un objet remis, qui
  se rend. Se trace, ne donne rien.

Deux rôles forment le socle : `administrateur` détient tout, `comite` donne un
accès en **lecture** au back-office. Tout le reste est une délégation.

Les règles relationnelles — « capitaine de **cette** équipe », « **mon** profil » —
ne sont pas exprimables par une permission : elles vivent dans les policies, qui
combinent le droit et l'appartenance.

---

## Les douze domaines

### Membres et identité
`app/Domains/ClubAdmin/Users`

Le membre, son tuteur légal, son groupe familial. Fiche membre, annuaire,
invitations, documents (certificat médical, autorisation parentale), anonymisation
RGPD. C'est le domaine le plus transverse : `User` touche presque tous les autres.

**Délégation** `membres`. Archiver et anonymiser restent à l'administrateur —
l'anonymisation est irréversible.

### Abonnements et affiliations
`app/Domains/ClubAdmin/Subscriptions`

L'affiliation d'un membre à une saison, sa machine à états (en attente → confirmée
→ payée → remboursée / annulée), les inscriptions aux packs d'entraînement et aux
événements payants.

« Membre actif », « compétiteur », « affilié » sont des **états d'abonnement**, pas
des rôles : ils changent au fil de la saison et sont des scopes Eloquent.

**Délégation** `membres`.

### Trésorerie
`app/Domains/ClubAdmin/Payment`, `app/Domains/ClubAdmin/Fines`

Paiements polymorphes (affiliation, amende, inscription tournoi, repas de réunion),
transactions bancaires et leur pointage, imports CODA, caisses physiques, amendes
disciplinaires.

**Trois délégations distinctes** : `tresorerie` (pointer, importer, rembourser),
`caisse` (détenir et équilibrer une caisse) et `amendes`. Détenir la caisse du bar
n'implique pas de toucher aux comptes — c'est ce découpage qui permet de confier la
caisse à quelqu'un hors comité.

### Installations
`app/Domains/ClubAdmin/Club`

Salles et tables, avec leur état. **Délégation** `installations`.

### Interclubs
`app/Domains/Competitions/Interclub`

Saisons, clubs adverses, divisions, équipes, calendrier des rencontres,
disponibilités, sélections, résultats.

Le domaine où la distinction délégation / relation compte le plus : un **capitaine**
est une relation (`teams.captain_id`), jamais une délégation. Il compose et encode
pour ses équipes sans rien détenir, tandis que `selections` et `interclubs`
autorisent à l'échelle du club. Deux gates (`access-selections`, `access-results`)
combinent les deux, et chaque écran restreint ensuite aux équipes capitainées.

### Tournois
`app/Domains/Competitions/Tournament`

Tournois internes, inscriptions et listes d'attente, poules, matches, sets,
attribution des tables, live center. **Délégation** `tournois`.

### Entraînements
`app/Domains/Trainings`

Packs d'entraînement vendus à la saison, séances, présences, et un tableau de
planification pour construire l'offre.

**Deux délégations** : `coach` anime les séances, `entrainements` construit l'offre
et la planification. Un coach n'hérite pas des écrans de planification.

### Réunions
`app/Domains/Meetings`

Réunions de comité et assemblées générales : sondage de dates, convocations,
ordre du jour, quorum, repas, procès-verbaux, points de suivi.

**Délégation** `reunions`. Le comité lit, la délégation gère.

### Bar
`app/Domains/Bar`

Catalogue, stock, commandes, feuille de caisse. Module semi-détaché, avec son
propre gabarit. **Délégation** `bar`, avec des droits plus fins pour le catalogue
et la feuille de caisse.

### Contenu et site public
`app/Domains/ClubPosts`

Articles, et un calendrier public polymorphe qui agrège tournois, packs
d'entraînement, réunions et événements autonomes.

**Délégation** `site-web`. Un domaine éteint disparaît aussi du calendrier public :
annoncer un événement dont la page renvoie 404 serait pire que ne pas l'annoncer.

### Contacts
`app/Domains/ClubAdmin/Contact`

Demandes entrantes du formulaire public, triage, modèles de réponse, quarantaine
anti-spam, transformation d'un contact en membre.

**Délégation** `contacts`, distincte de `site-web`.

### Plateforme
`app/Domains/Shared`

Réglages applicatifs, journal d'audit, supervision de la file d'attente, feature
flags. **Délégation** `supervision`.

---

## Ce qui peut être éteint

Un drapeau par domaine, piloté par `.env`. Un domaine éteint disparaît des
**quatre** surfaces à la fois — routes (404, pas 403), navigation, tâches
planifiées et calendrier public. Une extinction partielle serait pire que pas
d'extinction du tout : un membre cliquerait sur un lien mort, ou continuerait de
recevoir des mails d'un domaine invisible.

Membres, saisons et installations n'ont volontairement pas de drapeau : les
éteindre ne masquerait pas une fonctionnalité, cela casserait l'application.

La liste des clés est dans [permissions.md](permissions.md#domaines-extinguibles).

---

## Points d'intégration transverses

- **`Payment`** (`morphTo payable`) est le pivot de la trésorerie : affiliations,
  amendes, inscriptions aux tournois, repas de réunion. Toute nouvelle chose
  facturable implémente `App\Contracts\PayableInterface`.
- **`EventPost`** (`morphTo eventable`) est le pivot du calendrier public.
- **`Season`** est la colonne vertébrale temporelle : abonnements, entraînements,
  équipes et rencontres s'y rattachent.
