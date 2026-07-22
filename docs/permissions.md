# Délégations et permissions

> Fichier généré par `php artisan docs:permissions`. Ne pas modifier à la main :
> la source est `App\Domains\Shared\Enums\Role`, et `PermissionsDocTest` échoue
> si les deux divergent.

Trois familles cohabitent, et une seule décide :

| Famille | Nature | Stockage | Rôle |
|---|---|---|---|
| Titre statutaire | mandat AG, un par personne | `users.committee_role` | **s'affiche** |
| Délégation | charge opérationnelle, cumulable, attribuable à n'importe qui | rôles Spatie | **décide** |
| Équipement confié | objet remis, se rend | `users.has_key`, caisses détenues | **se trace** |

---

## Socle

### Administrateur — `administrateur`

Accès sans restriction à toute l'application.

Détient les 58 permissions. Accordées explicitement plutôt que
par un court-circuit `Gate::before`, car certaines policies encodent des règles qui
doivent survivre à un administrateur — il ne peut toujours pas supprimer son propre
compte.

### Membre du comité — `comite`

Accès de base au back-office : consulter les données du club sans les gérer.

- `users.view`
- `subscriptions.view`
- `payments.view`
- `contacts.view`
- `news_posts.view`
- `interclubs.view`
- `tournaments.view`
- `trainings.view`
- `meetings.view`
- `seasons.view`

---

## Délégations

Chacune peut être confiée à n'importe quel membre, qu'il siège au comité ou non.

### Bar — `bar`

Gérer le bar : produits, stock, commandes et feuille de caisse.

- `bar.access`
- `bar.products.manage`
- `bar.orders.manage`
- `bar.cash_sheet.send`

### Caisse — `caisse`

Détenir la caisse, l'équilibrer et enregistrer les mouvements.

- `cash_register.view`
- `cash_register.manage`
- `cash_register.entry.create`
- `cash_register.holder.change`

### Coach — `coach`

Animer les entraînements et encoder les présences.

- `trainings.view`
- `coach_area.access`

### Contacts — `contacts`

Traiter les demandes entrantes, les modèles de réponse et les spams.

- `contacts.view`
- `contacts.manage`
- `spams.manage`

### Installations — `installations`

Gérer les salles, les tables et le matériel confié.

- `rooms.manage`
- `tables.manage`
- `equipment.holder.update`

### Amendes — `amendes`

Infliger et annuler des amendes disciplinaires.

- `fines.view`
- `fines.issue`
- `fines.cancel`

### Interclubs — `interclubs`

Gérer les équipes, les divisions, les clubs adverses, les matches et les résultats.

- `interclubs.view`
- `interclubs.manage`
- `teams.manage`
- `leagues.manage`
- `clubs.manage`
- `results.manage`
- `selections.manage`

### Réunions — `reunions`

Convoquer les réunions, rédiger et publier les PV.

- `meetings.view`
- `meetings.manage`
- `meetings.minutes.manage`

### Membres — `membres`

Créer et modifier les membres, gérer les affiliations et les inscriptions.

- `users.view`
- `users.create`
- `users.update`
- `users.invite`
- `subscriptions.view`
- `subscriptions.manage`

### Saisons — `saisons`

Ouvrir, clôturer et préparer les saisons.

- `seasons.view`
- `seasons.manage`

### Sélections — `selections`

Composer les sélections (un capitaine, uniquement pour ses équipes).

- `interclubs.view`
- `selections.manage`

### Supervision technique — `supervision`

Consulter le journal d'audit, surveiller la file d'attente, modifier les réglages du club.

- `audit_log.view`
- `queue.view`
- `queue.manage`
- `club.update`

### Tournois — `tournois`

Organiser les tournois et animer le live center.

- `tournaments.view`
- `tournaments.manage`
- `tournaments.live.manage`

### Offre d'entraînement — `entrainements`

Construire l'offre d'entraînement, les packs et la planification de la saison.

- `trainings.view`
- `trainings.manage`
- `training_plans.manage`

### Trésorerie — `tresorerie`

Pointer les paiements, importer les extraits bancaires, gérer les remboursements.

- `payments.view`
- `payments.reconcile`
- `payments.refund`
- `payments.remind`
- `transactions.view`
- `transactions.import`
- `transactions.delete`

### Site web — `site-web`

Rédiger les articles et publier les événements publics.

- `news_posts.view`
- `news_posts.manage`
- `event_posts.manage`

---

## Délégations suggérées par titre

Pré-cochées à la nomination, et modifiables : un trésorier qui ne tient pas la caisse
est une situation légitime.

| Titre | Délégations suggérées |
|---|---|
| Administrateur | `supervision` |
| Président | `membres`, `contacts`, `reunions`, `saisons`, `supervision` |
| Secrétaire | `membres`, `contacts`, `reunions`, `site-web` |
| Trésorier | `tresorerie`, `caisse`, `amendes` |
| Vice-Président | `membres`, `contacts`, `reunions` |

---

## Permissions détenues par le seul administrateur

Aucune délégation ne les porte : elles ne peuvent pas être confiées.

- `users.anonymize`
- `users.delete`

---

## Domaines extinguibles

Un drapeau par domaine (`config/features.php`, piloté par `.env`). Éteindre un domaine
le retire des quatre surfaces à la fois : routes (404), navigation, tâches planifiées et
calendrier public.

| Domaine | Clé `.env` |
|---|---|
| Bar | `FEATURE_BAR` |
| Caisse | `FEATURE_CASH_REGISTER` |
| Contacts | `FEATURE_CONTACTS` |
| Centre d'aide | `FEATURE_HELP_CENTRE` |
| Interclubs | `FEATURE_INTERCLUBS` |
| Réunions | `FEATURE_MEETINGS` |
| Supervision technique | `FEATURE_SUPERVISION` |
| Tournois | `FEATURE_TOURNAMENTS` |
| Planification des entraînements | `FEATURE_TRAINING_PLANNING` |
| Entraînements | `FEATURE_TRAININGS` |
| Trésorerie | `FEATURE_TREASURY` |
| Site web | `FEATURE_WEBSITE` |

