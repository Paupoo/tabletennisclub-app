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
- **Équipement confié** (`key_rings`, caisses détenues) — un objet remis, qui
  se rend. Se trace, ne donne rien.

Deux rôles forment le socle : `administrateur` détient tout, `comite` donne un
accès en **lecture** au back-office. Tout le reste est une délégation.

Les règles relationnelles — « capitaine de **cette** équipe », « **mon** profil » —
ne sont pas exprimables par une permission : elles vivent dans les policies, qui
combinent le droit et l'appartenance.

---

## Les quatorze domaines

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

Paiements polymorphes (affiliation, inscription tournoi, repas de réunion),
comptes bancaires du club (courant, épargne), transactions bancaires et leur
pointage, imports des extraits CBC (solde et numéro d'extrait gardés par ligne,
virements entre comptes du club marqués internes), caisses physiques, amendes
du comité provincial — transmises au membre, qui les paie directement au comité.
Les notes de frais (`app/Domains/ClubAdmin/ExpenseReports`) y aboutissent comme
des remboursements ordinaires.

**Trois délégations distinctes** : `tresorerie` (pointer, importer, rembourser),
`caisse` (détenir et équilibrer une caisse) et `amendes`. Détenir la caisse du bar
n'implique pas de toucher aux comptes — c'est ce découpage qui permet de confier la
caisse à quelqu'un hors comité. `notes-de-frais` est le relais du trésorier pour
décider des notes : il n'exporte ni n'archive.

### Pièces justificatives
`app/Domains/ClubAdmin/SupportingDocuments`

L'argent que le site ne voit pas — location de salle, matériel, subsides,
sponsors, frais bancaires — et la pièce qui le prouve (`SupportingDocument`,
référence `P-<année>-<id>`, au moins un fichier). La catégorie porte le sens
(dépense ou recette) ; les dépenses partagent l'énumération des notes de frais.
Une pièce est liée, sans montant, à des lignes de banque et à des mouvements de
caisse : non liée, elle est **à régler** (dette ou créance) ; liée, elle est
**réglée**, et la ligne est **justifiée** — troisième façon de clôturer une
transaction, à côté du pointage et de l'abandon du reliquat. Une ligne affectée à
des paiements du site ne reçoit jamais de pièce.

**Permission** `supporting_documents.manage` (délégation `tresorerie`). La
délégation `caisse` classe les tickets payés avec sa caisse et les lie à des
mouvements de caisse, jamais à une ligne de banque. Lecture : `transactions.view`.

### Rapport financier
`app/Domains/ClubAdmin/Finance`

Un exercice comptable (`FiscalYear`, mois de début réglé sur le club, janvier
par défaut) vu en un écran, en comptabilité de caisse : recettes et dépenses par
poste — celles du site dérivées du `payable` du paiement, les autres des pièces —,
huit indicateurs comparés à l'exercice précédent, graphiques SVG rendus côté
serveur. Un seul calcul (`FinancialReport`, `FinancialPosition`) sert l'écran et
l'export. L'export (`FinancialExport`, PDF ou ZIP, préparé en file d'attente)
reprend le rapport, le journal de tous les mouvements et les pièces ; télécharger
le ZIP d'un exercice archive ses notes de frais payées.

**Permission** `financial_report.view` : `comite`, `verification-comptes`,
`tresorerie`. N'écrit rien, sauf l'archivage des notes de frais, réservé à qui
peut aussi les traiter ou les rembourser.

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

Catalogue, stock, commandes, feuille de caisse, courses et inventaires — seule
porte qui corrige le stock. Module semi-détaché, avec son propre gabarit. **Délégation** `bar`, avec des droits plus fins pour le catalogue
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
  inscriptions aux tournois, repas de réunion (plus les amendes émises avant le
  paiement direct au comité provincial). Toute nouvelle chose
  facturable implémente `App\Contracts\PayableInterface` — et doit trouver son
  poste de recette dans `FinancialReport`, qui range l'argent du site d'après le
  type du `payable`.
- **`EventPost`** (`morphTo eventable`) est le pivot du calendrier public.
- **`Season`** est la colonne vertébrale temporelle : abonnements, entraînements,
  équipes et rencontres s'y rattachent.
