# Audit de pré-merge — branche `develop` → `main`

**Projet** : Table Tennis Club (CTT Ottignies-Blocry)
**Date** : 1er août 2026
**Périmètre** : 291 commits, 997 fichiers, +76 106 / −25 344 lignes, 0 commit de retard sur `main`
**Auteur de l'audit** : analyse statique + exécution de la suite de tests. Aucune modification de code.

---

## Suivi — mis à jour le 1er août 2026 (soir)

Les constats ci-dessous sont conservés tels qu'ils étaient au moment de l'audit. Ce tableau dit ce qu'il en reste, vérifié dans le code et non dans le document.

### Traité

| Constat | Où c'est réglé |
|---|---|
| 🔴 1 — Route `subscribe` sans autorisation | `routes/web.php:407-411` — l'action autorise l'appelant contre le membre inscrit |
| 🔴 2 — XSS markdown | `app/Support/Markdown.php` — `safe()` centralise `html_input: escape` + `allow_unsafe_links: false` |
| 🔴 3 — `TrustHosts` / `TrustProxies` | `bootstrap/app.php:60` — `trustHosts(subdomains: true)` ; les proxies restent volontairement non approuvés (Apache en direct), et le commentaire le justifie |
| 🟠 Absence totale de CI | `.github/workflows/ci.yml` — 4 jobs : style+PHPStan, Unit/Feature/Architecture, Browser, audit de dépendances |
| 🟠 Aucun en-tête de sécurité | `app/Http/Middleware/SecurityHeaders.php` |
| 🟠 Squelette Laravel ≤ 10 | Migré (PR #72) — les deux `Kernel.php` ont disparu au profit de `Application::configure()` |
| 🟠 Contrôleurs et routes legacy | Les 4 contrôleurs `// TODO` et leurs routes supprimés |
| 🟠 Mails synchrones | Les 14 mailables portent `ShouldQueue` ; `StoreContactAction` passe par `->queue()`. Verrouillé par `tests/Architecture/QueuedMailablesTest.php` |
| 🟠 `withoutOverlapping()` | Les 6 tâches, dans `routes/console.php` |
| 🟠 Index manquants | `database/migrations/2026_08_01_095449_add_filtering_indexes_to_hot_tables.php` |
| 🟡 PHPStan inerte | `phpstan-baseline.neon` gèle l'existant, et l'analyse est bloquante en CI |
| 🟡 Throttle mots de passe | `throttle:6,1` sur les 4 routes de `routes/auth.php` |
| 🟡 Doublon `CashSheetService` | Un seul subsiste, dans `app/Domains/Bar/Services/` |
| 🟢 Mails `{!! $customMessage !!}` | Commit `6ca545d9` — échappement dans `CustomEmail::processMessage()`, en amont du HTML légitime ajouté par `nl2br()` et `linkifyUrls()` |
| 🟢 12 Mo d'images / `_ide_helper` / `AGENTS.md` | Supprimées / détraqué de l'index / devenu un lien symbolique vers `CLAUDE.md` |
| `.env.example`, CORS | `APP_DEBUG=false`, `QUEUE_CONNECTION=database`, `SESSION_SECURE_COOKIE` documenté, origines CORS ramenées à `APP_URL` |
| 30 skipped / 7 todos | Zéro aujourd'hui |

### Trouvé après coup, hors audit

| Constat | Où c'est réglé |
|---|---|
| `{!! $name !!}` dans `compact-event-preview.blade.php` — les appelants `calendar` et `rooms/show` lui passent la valeur brute via `:name`, donc echo non échappé réel | Commit `3bb75974` |

Deux précisions que l'audit n'avait pas relevées et qui comptent pour la suite :

- Le rendu markdown des mails passe par `Illuminate\Mail\Markdown::parse()`, appelé depuis `resources/views/vendor/mail/html/layout.blade.php:42` **sans** `html_input` — donc sur le défaut `allow` de CommonMark. Le HTML brut d'un corps de mail sortait intact, vérifié en rendant un mailable porteur d'un `<script>`.
- Le corps de ces mails n'est pas seulement « rédigé par un admin » : les variables `{{ $contact->* }}` y interpolent des données saisies par un **visiteur anonyme** du formulaire public.

### Reste ouvert

| Constat | État |
|---|---|
| 🟠 **Docker** | Toujours inexistant. Aucun Dockerfile ni compose dans le dépôt ; la configuration Synology reste hors versionnement. Estimation inchangée : ~4 h |
| **Le merge lui-même** | `develop` est à **317 commits d'avance et 0 de retard** sur `main`. Toute la checklist « avant de merger » est satisfaite, la CI est verte |
| 🟡 Fichiers > 900 lignes | **Aggravé** : `registrations.php` est passé de 1266 à 1780 lignes depuis l'audit, sa blade à 1356. À cadrer comme un chantier, pas comme un quick win |

---

## Résumé exécutif

Cette branche est une release de saison entière, pas une pull request. Elle apporte la refonte du système de rôles et permissions (spatie/laravel-permission), la trésorerie avec rapprochement bancaire, le planning d'entraînements, les réunions de comité, et 143 nouveaux fichiers de test.

**Le fond est sain.** 3013 tests passent sans échec, aucune vulnérabilité de dépendance, aucun secret committé, et le cœur du travail — l'autorisation — est fait avec sérieux : gating au niveau route *et* au niveau action, matrice de permissions versionnée, seeder idempotent qui élague, et des suites de tests écrites exprès pour verrouiller les régressions d'autorisation.

**Trois défauts exploitables restent présents**, aucun couvert par un test, chacun corrigeable en moins de quinze minutes. Et deux absences structurelles pèsent lourd : **il n'existe aucun fichier Docker et aucun workflow de CI dans ce dépôt.**

### Ce qui a été mesuré

| Vérification | Résultat |
|---|---|
| Suite Unit + Feature + Architecture | **3013 passés, 0 échec**, 30 skipped, 7 todos — 128 s en `--parallel` |
| Suite Browser | **non exécutée** (environnement occupé par un run externe) |
| `composer audit` | aucune vulnérabilité |
| `npm audit --omit=dev` | 0 vulnérabilité |
| PHPStan niveau 5 | **164 erreurs**, sans baseline, **jamais exécuté par `composer test`** |
| Secrets committés | aucun — `.env` et `.env.testing` non suivis |
| `dd()` / `dump()` / `console.log` oubliés | aucun |
| Position vs `main` | 291 commits d'avance, 0 de retard (fast-forward propre) |

### Répartition du diff

| Zone | Ajoutés | Supprimés | Modifiés |
|---|---|---|---|
| `app/` | 86 | 15 | 205 |
| `tests/` | 143 | 2 | 97 |
| `database/migrations/` | ~57 nouvelles | — | ~10 |

---

## Score global : **72 / 100**

| Domaine | Note | Commentaire |
|---|---|---|
| Sécurité applicative | 16/20 | Autorisation excellente ; 3 défauts de configuration nets |
| Architecture | 13/20 | Domaines propres, mais squelette Laravel 10 et code mort |
| Base de données | 14/20 | Clés étrangères irréprochables, index insuffisants |
| Backend | 15/20 | Bon code récent ; mails synchrones, PHPStan inerte |
| Frontend | 16/20 | Surface réduite, propre |
| Docker | 0/10 | **Inexistant** |
| CI/CD | 0/10 | **Inexistant** |
| Tests | 18/20 | Le point fort — 3013 verts, suites de sécurité dédiées |
| Documentation | 9/10 | `DEPLOYMENT.md` et `permissions.md` remarquables |

Le score est tiré vers le bas par deux absences totales plutôt que par la qualité du code, qui est nettement au-dessus de ce que suggère la moyenne.

---

## Comprendre l'application

Application Laravel 13 / PHP 8.5 pour un club de tennis de table belge. Elle combine **un site public** (accueil, actualités, résultats interclubs, événements, formulaire de contact) et **un back-office** couvrant la vie du club : membres et affiliations, trésorerie (paiements, rapprochement bancaire, amendes), interclubs (équipes, divisions, sélections par les capitaines, résultats), entraînements (packs, planning de saison, listes d'attente), tournois avec « live center », réunions de comité avec PV, bar et caisse.

**Le front n'est pas en Vue** : Livewire 4 + Alpine 3 + Tailwind 4 + Mary UI. Les pages sont des composants Livewire mono-fichier (`resources/views/pages/**/⚡nom/nom.php` + `.blade.php`). Le JS propre au projet est minuscule (~67 lignes de bootstrap + une douzaine de petits modules : carte Leaflet, cropper d'avatar, filtres, thème).

Le code métier est organisé en `app/Domains/*` (Bar, ClubAdmin, Competitions, Meetings, Trainings, ClubPosts, Shared) avec Services, Actions, Policies et Enums, plus des contrôleurs HTTP résiduels hérités de l'ancienne structure.

**L'autorisation** repose sur `spatie/laravel-permission`. Une matrice rôle → permissions vit dans `app/Domains/Shared/Enums/Role.php` — 18 délégations : `administrateur`, `comite`, `tresorerie`, `caisse`, `amendes`, `membres`, `contacts`, `site-web`, `interclubs`, `selections`, `tournois`, `entrainements`, `coach`, `reunions`, `saisons`, `installations`, `bar`, `supervision`. Elle est appliquée en base par un `RoleSeeder` idempotent qui crée ce qui manque **et élague ce que la matrice ne déclare plus**. Les routes sont verrouillées par `can:` au niveau middleware ; les composants Livewire re-vérifient chaque action avec `Gate::authorize()`.

S'y ajoutent des *feature flags* par domaine (`FEATURE_TREASURY`, `FEATURE_BAR`, `FEATURE_INTERCLUBS`…) qui font disparaître un domaine partout à la fois : routes en 404 (et non 403, pour ne pas confirmer l'existence de l'URL), navigation, tâches planifiées, calendrier public.

**Le déploiement est manuel** : SSH + `git pull`, documenté dans `docs/DEPLOYMENT.md`. Six tâches cron et un worker de queue doivent tourner en permanence.

---

## Points bloquants avant merge

### 🔴 1 — Route d'inscription sans aucune autorisation

**Fichier** : `routes/web.php:400`, `app/Actions/ClubAdmin/Subscriptions/SubscribeToSeasonAction.php`

```php
Route::post('seasons/{season}/subscribe/', SubscribeToSeasonAction::class)
    ->name('clubEvents.interclubs.seasons.subscribe');
```

Le groupe n'applique que `auth` + `verified`. L'action valide `user_id` puis inscrit le membre désigné, **sans jamais vérifier que l'appelant a le droit d'agir pour lui**. Aucun `Gate::authorize`, aucune Policy dans `__invoke()`.

| | |
|---|---|
| **Risque** | Broken Access Control / IDOR |
| **Scénario** | Un membre authentifié poste `user_id=<id d'un autre membre>` et crée une affiliation à sa place, avec les conséquences administratives et financières qui suivent. |
| **Probabilité** | Moyenne — la route est triviale à découvrir via `route:list` ou le HTML |
| **Impact** | Élevé — écriture de données métier au nom d'un tiers |
| **Détection** | L'action est testée sur son chemin nominal (`SubscriptionActionsTest:326`) mais **jamais sur l'autorisation** |
| **Correction** | Ajouter la permission, ou supprimer la route — elle est déjà dans le bloc commenté « obsolete, to clean and remove ». **~10 min** |

### 🔴 2 — XSS stocké sur le site public via le markdown des articles

**Fichiers** : `app/Http/Controllers/ClubPosts/PublicNewsPostController.php:36`, `resources/views/public/articles/show.blade.php:80`

```php
'renderedContent' => Str::markdown($article->content ?? ''),
```
```blade
{!! $renderedContent ?? '<p>Contenu de l\'article à venir...</p>' !!}
```

`Str::markdown()` instancie `GithubFlavoredMarkdownConverter` sans options. Vérifié dans le vendor — `vendor/league/commonmark/src/Environment/Environment.php:432-433` :

```php
'html_input' => Expect::anyOf(STRIP, ALLOW, ESCAPE)->default(HtmlFilter::ALLOW),
'allow_unsafe_links' => Expect::bool(true),
```

Le HTML brut passe donc intégralement, `<script>` compris, ainsi que les liens `javascript:`.

| | |
|---|---|
| **Risque** | XSS stocké (OWASP A03) → vol de session, escalade de privilèges |
| **Scénario** | Un porteur du rôle `site-web` publie un article contenant du JS. Il s'exécute chez tous les visiteurs de `/clubPosts/{slug}`, y compris un administrateur connecté. |
| **Probabilité** | Faible — exige déjà le rôle `site-web` ou `administrateur` |
| **Impact** | Élevé — l'attaquant passe d'éditeur de contenu à administrateur |
| **Aggravant** | **Aucune CSP** dans l'application pour amortir |
| **Même vecteur** | `pages/website/articles/⚡edit/edit.php:145` (aperçu d'édition), `tournaments/⚡live-center/live-center.php:276` |
| **Correction** | `Str::markdown($c, ['html_input' => 'escape', 'allow_unsafe_links' => false])` aux 3 endroits. **~15 min** |

> Le centre d'aide (`app/Support/Help/HelpArticle.php:46`) utilise aussi `Str::markdown()`, mais sur des fichiers `.md` du dépôt — pas d'entrée utilisateur, pas de risque.

### 🔴 3 — `TrustHosts` désactivé et `TrustProxies` non configuré

**Fichiers** : `app/Http/Kernel.php:48`, `app/Http/Middleware/TrustProxies.php:29`

```php
protected $middleware = [
    // \App\Http\Middleware\TrustHosts::class,   // ← commenté
    TrustProxies::class,
    ...
];
```

La classe `TrustHosts` existe et est correctement écrite (`allSubdomainsOfApplicationUrl()`), mais elle est retirée de la pile. Et `TrustProxies::$proxies` est laissé non initialisé, donc `null` : aucun proxy n'est approuvé.

| | |
|---|---|
| **Risque A** | **Host header injection** — empoisonnement des liens de réinitialisation de mot de passe si le vhost accepte n'importe quel `Host` |
| **Risque B** | Derrière un reverse proxy, `$request->ip()` renvoie l'IP du proxy pour **tous** les visiteurs |
| **Conséquence B1** | `throttle:10,1` sur `POST /contact` devient un compteur global : **un seul visiteur peut bloquer le formulaire de contact pour tout le club** (DoS) |
| **Conséquence B2** | Les IP enregistrées par `ProtectAgainstSpam` (`:42`, `:48`) sont sans valeur |
| **Non affecté** | Le throttle de login reste correct — sa clé contient l'e-mail (`LoginRequest:85`) |
| **Probabilité** | Élevée — le déploiement est derrière un reverse proxy |
| **Correction** | Décommenter `TrustHosts`, renseigner `$proxies`. **~15 min** |

---

## Sécurité

### Ce qui est bien fait

Il faut le dire clairement : **le socle d'autorisation est bon**, et c'est visiblement le gros du travail de cette branche.

- Les routes back-office sont gatées par `can:` groupe par groupe, avec des commentaires qui justifient chaque choix de délégation.
- Les composants Livewire re-autorisent **chaque action** : `treasury/⚡payments/payments.php` porte un `Gate::authorize()` sur les 14 méthodes mutantes (`bulkCancelRefund`, `confirmReconcile`, `sendReminder`…).
- Les 10 composants `my-space` font tous `abort_unless(Auth::user()->is($user), 403)` dans `mount()`, et les mutations le revérifient (`settings.php` le fait 4 fois).
- `UserDocumentController` est exemplaire : documents médicaux sur le disque **privé**, servis par route contrôlée, trois cas d'accès explicites (soi-même, `users.view`, tuteur légal).
- Pas de `Gate::before()` de contournement admin — volontairement, et commenté (`AuthServiceProvider:79`).
- Les suites `tests/Feature/Security/` et `tests/Feature/Permissions/` ajoutées sur cette branche verrouillent tout ça. `BackofficeRouteAuthorizationTest` documente même la faille corrigée : « la navigation se contentait de *cacher* les liens ».

### Ce qui a été cherché et non trouvé

| Vecteur | Constat |
|---|---|
| Injection SQL | **Aucune.** Tout le SQL brut est paramétré, y compris les `whereRaw('LOWER(col) LIKE ?', [...])` de `User` (`:776`, `:795`) et `ManagesGuardians:293`. `Contact::getStatusStats()` n'interpole que des littéraux. |
| Mass assignment | Aucun `$request->all()` en `create`/`fill`/`update`. Les 3 `forceFill` sont légitimes (reset de mot de passe, purge de rôle, invalidation de `email_verified_at`). |
| Upload non sécurisé | Validation correcte partout : `mimes:jpg,jpeg,png,pdf|max:4096` pour les certificats médicaux et consentements parentaux, `image|mimes:jpg,jpeg,png,webp` pour les photos. Le stockage privé n'est jamais exposé. |
| XSS Alpine | Aucun `x-html`. |
| Cryptographie | bcrypt, 12 rounds. `Password::min(8)->letters()->numbers()->uncompromised()` en production (`AppServiceProvider:38`). |
| Sessions / cookies | `http_only: true`, `same_site: lax`. |
| URLs signées | Présentes sur tous les flux e-mail : RSVP réunions, sondages de date, inscriptions tournois, désinscription liste d'attente, flux ICS personnel. |

### Défauts restants

| Gravité | Problème | Fichier | Détail | Correction |
|---|---|---|---|---|
| 🟠 | **Aucun en-tête de sécurité** | — | Ni CSP, ni `X-Frame-Options`, ni HSTS, ni `X-Content-Type-Options`. Rien dans `app/`, `config/`, `bootstrap/`. C'est ce qui transforme le point 🔴2 en risque réel. | ~1 h |
| 🟠 | `SESSION_SECURE_COOKIE` absent de `.env.example` | `config/session.php:173` | `env('SESSION_SECURE_COOKIE')` → `null` → cookie de session envoyé en clair si HTTP. À ajouter au `.env.example` **et** à `DEPLOYMENT.md`. | ~10 min |
| 🟠 | `.env.example` avec `APP_DEBUG=true` | `.env.example:4` | Combiné à `spatie/laravel-ignition`, une copie hâtive en production expose la stack et l'environnement. `DEPLOYMENT.md` le corrige, mais le défaut du fichier reste dangereux. | ~5 min |
| 🟡 | `/forgot-password` et `/reset-password` sans throttle de route | `routes/auth.php:24-30` | Le broker limite à 60 s par utilisateur (`config/auth.php:101`) — pas de brute force. Mais rien n'empêche l'énumération ou le bombardement par IP. | ~10 min |
| 🟡 | CORS `allowed_origins: ['*']` | `config/cors.php:23` | Sur `api/*`. Atténué : `supports_credentials => false` et une seule route API (`/user` sous `auth:sanctum`). À resserrer par principe. | ~5 min |
| 🟢 | Mails `{!! $customMessage !!}` | `mail/custom-email.blade.php:2`, `custom-copy-email.blade.php:24` | Contenu rédigé par un admin, destination e-mail. Impact faible, mais HTML non filtré. | ~10 min |

---

## Architecture

### 🟠 Le squelette est resté en Laravel ≤ 10 alors que le framework est en 13

`bootstrap/app.php` utilise l'ancien style — `new Application(...)` puis singletons `Illuminate\Contracts\Http\Kernel` / `Console\Kernel` — au lieu de `Application::configure()->withMiddleware()->withRouting()`. La pile de middleware vit dans `app/Http/Kernel.php`, les tâches planifiées dans `app/Console/Kernel.php`.

Ça fonctionne : Laravel 13 supporte encore `App\Http\Kernel`. Mais vous accumulez un écart croissant avec la documentation officielle, avec les *skills* Laravel Boost embarqués dans le projet, et avec les générateurs `artisan make:`. **C'est la dette structurelle la plus lourde du dépôt.**

> Migration estimée à 3-4 h. À faire **après** ce merge, pas dedans.

### 🟠 Deux couches qui se contredisent

Le projet a migré vers `app/Domains/*` + composants Livewire, mais les anciens contrôleurs sont toujours là — dont quatre quasi vides, remplis de `// TODO` :

- `app/Http/Controllers/ClubAdmin/Payment/PaymentController.php`
- `app/Http/Controllers/ClubAdmin/Subscription/RegistrationController.php`
- `app/Http/Controllers/ClubEvents/Interclub/SeasonController.php`
- `app/Http/Controllers/ClubEvents/Tournament/PoolController.php`

Leurs routes `Route::resource` existent toujours (`web.php:396-399`), sous `auth` + `verified` seulement.

**Bonne nouvelle : elles ne fuient rien.** Vérifié — les vues (`clubAdmin.payments.index`, `clubEvents.interclubs.seasons.index`, `clubAdmin.registrations.index`) **n'existent pas**. `Payment::all()` s'exécute, puis `view()` lève une exception → 500. Et `tests/Pest.php:186-189` les recense explicitement :

```php
'clubEvents.interclubs.seasons.index' => 'TODO legacy: missing view, superseded by admin.seasons.index',
'admin.payments.index' => 'TODO legacy: missing view, superseded by admin.treasury.payments',
```

C'est donc du code mort **connu**, mais qui expose des routes en 500 à tout membre authentifié.

> Correction : supprimer routes + contrôleurs + entrées de `Pest.php`. **~30 min**

### 🟡 Duplication de services

`app/Domains/Bar/Services/CashSheetService.php` et `app/Services/Bar/CashSheetService.php` coexistent avec un contenu quasi identique (même `selectRaw('payment_method, SUM(total_price) as total')`, aux lignes 58 et 55 respectivement). L'un des deux est mort ; il faut déterminer lequel.

> **~20 min**

### 🟡 Fichiers trop gros

| Fichier | Lignes |
|---|---|
| `pages/club-events/tournaments/⚡wizard/wizard.php` | 1532 |
| `pages/club-admin/users/⚡registrations/registrations.php` | 1266 |
| `app/Http/Controllers/ClubEvents/Tournament/TournamentController.php` | 1005 |
| `pages/club-events/interclubs/⚡captain-selection/captain-selection.php` | 995 |
| `app/Domains/ClubAdmin/Users/Models/User.php` | 985 |
| `app/Domains/Competitions/Tournament/Services/TournamentMatchService.php` | 943 |

Le modèle `User` porte à lui seul les scopes de recherche, les vétérans, les catégories d'âge, les rôles, les documents, les préférences de notification. Découpage en traits ou services recommandé — hors de ce merge.

---

## Base de données

### Ce qui est bien fait

Le travail sur les clés étrangères est **solide**. 57 migrations utilisent `constrained()`, et les cascades sont explicites et cohérentes :

| Comportement | Occurrences |
|---|---|
| `nullOnDelete()` | 53 |
| `cascadeOnDelete()` | 53 |
| `onDelete('cascade')` | 16 |
| `onDelete('set null')` | 4 |

Rien d'orphelin. Les tables pivot ont leurs contraintes d'unicité (`meeting_user`, `pool_user`, `tournament_pairs`, `meeting_date_votes`). Les colonnes naturellement uniques le sont (`users.email`, `users.licence`, `clubs.licence`, `payments.reference`, `news_posts.slug`, `app_settings.key`, `email_templates.key`).

### 🟠 Couverture d'index très faible

Sur **71** migrations `create_*`, **7 seulement** déclarent un index. Les colonnes de clé étrangère sont couvertes automatiquement par InnoDB, mais les colonnes de filtrage chaudes ne le sont pas :

- `subscriptions.status`
- `payments.status`
- `contacts.status`
- les dates de début/fin de saison

Ce sont exactement les colonnes qu'interrogent les écrans trésorerie et roster. Invisible aujourd'hui (quelques centaines de membres), pénalisant à mesure que l'historique de saisons s'accumule.

> Migration d'index ciblée : **~1 h**

### 🟠 Rollback impossible sur plusieurs migrations

Une dizaine sont destructives par nature :

- `2026_06_13_004442_drop_is_active_from_users_table`
- `2026_06_13_025720_drop_has_paid_from_users_table`
- `2026_07_21_234338_drop_role_flags_from_users`
- `2026_07_05_211014_drop_family_columns_from_users_table`
- `2026_07_03_203526_migrate_member_documents_to_private_disk`
- `2026_07_21_015409_backfill_roles_from_user_flags`

`docs/DEPLOYMENT.md` l'assume explicitement et impose de restaurer un dump plutôt que de faire `migrate:rollback`. C'est la bonne réponse — mais cela signifie que **la sauvegarde avant déploiement n'est pas une précaution, c'est la seule voie de retour**. À traiter comme une étape bloquante de la procédure, pas comme une recommandation.

### 🟢 Compatibilité SQLite / MySQL

Bien gérée, et consciemment. `Contact::getStatusStats()` utilise `SUM(status = 'new')`, valide sur les deux moteurs. Les recherches passent par des `LOWER(col) LIKE ?` **par colonne** plutôt que des `CONCAT` — un commentaire du code (`User.php:765`) dit explicitement que c'est pour rester agnostique.

Les tests tournent en SQLite `:memory:` (forcé dans `phpunit.xml` avec un commentaire qui explique pourquoi : éviter d'écraser la base de dev et les corruptions en parallèle), la production en MariaDB. L'écart existe mais il est contenu.

---

## Backend

### 🟠 Envoi d'e-mails synchrone sur un endpoint public

`app/Actions/ClubAdmin/Contact/StoreContactAction.php:32,37` envoie deux mails avec `Mail::to()->send()` — donc SMTP **en ligne dans la requête HTTP**, d'autant plus que `.env.example` propose `QUEUE_CONNECTION=sync`.

Pire, le `catch` logge **puis relance** l'exception (`:52`) :

```php
} catch (Exception $e) {
    Log::error('Error sending contact notification emails', [...]);
    // Still return the contact - it was created successfully
    // Email failure shouldn't prevent the contact from being stored
    throw $e;   // ← contredit le commentaire juste au-dessus
}
```

Si le relais SMTP est indisponible, le contact **est déjà créé en base** mais le visiteur reçoit une 500. Il resoumet → doublons. C'est le seul formulaire public de l'application.

> Passer en `->queue()` et ne pas relancer l'exception. **~30 min**

### 🟠 Neuf mailables sur quatorze sans `ShouldQueue`

`RequestInfoEmail`, `TournamentPublishedMail`, `MembershipInfoDetailEmail`, `PoliteDeclineEmail`, `PaymentInvitationEmail`, `ContactFormConfirmationEmail`, `WelcomeEmail`, `CustomEmail`, `TournamentPaymentRequestMail`.

Les envois depuis `treasury/⚡payments/payments.php:662` et `users/⚡registrations/registrations.php:1064` bloquent l'interface admin le temps du SMTP.

> `QueueStalledMail` est volontairement synchrone (`Mail::sendNow()`) — c'est justifié et commenté : alerter que la queue est morte via la queue n'aurait pas de sens.

> **~45 min**

### 🟠 Aucune tâche planifiée n'a `withoutOverlapping()` ni `onOneServer()`

`app/Console/Kernel.php:35-61` — six tâches, dont deux **horaires** qui manipulent des listes d'attente :

| Tâche | Fréquence |
|---|---|
| `tournament:process-deadlines` | horaire |
| `training:process-deadlines` | horaire |
| `tournament:close-registrations` | 00 h 05 |
| `payment:send-refund-reminder` | lundi 08 h 00 |
| `season:provision` | 1er juillet |
| `queue:check-health` | horaire |

Si un run dépasse une heure, deux instances promeuvent le même joueur en parallèle. Condition de concurrence bien réelle sur une logique métier sensible.

> **~20 min**

### 🟡 PHPStan : 164 erreurs, aucune baseline, et jamais lancé

Le script `composer test` enchaîne `config:clear` → `pint --test` → tests. **PHPStan n'y figure pas**, alors qu'il est installé (`larastan/larastan ^3.0`) et configuré (`phpstan.neon`, niveau 5).

Les 164 erreurs sont majoritairement du typage de collections Eloquent, mais quelques signaux méritent un coup d'œil :

| Fichier | Ligne | Signal |
|---|---|---|
| `Services/ClubAdmin/Planning/ImportTrainingPlanService.php` | 242, 244 | `keyBy()` mal typé, retour `Collection<Model>` au lieu de `Collection<TrainingPlanPack>` |
| `Services/ClubAdmin/Users/FederationMemberMatcher.php` | 81, 156 | Comparaison `!== null` sur une valeur que PHPStan sait non-nulle — logique morte ou annotation fausse |
| `Support/Captcha.php` | 45 | `match` non exhaustif (`mixed` non traité) |
| `Support/Breadcrumb.php` | 15 | `new static()` non sûr |

> Ajouter PHPStan au pipeline avec une baseline sur l'existant. **~1 h**

### 🟢 À porter au crédit

`Model::preventLazyLoading()` est actif hors production (`AppServiceProvider:33`) — **donc actif pendant les tests**, et les 3013 tests passent. C'est une preuve solide d'absence de N+1 sur tous les chemins couverts.

18 `DB::transaction`, 4 Observers (`User`, `Subscription`, `Interclub`, `Tournament`), des Enums partout, des Form Requests, 11 Policies. Le code métier récent est de bonne facture.

### 🟢 Huit `::all()` non bornés

Six sont dans les contrôleurs morts cités plus haut ; les deux autres portent sur `Room` (quelques lignes). Non-problème une fois le code mort supprimé.

---

## Frontend

Rien d'alarmant. Surface JS minime, pas de bundle lourd à auditer.

| Vérification | Résultat |
|---|---|
| `x-html` (XSS Alpine) | aucun |
| `onclick` inline | 7 |
| `<img>` sans `alt` | 3 |
| Vite | plugin Laravel officiel, `refresh: true`, Tailwind 4 via `@tailwindcss/vite` |

### 🟡 Environ 12 Mo d'images mortes committées

| Fichier | Taille | Références |
|---|---|---|
| `public/images/background_results.jpg` | 7,6 Mo | **0** |
| `public/images/background_events.jpeg` | 3,2 Mo | **0** |
| `public/images/background_home.jpg` | 792 Ko | **0** |
| `public/images/background_news.jpg` | 356 Ko | **0** |
| `public/images/table-tennis-background1.jpg` | 304 Ko | **0** |

Seules les versions `.webp` sont servies (`public/events.blade.php:6`, `components/public/hero.blade.php:4`, etc.). Ces JPG ne pèsent donc pas sur les visiteurs — uniquement sur le dépôt et sur chaque clone.

> **~10 min**

### 🟡 `_ide_helper.php` (1 Mo) toujours suivi par Git

`.gitignore:47` contient bien `_ide_helper*.php`, mais le fichier a été ajouté **avant** la règle : il reste suivi. Il apparaît d'ailleurs modifié dans le working tree actuel.

> `git rm --cached _ide_helper.php _ide_helper_models.php`. **~2 min**

---

## Docker

**Il n'existe aucun Dockerfile ni docker-compose dans ce dépôt.** Recherche effectuée sur toute l'arborescence hors `vendor/` et `node_modules/` : zéro résultat. Le seul indice est un `.gitignore` qui exclut `docker-compose.override.yml` — un vestige.

L'environnement de test tourne bien sur Docker (Synology), mais **cette configuration ne vit pas dans le dépôt** : elle est hors versionnement.

Conséquences concrètes :

- L'environnement de test n'est pas reproductible depuis un clone.
- Pas de parité dev/prod versionnée.
- Rien ne documente les extensions PHP attendues autrement qu'en prose dans `DEPLOYMENT.md` (`pdo_mysql`, `mbstring`, `gd`, `zip`, `intl`).
- Aucune image, donc aucune notion de multi-stage, d'utilisateur non-root, de healthcheck ou de volume nommé à auditer.

Ce n'est pas un défaut de *cette branche* — c'est une absence structurelle.

> Chantier : Dockerfile multi-stage (build assets → runtime PHP-FPM non-root) + compose avec MariaDB, healthchecks et volumes nommés. **~4 h**

---

## CI / CD

**Il n'existe aucun workflow GitHub Actions.** `.github/` ne contient que `skills/`. Il n'y a donc :

- aucune exécution automatique des tests sur une PR ;
- aucune vérification Pint automatisée (uniquement locale, via `composer test`) ;
- aucune analyse PHPStan (outil installé, configuré, **jamais lancé**) ;
- aucun Rector en vérification (`rector.php` a tous ses niveaux à 0, donc inerte) ;
- aucun `composer audit` / `npm audit` périodique ;
- aucun garde-fou avant merge, aucun déploiement automatisé, aucun rollback outillé.

**C'est le point le plus important de ce rapport.** Vous vous apprêtez à fusionner 291 commits et 76 000 lignes, et la seule barrière est votre discipline personnelle à lancer `composer test`. Elle a manifestement tenu — 3013 tests au vert le prouvent — mais elle ne passe pas à l'échelle, et elle n'a détecté **aucun** des trois points bloquants ci-dessus.

> Un workflow minimal — checkout, PHP 8.5, `composer install`, `pint --test`, `phpstan`, `artisan test --parallel` — représente **~1 h** et éliminerait toute cette catégorie de risque.

### Un mot sur la documentation de déploiement

`docs/DEPLOYMENT.md` est franchement bon : sauvegarde obligatoire, `RoleSeeder` non optionnel avec le rappel de l'incident du 22 juillet 2026, `queue:restart` expliqué, avertissement explicite sur l'impossibilité du rollback, procédure de vérification post-déploiement. C'est de la documentation qui a servi.

Elle ne remplace pas l'automatisation, mais elle réduit beaucoup le risque humain.

---

## Tests

C'est le point fort de la branche : **+143 fichiers de test**.

### Suites de sécurité entièrement nouvelles

- `tests/Feature/Security/PrivilegeEscalationTest.php`
- `tests/Feature/Security/BackofficeRouteAuthorizationTest.php`
- `tests/Feature/Security/CaptainSelectionAuthorizationTest.php`
- `tests/Feature/Security/AdminNavigationVisibilityTest.php`
- `tests/Feature/Permissions/RoleMatrixTest.php`
- `tests/Feature/Permissions/RoleSeederTest.php`
- `tests/Feature/Permissions/TreasuryAuthorizationTest.php`

Le commentaire d'en-tête de `BackofficeRouteAuthorizationTest` documente la faille corrigée : « le back-office se reposait sur une navigation qui se contentait de *cacher* les liens, tandis que les routes sous-jacentes restaient atteignables par URL pour tout membre authentifié ».

### Tests d'architecture — 9 fichiers, 631 lignes

`AffiliationVocabularyTest` (cohérence du vocabulaire métier), `BladeConventionsTest`, `LaravelConventionsCustomTest`, `LayeringTest`, `NamingConventions`, `PhpConventionsCustomTest`, `PresetTest`, `ReturnTypes`, `TranslationCoverageTest`.

### Suites Browser — 17 fichiers

`AuthFlowTest`, `CaptainSelectionZoomTest`, `ContactFlowTest`, `CriticalPagesSmokeTest`, `DelegationScreensTest`, `InterclubResultsTest`, `LivewireReactivityTest`, `MeetingFlowTest`, `SubscriptionFlowTest`, `TournamentFlowTest`, `TrainingEnrollmentFlowTest`, etc.

### Ce qui n'est pas couvert, et qui compte

| Zone | Manque |
|---|---|
| `SubscribeToSeasonAction` | Testé sur le chemin nominal, **jamais sur l'autorisation** — d'où le 🔴1 non détecté |
| Rendu markdown public | Aucun test n'injecte de HTML dans un article pour vérifier l'échappement — d'où le 🔴2 |
| En-têtes HTTP de sécurité | Aucun test (rien à tester aujourd'hui) |
| `TrustHosts` / injection de `Host` | Aucun test |
| Chevauchement des tâches planifiées | Aucun test |
| **Suite Browser** | **Non exécutée pendant cet audit** — l'environnement était occupé par un run lancé hors session. Son état est inconnu. **À lancer impérativement avant merge.** |

30 tests skipped et 7 todos : à passer en revue — certains recouvrent les routes legacy à supprimer.

---

## Dette technique — synthèse

> État au moment de l'audit. Tout est soldé sauf Docker et le découpage des fichiers > 900 lignes — voir le **Suivi** en tête de document.

| Priorité | Élément | Effort |
|---|---|---|
| 🔴 | Route `subscribe` sans autorisation | 10 min |
| 🔴 | XSS markdown sur page publique | 15 min |
| 🔴 | `TrustHosts` / `TrustProxies` | 15 min |
| 🟠 | Absence totale de CI | 1 h |
| 🟠 | Absence totale de Docker | 4 h |
| 🟠 | Aucun en-tête de sécurité / CSP | 1 h |
| 🟠 | Squelette Laravel ≤ 10 sur framework 13 | 3-4 h |
| 🟠 | Contrôleurs et routes legacy morts | 30 min |
| 🟠 | Mails synchrones (9 mailables + contact public) | 1 h 15 |
| 🟠 | `withoutOverlapping()` sur les tâches planifiées | 20 min |
| 🟠 | Index de base de données manquants | 1 h |
| 🟡 | PHPStan : 164 erreurs, jamais lancé | 1 h |
| 🟡 | Throttle sur les routes de mot de passe | 10 min |
| 🟡 | Doublon `CashSheetService` | 20 min |
| 🟡 | Fichiers > 900 lignes | plusieurs jours |
| 🟢 | 12 Mo d'images mortes | 10 min |
| 🟢 | `_ide_helper.php` suivi par Git | 2 min |
| 🟢 | `AGENTS.md` et `CLAUDE.md` strictement identiques | 2 min |

---

## Quick wins — moins de 30 minutes chacun

> Les douze ont été appliqués. Conservés pour mémoire.

1. Supprimer les 4 routes `Route::resource` legacy + contrôleurs `// TODO` (`web.php:396-399`) — **10 min**
2. `Str::markdown($c, ['html_input' => 'escape', 'allow_unsafe_links' => false])` aux 3 endroits — **15 min**
3. Décommenter `TrustHosts::class` (`Kernel.php:48`) — **5 min**
4. Renseigner `TrustProxies::$proxies` selon le reverse proxy — **10 min**
5. Autoriser ou supprimer la route `seasons/{season}/subscribe` — **10 min**
6. `SESSION_SECURE_COOKIE=true` + `APP_DEBUG=false` dans `.env.example` — **5 min**
7. `git rm --cached _ide_helper.php _ide_helper_models.php` — **2 min**
8. Supprimer les 5 JPG inutilisés (~12 Mo) — **10 min**
9. `->withoutOverlapping()` sur les 6 tâches planifiées — **20 min**
10. `throttle:6,1` sur `password.email` et `password.store` — **10 min**
11. Supprimer le doublon `CashSheetService` — **20 min**
12. Remplacer `AGENTS.md` (identique à `CLAUDE.md`) par un lien — **2 min**

---

## Recommandations avant production

### Avant ce merge — environ 1 h

- Les trois points 🔴 (quick wins 2, 3, 4, 5)
- **Lancer la suite Browser** : `rm -f public/hot && composer test`

### Dans le mois

- Workflow GitHub Actions (1 h) — **priorité absolue**
- En-têtes de sécurité + CSP (1 h)
- PHPStan dans le pipeline, avec baseline sur les 164 erreurs existantes (1 h)
- Mailables en `ShouldQueue` + contact public asynchrone (1 h 15)
- Migration d'index sur les colonnes de statut (1 h)

### Dans le trimestre

- Dockerfile + compose versionnés (4 h)
- Migration du squelette vers `Application::configure()` (3-4 h)
- Découpage de `User.php` et des composants > 900 lignes

---

## Checklist finale

**Avant de merger** — soldée le 1er août 2026

- [x] Route `seasons/{season}/subscribe` autorisée ou supprimée
- [x] `Str::markdown()` en mode `escape` (3 emplacements) — centralisé dans `App\Support\Markdown::safe()`
- [x] `TrustHosts::class` activé — via `$middleware->trustHosts()`, le `Kernel.php` n'existant plus
- [x] `TrustProxies::$proxies` — statué : reste non configuré, Apache répondant en direct. La décision est commentée dans `bootstrap/app.php:53`
- [x] Suite Browser exécutée et verte — désormais en CI, dans son propre job
- [x] `vendor/bin/pint` passé sur les fichiers modifiés — et bloquant en CI

**Avant de déployer**

- [ ] `mysqldump` de la base de production effectué et vérifié
- [ ] `tar czf` de `storage/app` effectué
- [ ] `.env` de production comparé à `.env.example` (nouvelles clés de feature flags)
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`
- [ ] `QUEUE_CONNECTION=database` (jamais `sync`)
- [ ] `composer install --no-dev --optimize-autoloader` (pas seulement `git pull`)
- [ ] `php artisan db:seed --class=RoleSeeder --force` — **non optionnel**
- [ ] `php artisan queue:restart` après le déploiement
- [ ] Worker de queue supervisé et actif
- [ ] Crontab `schedule:run` en place
- [ ] Connexion admin testée (valide la chaîne rôles/permissions)
- [ ] Une page de chaque délégation ouverte (valide les caches)
- [ ] Un envoi d'e-mail déclenché (valide le worker)

---

## Recommandation finale

# ⚠️ Merge possible avec réserves

> **Réserves levées le 1er août 2026.** Les trois défauts exploitables sont corrigés et testés, et la CI existe. Le verdict ci-dessous reste celui du jour de l'audit.

### Pourquoi pas ❌

La branche est saine sur le fond. 3013 tests au vert, aucune vulnérabilité de dépendance, aucun secret committé, 0 commit de retard sur `main` (fast-forward propre). Le cœur du travail de cette saison — la refonte des rôles et permissions — est fait avec sérieux : gating au niveau route **et** au niveau action, matrice versionnée, seeder idempotent qui élague, et des suites de tests écrites exprès pour verrouiller les régressions d'autorisation. Ce n'est pas une branche à laquelle on refuse l'entrée.

### Pourquoi pas ✅

Trois défauts exploitables sont présents et **aucun n'est couvert par un test** : une route qui inscrit n'importe qui à une saison sans contrôle, un XSS stocké sur la page publique des articles, et une configuration réseau (`TrustHosts` commenté, `TrustProxies` vide) qui rend le throttle du formulaire de contact contournable et les liens de réinitialisation empoisonnables. Aucun ne demande plus de quinze minutes de correction, mais chacun est bien réel.

### La réserve la plus sérieuse n'est pas dans le code

**C'est l'absence totale de CI.** 291 commits fusionnent dans `main` sans qu'aucune machine ne les ait vérifiés. Votre discipline a tenu — les tests le prouvent — mais elle n'a détecté aucun des trois points bloquants ci-dessus, et elle ne tiendra pas indéfiniment à mesure que le projet grossit.

C'est le premier chantier après ce merge : avant Docker, avant la modernisation du squelette.

### Marche à suivre

1. ~~Appliquer les quick wins **1 à 5** (≈ 1 h)~~ — fait
2. ~~Lancer `composer test` en entier, **suite Browser comprise**~~ — fait, et automatisé en CI
3. **Merger** ← seule étape restante
4. ~~Ouvrir immédiatement une branche pour le workflow CI~~ — fait, la CI a précédé le merge

> Les points 1, 2 et 4 ont été traités avant le merge plutôt qu'après : la réserve principale du rapport — « 291 commits fusionnent dans `main` sans qu'aucune machine ne les ait vérifiés » — ne tient plus. Voir le **Suivi** en tête de document.
