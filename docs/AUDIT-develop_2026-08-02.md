# Audit de pré-merge — branche `develop` → `main`

**Projet** : Table Tennis Club (CTT Ottignies-Blocry)
**Date** : 2 août 2026
**Périmètre audité** : les 8 commits `a420b4af..4926e7c4`, 22 fichiers, +1 074 / −143 lignes, 0 commit de retard sur `main`
**Méthode** : analyse statique et exécution réelle de la suite complète. Aucune modification de code pendant l'audit.

> **Portée de ce document.** Il porte sur les 8 commits ci-dessus, tels qu'ils étaient le 2 août au matin. Cinq commits de refactoring outillé (`52114b45..700fd1de` — Rector, suppression de code mort) ont été produits **après** cet audit, à la demande, et ne sont pas couverts ici. Ils ne modifient ni la logique d'import fédéral, ni la règle du tuteur, ni l'écran de relecture : les constats ci-dessous restent valables tels quels, et les références de ligne ont été revérifiées après leur passage.
>
> L'audit précédent, `AUDIT-develop-2026-08-01.md`, couvrait les 291 commits de la release de saison. Ce qu'il a produit est aujourd'hui dans `main`.

---

## Résumé exécutif

`main` contient déjà l'audit du 1er août et ses correctifs. Cette branche n'est donc plus une release de saison mais un delta de 8 commits, cohérent et sur un seul sujet : **le membre géré** — celui qui n'a ni adresse e-mail ni téléphone à lui et qu'on joint via son tuteur.

Concrètement : `email` devient `?string` dans le formulaire d'admin et dans `UpdateUserData`, la complétude de profil accorde une dispense de téléphone à qui a un tuteur (`hasCompleteProfile()` et son jumeau SQL), l'import fédéral cesse de prendre un enfant pour son parent sur la foi d'une adresse partagée, et l'écran d'import sépare les lignes qui demandent quelque chose de celles qui ne demandent rien. Plus un correctif de seeder (`ForceList` injecté alors que la classe n'existait plus).

**Le travail est de bonne facture.** Aucune migration, aucune route, aucune dépendance modifiée. Environ 80 tests ajoutés, dont plusieurs couvrent des cas métier fins — le fils homonyme de son père, la mère au nom de jeune fille, le frère aîné qui détient l'adresse du foyer. Deux règles jumelles PHP et SQL sont explicitement testées l'une contre l'autre.

**Trois angles morts subsistent**, tous du même genre : la branche a raison sur le fond et incomplet sur le bord. Aucun n'est un défaut de conception ; ils se corrigent en une à deux heures au total.

### Ce qui a été mesuré

| Vérification | Résultat |
|---|---|
| Unit + Feature + Architecture | **3 181 passés, 0 échec** — 29 skipped, 7 todos, 119 s en `--parallel` |
| Browser (Playwright) | **74 passés, 0 échec** — 51 s |
| Pint | `passed` |
| PHPStan niveau 5 (+ baseline de 100 entrées) | **No errors** |
| `composer audit` / `npm audit --omit=dev` | 0 vulnérabilité |
| Secrets committés | aucun — `.env`, `.env.testing`, la base SQLite de dev et `_ide_helper*` hors index |
| `dd()` / `dump()` / `console.log` oubliés | **aucun** |
| Position vs `main` | 8 en avance, 0 de retard (fast-forward propre) |

---

## Score global : **82 / 100**

| Domaine | Note | Commentaire |
|---|---|---|
| Sécurité applicative | 17/20 | Socle d'autorisation solide ; un lien de tutelle déduit d'une chaîne |
| Architecture | 16/20 | Squelette L13 migré, domaines nets ; 5 fichiers > 900 lignes assumés |
| Base de données | 15/20 | Clés étrangères irréprochables ; **aucune migration dans cette branche** |
| Backend | 17/20 | Code très commenté, actions transactionnelles, pas de N+1 introduit |
| Frontend | 14/20 | Surface minuscule mais bundle non découpé (814 Ko) |
| Docker | 0/10 | Toujours inexistant |
| CI/CD | 8/10 | 4 jobs, miroir de `composer test` ; ni Rector ni Peck, ni déploiement |
| Tests | 17/20 | 3 255 verts, mais 4 skips masquent une garde d'escalade de privilèges |
| Documentation | 10/10 | 30 documents, manuels par rôle, audit précédent tenu à jour |

---

## Comprendre l'application

**Ce n'est pas une application Vue.** Le front est **Livewire 4 + Alpine 3 + Tailwind 4 + Mary UI/daisyUI**. Chaque écran est un composant mono-fichier `resources/views/pages/**/⚡nom/nom.php` (+ `.blade.php`), routé **explicitement** par `Route::livewire()` dans `routes/web.php` (64 occurrences) — il n'y a pas de découverte automatique, ce qui réduit d'autant la surface d'attaque. Le JS propre au projet tient en 714 lignes réparties sur 14 modules (carte Leaflet, cropper d'avatar, thème, filtres).

Le backend est un Laravel 13 / PHP 8.5 organisé en `app/Domains/*` (Bar, ClubAdmin, Competitions, Meetings, Trainings, ClubPosts, Shared), avec des `Actions` statiques comme unité d'écriture, des `Services` pour la logique de calcul, des `Policies` par modèle et des objets `Data` en entrée. 410 fichiers PHP dans `app/`, 301 fichiers de test.

L'**autorisation** repose sur `spatie/laravel-permission`, avec une matrice rôle → permissions versionnée dans `app/Domains/Shared/Enums/Role.php` : deux rôles socles (`administrateur`, `comite` — ce dernier en lecture seule) et 16 délégations empilables (`membres`, `tresorerie`, `caisse`, `interclubs`, `tournois`…). Le verrouillage est double : `can:` au niveau route, `Gate::authorize()` re-vérifié dans chaque méthode mutante des composants. S'y ajoutent des feature flags par domaine qui font disparaître un domaine partout à la fois — routes en 404 et non 403, pour ne pas confirmer l'existence de l'URL.

Le déploiement est **manuel** (SSH + `git pull`, `docs/DEPLOYMENT.md`), sur un VPS OVH avec Apache en direct — d'où `TrustProxies` volontairement non configuré, ce qui est le bon choix ici et non un oubli. Six tâches cron et un worker de queue doivent tourner en permanence. Une CI GitHub Actions existe (4 jobs) et reproduit exactement `composer test`.

---

## Points bloquants avant merge

**Aucun bloquant absolu.** La suite est verte, l'analyse statique est verte, aucune migration n'est embarquée. Trois constats méritent cependant d'être traités **avant que la fonctionnalité serve à un vrai fichier fédéral**, parce qu'ils portent sur des écritures irréversibles ou sur une garde non vérifiée.

---

## Sécurité

### 🟠 S1 — La tutelle, qui ouvre l'accès aux documents médicaux d'un mineur, se déduit d'une adresse e-mail seule

**Fichiers** : `app/Actions/User/ImportFederationMembersAction.php:230` (`memberHoldingAddress`) et `:263` (`outsideGuardian`)

```php
return User::query()
    ->whereKeyNot($member->getKey())
    ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
    ->where(fn (Builder $q) => $q->whereNull('birthdate')
        ->orWhereDate('birthdate', '<=', now()->subYears(18)))
    ->first();
```

Le `Guardian` ainsi créé porte un `user_id`, et `app/Http/Controllers/ClubAdmin/Users/UserDocumentController.php:31` accepte précisément ce lien comme autorisation :

```php
|| $user->guardians()->where('guardians.user_id', $actor->id)->exists(),
```

C'est-à-dire l'accès en téléchargement aux **certificats médicaux et consentements parentaux** de l'enfant, stockés sur le disque privé.

L'ironie est que la même branche **durcit** `FederationMemberMatcher::byEmail()` (`:85`) en exigeant nom + date de naissance, avec un docblock qui explique très bien pourquoi : *« une adresse identifie un foyer, pas une personne »*. Cette règle n'a pas été appliquée à `memberHoldingAddress()`, qui accorde pourtant davantage qu'un simple rapprochement de fiches.

| | |
|---|---|
| **Risque** | Broken Access Control — exposition de données de santé d'un mineur (RGPD, catégorie particulière) |
| **Scénario** | La ligne d'un enfant porte l'adresse du foyer. Le secrétaire coche « cette adresse est celle d'un tuteur ». Le code retient *n'importe quel* membre non-mineur portant cette adresse — un grand frère dont la date de naissance n'est pas encodée (`whereNull('birthdate')` le classe adulte), un colocataire, ou l'adresse générique du club utilisée pour plusieurs enfants — et lui accorde la tutelle. Ce membre peut ensuite télécharger le certificat médical de l'enfant. |
| **Probabilité** | Moyenne. Le docblock reconnaît lui-même le cas du grand frère et prétend l'exclure, ce que `whereNull('birthdate')` ne fait pas — or l'audit du 1er août note que la moitié du roster a été saisie sans date de naissance. |
| **Impact** | Élevé — donnée de santé d'un mineur, chez un tiers |
| **Difficulté** | Faible |
| **Correction** | Appliquer la symétrie : exiger que le nom de famille du porteur corresponde à celui saisi sur l'écran ou à celui de l'enfant, et remplacer `whereNull('birthdate')` par une exigence de date connue **pour ce chemin précis**, puisqu'ici le silence ouvre un accès au lieu de simplement rapprocher deux fiches. **~45 min + 2 tests** |

### 🟡 S2 — Deux tuteurs externes sans adresse fusionnent en un seul

**Fichier** : `app/Actions/User/ImportFederationMembersAction.php:275`

```php
return Guardian::firstOrCreate(
    ['user_id' => null, 'email' => $line->guardianEmail],
    ['first_name' => ..., 'last_name' => ..., 'phone' => ...],
);
```

`guardians.email` est nullable (migration `2026_06_04_040633`), et `import.php` passe `guardianEmail: $row['email']` — l'adresse de la ligne, qui peut être vide. Deux enfants de deux familles différentes, tous deux sans adresse sur le listing, tous deux cochés « adresse d'un tuteur » : le second est rattaché au tuteur **du premier**, avec le nom et le téléphone d'une autre famille.

Défaut **préexistant** — le `firstOrCreate` est antérieur à cette branche — mais la nouvelle mise en page rend la case à cocher visible sur chaque ligne de mineur, ce qui augmente la fréquence du chemin.

> **Correction** : inclure `first_name` et `last_name` dans les clés de recherche, ou refuser la création quand l'adresse est nulle. **~15 min**

### 🟡 S3 — La garde qui empêche un délégué « membres » de se promouvoir administrateur n'est vérifiée par aucun test

**Fichiers** : `resources/views/pages/club-admin/users/⚡form/form.php:388-408`, `tests/Feature/ClubAdmin/Users/UserFormTest.php:80-125`

La règle existe et paraît correcte :

```php
if ((bool) $value !== $targetIsAdmin && ! $actor?->hasRole(Role::ADMINISTRATOR->value)) {
    $fail(__('Only an administrator can change the administrator status.'));
}
if ($targetIsAdmin && ! $value) { /* protection du dernier administrateur */ }
```

Les 4 tests qui la couvriraient sont `->skip()`, au motif : *« save() non déclenché via ->call() en contexte PHPUnit — à investiguer »*. **Ce motif est contredit par le fichier lui-même** : une quinzaine de tests ajoutés par cette branche appellent `->call('save')` et vérifient l'effet en base, et `tests/Feature/Permissions/DelegationScreensTest.php:152` fait de même sur le même composant.

La cause réelle est probablement autre : les deux premiers skips font agir un membre `comite`, or `comite` est un rôle **en lecture seule** (`Role.php:86`) dépourvu de `users.update`. `Gate::authorize('update')` en tête de `save()` lève donc une `AuthorizationException` avant toute validation — ce sont les assertions qui sont mal formées, pas le framework.

Cela laisse sans couverture le seul cas réellement intéressant : **le délégué `membres`, qui a bien `users.update` sans être administrateur**. C'est exactement l'acteur que la closure est censée arrêter.

| | |
|---|---|
| **Risque** | Escalade de privilèges — non démontré, mais non démontré sûr non plus |
| **Aggravant** | La protection du dernier administrateur est elle aussi sans test. Un `RoleSeeder` qui élague plus une suppression malheureuse, et le club perd son accès admin (incident déjà vécu le 22 juillet) |
| **Correction** | Réécrire les 4 tests avec un acteur porteur de la délégation `membres`, et vérifier `assertHasErrors(['is_admin'])` plus l'absence de rôle en base. **~45 min** |

### Ce qui a été cherché et non trouvé

| Vecteur | Constat |
|---|---|
| Injection SQL | Aucune. Les deux `whereRaw` ajoutés par la branche sont paramétrés |
| Mass assignment | `tests/Feature/Security/PrivilegeEscalationTest.php` verrouille le sujet ; les rôles vivent dans une table pivot inatteignable par `fill()` |
| XSS | Aucun `{!! !!}` ni `x-html` introduit ; la nouvelle carte de ligne échappe tout, y compris les noms corrigés par le relecteur |
| Upload | `required|file|mimes:csv,txt` ; le fichier est supprimé après import et rien n'en est journalisé sinon le numéro de ligne et le motif |
| Fuite par e-mail | `SendInvitationAction` refuse explicitement de retomber sur l'adresse du tuteur pour une invitation — la distinction identifiant / contact est tenue proprement partout |
| CSRF, sessions, cryptographie | Inchangés, conformes |
| Secrets | Aucun |

---

## Architecture

### 🟡 A1 — Une même règle métier écrite trois fois

La dispense de téléphone accordée par un tuteur existe en trois exemplaires :

1. `User::hasCompleteProfile()` — `filled($this->phone_number) || $this->hasGuardian()`
2. `User::scopeWithIncompleteProfile()` — le jumeau SQL, avec `whereDoesntHave('guardians')`
3. `resources/views/pages/club-admin/users/user-space/⚡onboarding/onboarding.php:192` (`mount()`) — réimplémenté en ligne

Les deux premiers sont **explicitement testés l'un contre l'autre** (`GuardianTest`, dernier test) — c'est exemplaire. Le troisième n'a pas ce filet, et c'est celui qui dérivera. Un `hasCompleteIdentity()` sur le modèle, appelé par les trois, coûte **~20 min**.

### 🟢 A2 — Fichiers > 900 lignes

`registrations.php` (1 780), `wizard.php` (1 532), `User.php` (998), `captain-selection.php` (995), `TournamentMatchService.php` (943). **Décision documentée du 1er août : classé sans suite**, faute de douleur attribuable. Cet audit la reconduit. On note seulement que `User.php` a encore grossi de 13 lignes cette semaine, ce qui est la trajectoire attendue quand un modèle sert de dépôt commun.

---

## Base de données

**Rien à signaler pour cette branche** : aucune migration, aucun changement de schéma, aucune écriture destructrice ajoutée. C'est le meilleur profil de risque possible pour un merge.

Deux points de contexte restent valables :

- 🟡 `memberHoldingAddress()` fait `whereRaw('LOWER(email) = ?')` — non sargable, aucun index utilisable. Sans effet à cette échelle (quelques centaines de membres, un import par saison), mais c'est le motif qu'on ne veut pas voir se propager dans un écran de liste.
- 🟠 **Le rollback reste un dump.** Une dizaine de migrations sont irréversibles par nature ; `DEPLOYMENT.md` l'assume. La sauvegarde avant déploiement n'est donc pas une précaution mais la seule voie de retour — à traiter comme une étape bloquante de la procédure, pas comme une recommandation.

---

## Backend

### 🟠 B1 — Les lignes où la fédération contredit le club sont repliées dans « Rien à signaler »

**Fichiers** : `resources/views/pages/club-admin/users/⚡import/import.php:373-376`, `import.blade.php:72-90`, `app/Actions/User/ImportFederationMembersAction.php:366-369`

C'est le constat le plus important de la branche.

Le nouvel accordéon classe une ligne selon `needsReview` :

```php
'needsReview' => $this->proposedAction($match->outcome) === ''
    || $row->needsNameReview       // le parser a deviné le découpage du nom
    || $row->needsAddressReview    // le parser soupçonne un décalage de colonnes
    || $minor,
```

Ces deux drapeaux viennent du **parser** — ils disent « je n'ai pas su lire ». Le tableau `$match->discrepancies`, qui dit « le club et la fédération ne sont pas d'accord » sur la **licence**, la **date de naissance**, l'**adresse** ou l'**e-mail**, **n'entre pas dans le calcul**. Une ligne `MATCHED` porteuse d'une divergence de licence reçoit donc l'action `update` par défaut et atterrit dans l'accordéon **replié** intitulé « Rien à signaler » — dont le docblock affirme pourtant que « le roster et le listing sont d'accord ».

Or `update()` écrase précisément ces champs :

```php
self::overwrite($member, 'licence', ...);   // ligne 366
self::overwrite($member, 'street',  $row->street);
self::overwrite($member, 'city_code', $row->cityCode);
self::overwrite($member, 'city_name', $row->cityName);
```

`email` et `birthdate` passent par `fillIfMissing()` et sont donc protégés ; la licence et l'adresse, non.

Avant ce commit, toutes les lignes étaient dans une liste plate et l'avertissement `text-warning` était visible sans geste. La nouvelle mise en page est une amélioration réelle sur le volume, mais elle a déplacé le seul signal qui justifiait la relecture derrière un clic.

| | |
|---|---|
| **Risque** | Écrasement silencieux du numéro de licence ou de l'adresse d'un membre |
| **Probabilité** | Élevée — la divergence de licence est le cas courant que la relecture existe pour attraper |
| **Impact** | Moyen à élevé : la licence est la clé de rapprochement avec la fédération, et l'import tourne dans une transaction unique déjà validée quand on s'en aperçoit |
| **Difficulté** | Triviale |
| **Correction** | Ajouter `|| $match->discrepancies !== []` dans `needsReview`, plus un test « une ligne dont la licence diverge est classée à relire ». **~15 min** |

### 🟡 B2 — Le durcissement de `byEmail()` peut créer un doublon silencieux

`FederationMemberMatcher::byEmail()` exige désormais `sameName()`. `normalize()` gère la casse et les accents (`Str::ascii`), mais ni le trait d'union ni la soudure : `Jean-Pierre` ≠ `Jean Pierre`, `Van Oudenhove` ≠ `Vanoudenhove`, `De Smet` ≠ `Desmet`. Sur un roster belge saisi à la main, ce n'est pas un cas d'école.

La cascade devient alors : `byLicence` échoue (le membre a été saisi sans licence — cas que le code documente lui-même), `byEmail` échoue désormais, `byNameAndBirthdate` échoue, `namesake` échoue → **`NEW`**. `unlessTaken()` évite bien la violation de contrainte unique, donc pas de crash et la transaction passe — mais le résultat est **un second membre, sans adresse e-mail**, pendant que l'ancienne fiche garde la sienne.

Et à cause de B1, cette ligne `NEW` reçoit l'action `create` et va dans l'accordéon replié. Les deux constats se composent : le durcissement produit plus de faux « inconnu du club », et la nouvelle mise en page les soustrait au regard.

Ce n'est **pas** un argument pour revenir en arrière — le durcissement corrige un défaut bien plus grave, où l'enfant recevait la fiche de son parent. C'est un argument pour que B1 soit corrigé **avec** lui.

> **Correction** : corriger B1 ; en complément, faire tomber les séparateurs dans `normalize()` (`preg_replace('/[\s\'-]+/', '', …)`) et couvrir « Jean-Pierre vs Jean Pierre ». **~30 min**

### 🟡 B3 — Retirer l'adresse d'un membre détruit son accès, sans avertissement

`form.php` autorise désormais à vider le champ e-mail d'un membre **existant** dès qu'un tuteur est rattaché ; `UpdateUserAction:55-61` annule alors `email_verified_at` et — correctement — s'abstient d'envoyer la notification de vérification. Le comportement est testé et voulu.

Mais l'e-mail **est** l'identifiant de connexion. Un membre adulte qui se connectait hier ne le peut plus, et l'unique indication à l'écran est le hint statique « Laissez vide si le membre est joint via son tuteur ». Pas de confirmation, pas de distinction entre « ce membre n'en a jamais eu » et « je retire celui d'un membre actif ».

> **Correction** : une confirmation quand `$this->user?->email !== null` et que le champ est vidé. **~20 min**

### 🟢 B4 — `importFile` sans `max:`

`'importFile' => 'required|file|mimes:csv,txt'`, puis `file_get_contents()` du fichier entier. Réservé au porteur de `users.import`, donc à peine un risque — mais un `max:2048` coûte une ligne.

---

## Frontend

- 🟡 **Bundle non découpé.** `app-*.js` = 378 Ko et `app-*.css` = 436 Ko, servis sur **toutes** les pages. `resources/js/app.js` importe statiquement Leaflet (carte de contact), Cropper.js (upload d'avatar) et SortableJS (planning) — trois bibliothèques qu'aucune page publique n'utilise. Un `import()` dynamique sur ces trois modules rendrait ~200 Ko à la page d'accueil. **~1 h**
- 🟢 Le nouveau `_line-card.blade.php` est un bon geste : 94 lignes extraites d'un `import.blade.php` qui en perdait 89. Il n'est pas routable — les pages sont déclarées à la main, jamais découvertes — donc le préfixe `_` est une convention, pas une protection, et c'est très bien ainsi.
- 🟢 `wire:key="line-{{ $line }}"` est présent sur la carte, et le commentaire qui explique pourquoi `needsReview` est figé au parsing (« une ligne qui changerait de section sous le pointeur donnerait le clic suivant au mauvais affilié ») est exactement le bon raisonnement.
- 🟢 Accessibilité : les champs passent par les composants Mary (label lié), les badges portent du texte et pas seulement une couleur.

---

## Docker

**0/10 — inchangé.** Aucun `Dockerfile`, aucun `docker-compose.yml` dans le dépôt. La configuration du NAS Synology qui sert d'environnement de test vit hors versionnement : elle n'est ni relue, ni reproductible, ni restaurable. Estimation inchangée : **~4 h** pour un multi-stage PHP-FPM + nginx et un compose de développement. Hors périmètre de ce merge.

---

## CI/CD

La CI est bonne et honnête : quatre jobs qui reproduisent `composer test` à l'identique, avec des commentaires qui expliquent chaque non-évidence — pourquoi `npm run build` n'est pas optionnel, pourquoi `rm -f public/hot`, pourquoi la suite Browser refuse `--parallel`, pourquoi `composer audit` est en `continue-on-error`. C'est le contraire d'un fichier copié.

Manques :

| Gravité | Constat |
|---|---|
| 🟡 | **Rector et Peck sont installés et jamais exécutés** — ni dans `composer test`, ni en CI. Deux dépendances de développement qui ne rendent rien *(traité depuis, hors périmètre de cet audit — voir `52114b45..700fd1de`)* |
| 🟡 | **Aucun déploiement ni rollback automatisés.** SSH manuel. Comme le rollback de base est un dump manuel, une release ratée signifie une intervention à la main sous pression |
| 🟢 | Pas de garde-fou de couverture — assumé, le bloc `<coverage>` a été retiré pour des raisons documentées dans `phpunit.xml` |

---

## Tests

**3 255 tests verts**, dont environ 80 ajoutés par cette branche, et de bonne facture : `FederationImportTest` couvre les trois formes de tutelle, `FederationMemberMatcherTest` couvre le fils homonyme de son père et la mère au nom de jeune fille, `UserFormTest` couvre les six cas du membre géré, `SeederResolutionTest` transforme un incident réel — `ForceList` injecté après suppression — en filet permanent.

Les manques :

| Gravité | Non couvert |
|---|---|
| 🟠 | **La garde d'escalade `is_admin` et la protection du dernier administrateur** (S3) — 4 tests skippés, aucun substitut ailleurs |
| 🟠 | `memberHoldingAddress()` n'a **aucun test négatif** : pas de cas « le porteur de l'adresse n'a rien à voir avec l'enfant ». Les trois tests existants confirment tous le chemin nominal, ou le cas du frère mineur *dont la date de naissance est connue* |
| 🟡 | La classification `needsReview` / `linesToReview` n'est vérifiée sur **aucune** ligne porteuse d'une divergence |
| 🟡 | 13 tests de `tests/Feature/Trainings/CreateTest.php` skippés contre des routes supprimées — du test mort, à réécrire ou à supprimer |
| 🟡 | 37 `skip`/`todo` au total, dont 8 « not able to test toasts » : un helper d'assertion Mary UI les débloquerait tous d'un coup |

---

## Dette technique

Inchangée depuis l'audit du 1er août, aucune ajoutée de façon notable :

- Baseline PHPStan de 100 entrées gelées. Le mécanisme est correct : bloquant en CI, à réduire par correction, jamais à régénérer
- 7 marqueurs `TODO` dans `TrainingPackController` et `TournamentStatusManager`
- 5 fichiers > 900 lignes, décision de non-découpage assumée et argumentée
- `Club::ourClub()->first()` déréférencé sans garde dans une douzaine d'endroits — reste ouvert, non planifié

---

## Quick Wins (< 30 min)

1. **B1** — `|| $match->discrepancies !== []` dans `needsReview` · *15 min* · le meilleur rapport valeur/effort du lot
2. **S2** — inclure les noms dans les clés du `firstOrCreate` du tuteur externe · *15 min*
3. **B3** — confirmation avant de vider l'adresse d'un membre qui en avait une · *20 min*
4. **B4** — `max:2048` sur `importFile` · *2 min*
5. Ajouter `rector --dry-run` et `peck` à `test:lint` · *10 min*
6. `cropperjs` 1.6.2 → 2.x et `guzzle` 7 → 8 sont disponibles ; **rien de vulnérable**, à faire hors merge

---

## Recommandations avant production

1. Traiter **B1** — un correctif d'une ligne qui rend à la relecture la seule information qu'elle existe pour montrer.
2. Traiter **S1** — ne pas laisser une relation qui ouvre l'accès à des données de santé se déduire d'une chaîne de caractères, alors que la branche vient d'établir dans le fichier voisin que cette chaîne n'identifie pas une personne.
3. Réhabiliter les 4 tests de **S3** avec un acteur `membres`, ou les supprimer et écrire la vraie garde ailleurs. Un skip dont le motif est faux est pire qu'un test manquant : il annonce que le sujet est couvert.
4. **Dump de base obligatoire avant déploiement** — les migrations ne se rejouent pas à l'envers.
5. Après ce merge, dans l'ordre : Docker (~4 h), puis le découpage du bundle JS (~1 h), puis la réécriture des 13 tests de `Trainings/CreateTest`.

---

## Checklist finale

- [x] Suite complète verte (3 255 tests, Browser compris)
- [x] PHPStan sans nouvelle erreur · Pint conforme
- [x] Aucune vulnérabilité de dépendance (composer + npm)
- [x] Aucun secret, artefact, binaire ou `.env` committé
- [x] Aucun `dd()` / `dump()` / `console.log`
- [x] Aucune migration, aucune route, aucune dépendance modifiée
- [x] 0 commit de retard sur `main` (fast-forward propre)
- [x] Traductions `fr_BE` et `nl_BE` à jour pour les 7 nouvelles chaînes
- [ ] Divergences fédération/roster visibles sans replier un accordéon (**B1**)
- [ ] Tutelle non déductible d'une adresse seule (**S1**)
- [ ] Garde d'escalade `is_admin` sous test (**S3**)
- [ ] Sauvegarde de base vérifiée avant déploiement

---

## Recommandation

# ⚠️ Merge possible avec réserves

**Pourquoi possible** : le delta est petit, cohérent, sans migration, sans changement de route ni de dépendance, entièrement vert sur 3 255 tests dont environ 80 écrits pour ce travail précis. Le code est parmi le mieux commenté du dépôt : chaque décision non évidente porte le raisonnement qui l'a produite, et deux règles jumelles PHP/SQL sont explicitement testées l'une contre l'autre. Le risque de régression fonctionnelle est faible.

**Pourquoi avec réserves, et pas un ✅ sec** : les trois constats principaux sont tous du même genre — la branche a raison sur le fond et incomplète sur le bord.

- Elle établit qu'une adresse identifie un foyer et non une personne, puis fonde sur cette même adresse un lien de tutelle qui ouvre l'accès aux documents médicaux d'un mineur (**S1**).
- Elle sépare intelligemment les lignes à relire de celles qui ne demandent rien, mais oublie du critère la seule information qui motive une relecture — le désaccord entre le club et la fédération — pendant que l'import écrase justement les champs concernés (**B1**).
- Elle ajoute quinze tests qui appellent `->call('save')` avec succès, dans le fichier même où quatre tests restent désactivés au motif que `->call('save')` ne fonctionne pas — laissant la garde anti-escalade `is_admin` sans aucune preuve (**S3**).

Aucun de ces trois points n'est un défaut de conception : ce sont des angles morts à ~15, ~45 et ~45 minutes. **Corriger B1 et S1 avant le merge** (une heure à deux), **S3 juste après**, et la branche passe en ✅ sans réserve.

Si le merge doit partir maintenant, il est acceptable **à condition que le premier import fédéral réel n'ait pas lieu avant B1** — c'est lui qui écrit, et il écrit dans une transaction déjà validée quand on s'aperçoit du problème.
