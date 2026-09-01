# Plan — états d'un tournoi (options B et C)

> **Statut global :** 🟢 A livrée · **B livrée intégralement (2026-08-29, non commitée)** · C1 et C3 livrées, C2 classée sans suite
> **Branche :** `develop`
> **Issue d'origine :** #35, fermée le 2026-08-28 (symptôme corrigé) · **Issue dérivée, corrigée aussi :** #81
>
> Ce document survit à la fermeture du ticket : c'est lui qui porte B et C.
> **Carte d'analyse (lecture) :** <https://claude.ai/code/artifact/1ef13c8d-e353-4c44-964e-5be48520c182>
> **Dernière mise à jour :** 2026-08-29

Ce document est **exécutable sans refaire l'analyse** : chaque inventaire a été vérifié par
recherche d'appelants, production et tests inclus. Les commandes de vérification (§6) sont à
relancer avant de commencer, pour confirmer que rien n'a bougé depuis.

---

## 0. Ordre recommandé

**B avant C.** B rend les transitions traçables en un seul endroit ; C devient alors une
modification locale plutôt qu'une chasse à travers 63 fichiers.

---

## 1. Ce qui est déjà fait (option A, commit `666058ee`)

Aucun statut ajouté, supprimé ni migré. Trois gestes :

- Un bouton **« Ouvrir les inscriptions »** à l'étape 4 du wizard, qui fait
  `locked → published` explicitement. Le même bouton rouvre depuis `setup`.
- **L'envoi d'invitations exige désormais `published`.** L'ancienne promotion implicite
  `locked → published` au premier envoi a été supprimée — c'était la cause de #35.
- Libellés côté comité alignés sur ce que chaque statut *fait*, et les deux axes
  (statut d'inscription / article) affichés côte à côte sur la carte.

Tests : `TournamentWizardIntegrationTest`, blocs `opening registrations` et
`inviting members` (7 tests).

---

## 2. État des lieux vérifié

### 2.1 Les sept statuts et leur rôle réel

| Statut | Rôle | Inscriptions membre |
|---|---|---|
| `draft` | configuration libre, nom et prix modifiables | non |
| `locked` | validé, nom et prix figés, jamais encore ouvert | non |
| `published` | **seul statut où un membre peut s'inscrire** | oui |
| `setup` | inscriptions closes, poules et matchs en préparation | non |
| `pending` | tournoi en cours | non |
| `closed` | terminé | non |
| `cancelled` | annulé | non |

### 2.2 Les huit écritures du statut

Toutes en `update(['status' => …])` direct, aucune ne passe par une machine à états.

| # | Emplacement | Transition |
|---|---|---|
| 1 | `⚡wizard/wizard.php:1493` (`validateAndLock`) | `draft → locked` |
| 2 | `⚡wizard/wizard.php:403` (`confirmOpenRegistrations`) | `locked\|setup → published` |
| 3 | `⚡wizard/wizard.php:373` (`confirmCloseRegistrations`) | `published → setup` |
| 4 | `⚡wizard/wizard.php:970` (`processLaunch`) | `setup → pending` |
| 5 | `⚡wizard/wizard.php:242` | `* → cancelled` |
| 6 | `⚡live-center/live-center.php:154` | `pending → closed` |
| 7 | `⚡index/index.php:51` (action groupée) | `* → cancelled` |
| 8 | `CloseRegistrationsByDeadlineCommand.php:25` (planifiée) | `published → setup` |

Plus les **seeders** (`database/seeders/TournamentSeeder.php`), qui posent le statut à la
création — donc hors périmètre d'une machine à états sur les transitions.

### 2.3 Les lectures qui comptent

Au-delà de l'affichage, sept endroits font dépendre un comportement du statut :

- `TournamentPolicy:35` — modification interdite si `pending`
- `TournamentPolicy:59` et `:67` — actions réservées à `published`
- `TournamentService:287` — inscription refusée hors `published`
- `UserCalendarService:164` — le calendrier ne montre que `published`
- `DashboardController:307` — exclut `cancelled`
- `⚡event-subscription:336` — « Mes inscriptions » ne montre que `published`
- `TournamentObserver` — annonce la première ouverture (`draft|locked → published`) ; corrigé par #81

### 2.4 Le code mort (vérifié : zéro appelant en production)

| Élément | Fichier | Défaut connu |
|---|---|---|
| `TournamentStatusManager` | `app/Domains/Competitions/Tournament/Services/` | table de transitions sans cas `setup` → `UnhandledMatchError` si appelé sur un tournoi en configuration ; place `locked` là où les classes `State` placent `setup` |
| `TournamentStateMachine` | `app/Domains/Shared/States/Tournament/` | ~~`refreshState()` écrase la transition avant enregistrement~~ — **ce défaut n'existait pas.** Les sept classes `State` font toutes `save()` avant que `refreshState()` ne relise. Vérifié par test le 2026-08-29 : la transition survit en base, la machine pointe sur le nouvel état, une transition interdite lève sans rien écrire. Aucun correctif n'a été nécessaire |
| `TournamentStateFactory` + 7 classes `State` | `app/Domains/Shared/States/Tournament/` | `cancelled` mappé sur `LockedState` (donc `CancelledState` jamais instanciée) ; `LockedState::getStatus()` retourne `CANCELLED` |
| `Tournament::state()` | `Models/Tournament.php:226` | aucun appelant |

**Deux défauts que cette analyse avait manqués** (trouvés en implémentant B, le 2026-08-29) :

- `LockedState::getAllowedTransitions()` retournait `[]` et la classe n'avait ni `publish()`
  ni `cancel()`. La transition `locked → published` livrée par l'option A était donc
  **inexprimable** par la machine — l'étape 4 était plus lourde que « compléter la table ».
- `PendingState::close()` faisait `$tournament->matches()->whereNot('completed')` : un
  `whereNot()` à un seul argument, sans colonne. La garde de clôture ne comptait pas les
  matchs non terminés. Jamais exécutée, la classe étant morte.

**Tests trompeurs à connaître.** `TournamentStatusManager` est couvert par *deux* fichiers
de tests quasi identiques — `tests/Unit/Competitions/TournamentStatusManagerTest.php` et
`tests/Feature/Competitions/Tournament/TournamentStatusManagerTest.php`. Ils sont verts et
ne protègent rien, puisque la classe n'est jamais appelée. Ne pas les lire comme une
garantie de non-régression.

---

## 3. Option B — brancher une seule machine à états

### 3.1 Objectif

Une seule autorité sur les transitions, appelée par les huit sites d'écriture, portant
les gardes métier qui n'existent aujourd'hui nulle part (« un match a déjà commencé »).

### 3.2 Décision préalable à prendre

**Laquelle des deux abstractions garder ?**

Recommandation : **partir des classes `State`, supprimer `TournamentStatusManager`.**

- Les classes `State` connaissent `setup`, `TournamentStatusManager` non — leur table est
  celle qui correspond au parcours réel du wizard.
- Elles portent déjà les prédicats utiles (`canRegisterUsers`, `canCreatePools`,
  `canGenerateMatches`, `canStartMatches`, `canModifyPools`), qui remplaceraient les
  computed ad hoc du wizard.
- `TournamentStatusManager` n'apporte que les gardes « match commencé », faciles à
  reporter (une vingtaine de lignes).

### 3.3 Étapes

1. **Supprimer** `TournamentStatusManager` et ses deux fichiers de tests. Sans appelant,
   il n'y a rien à préserver ; garder les tests reviendrait à figer une table de
   transitions fausse (pas de `setup`).

2. **Corriger les trois défauts des classes `State`** — chacun est indépendant et
   testable isolément :
   - `TournamentStateFactory` : mapper `CANCELLED` sur `CancelledState`, pas `LockedState`.
   - `LockedState::getStatus()` : retourner `LOCKED`.
   - `TournamentStateMachine::refreshState()` : enregistrer avant de relire, ou remplacer
     `$tournament->refresh()` par une réaffectation de l'état depuis le statut en mémoire.
     Le test doit vérifier que la transition **survit en base**, pas seulement en mémoire —
     c'est précisément ce que l'implémentation actuelle rate.

3. **Reporter les gardes métier** de `TournamentStatusManager` dans les classes `State`
   concernées : refuser `pending → locked` et `pending → cancelled` si au moins un match
   est en `in_progress` ou `completed`.

4. **Compléter la table de transitions** pour qu'elle décrive le parcours réel, y compris
   `locked → published` (livré en A) et l'annulation depuis `draft`, `locked`, `published`
   et `setup`.

5. **Faire passer les huit écritures** par la machine, une par une, chacune avec son test.
   Commencer par `validateAndLock` et `confirmOpenRegistrations` : ce sont celles couvertes
   par les tests d'A, donc le filet est déjà là.

6. **Remplacer les computed du wizard** (`isContractLocked`, `isLaunched`,
   `registrationClosed`, `registrationsOpen`, `canOpenRegistrations`) par les prédicats de
   l'état, une fois toutes les écritures passées par la machine.

### 3.4 Critères d'acceptation

- `grep -rn "update(\['status' => TournamentStatusEnum" app resources/views` ne renvoie
  plus que la machine à états elle-même.
- Un test prouve qu'une transition interdite lève, et qu'une transition autorisée est
  **relue depuis la base** avec le nouveau statut.
- Un test prouve qu'un tournoi `pending` avec un match commencé ne peut plus être annulé.
- `TournamentStatusManager` et ses tests n'existent plus.
- Suite complète verte (`php artisan test --parallel --exclude-testsuite=Browser`).

### 3.5 Risques

- **Faible.** Le code ajouté remplace des `update()` directs par des appels équivalents ;
  le comportement observable ne change que là où une garde nouvelle refuse une transition
  qui passait avant. Recenser ces cas explicitement avant de livrer.
- Les tests existants du wizard couvrent les transitions principales : les lancer à chaque
  étape, pas seulement à la fin.

---

## 4. Option C — réduire le nombre de statuts

### 4.1 Ce qui est fusionnable, et ce qui ne l'est pas

`locked` et `setup` décrivent tous deux « configuré, inscriptions fermées ». La différence
est **temporelle** : `locked` est *avant* toute ouverture, `setup` est *après* une clôture.

Cette différence n'est pas cosmétique — elle porte trois comportements distincts :

| | `locked` | `setup` |
|---|---|---|
| Poules et matchs | non générables | générables |
| Libellé du bouton d'ouverture | « Ouvrir » | « Rouvrir » |
| Annonce aux membres | première ouverture | réouverture, ne doit pas ré-annoncer |

**Conclusion : ne pas fusionner `locked` et `setup`.** Un seul statut obligerait à
retrouver l'information perdue par un autre moyen (une date de première ouverture, un
booléen), ce qui déplace la complexité sans la réduire.

### 4.2 Ce qui est réellement supprimable

Un seul statut est un doublon : **aucun**. Mais deux ménages sont possibles et utiles.

**C1 — supprimer la classe `CancelledState` ou la brancher.**
Traité par l'étape 2 de B. Après B, ce point est clos.

**C2 — clarifier `closed` vs `cancelled`.**
Les deux sont terminaux et sans transition sortante. Ils sont distincts (terminé / annulé)
et la distinction est visible au comité. **À conserver.**

### 4.3 La vraie simplification : nommer l'axe manquant

L'analyse a montré que la confusion de #35 ne vient pas du *nombre* de statuts mais du
fait que **deux axes indépendants partagent le mot « publié »**. A l'a traité côté
libellés. La simplification structurelle correspondante serait de faire porter au modèle
ce que l'interface dit déjà :

```php
// Sur Tournament — un prédicat, pas un statut de plus.
public function registrationsAreOpen(): bool;   // status === PUBLISHED
public function isOnPublicWebsite(): bool;      // eventPost?->status === PUBLISHED
```

Les 71 lectures dispersées de `status` cesseraient alors de comparer des valeurs d'enum à
la main. C'est un remplacement mécanique, sans migration, et il rend l'option C1 inutile.

### 4.4 Recommandation

**Ne pas exécuter C comme une réduction du nombre de statuts.** Les sept sont justifiés.
Exécuter à la place :

1. C1 via l'étape 2 de B (gratuit).
2. C3 — les deux prédicats de la section 4.3, puis remplacer les lectures directes.

Aucune migration de données. Aucun risque de retour en arrière ambigu.

> **Si le club veut malgré tout fusionner `locked` et `setup`** : la migration est
> `UPDATE tournaments SET status = 'setup' WHERE status = 'locked'`, mais elle **détruit**
> l'information « jamais ouvert », dont dépend l'annonce de #81. Il faudrait alors ajouter
> une colonne `first_opened_at` dans la même migration, et la renseigner à `NULL` pour les
> lignes converties — ce qui les rendrait indistinguables des tournois réellement jamais
> ouverts. C'est irréversible. Ne pas faire sans un besoin exprimé.

---

## 5. Ordre d'exécution proposé

1. ~~**#81** (annonce jamais envoyée)~~ — **fait**. Déclencheur corrigé
   (`draft|locked → published`, une réouverture ne ré-annonce pas), audience ramenée à
   `User::active()`, envoi éclaté sur un job throttlé partageant le limiteur `invitations`.
2. **B étape 1** — supprimer `TournamentStatusManager` et ses tests.
3. **B étapes 2 à 4** — réparer et compléter les classes `State`.
4. **B étapes 5 et 6** — brancher les huit écritures, puis les computed.
5. **C1** — clos par B étape 2.
6. **C3** — les deux prédicats et le remplacement des lectures.

Chaque étape est committable seule et laisse la suite verte.

---

## 5 bis. Journal d'exécution

### 2026-08-29 — B intégrale, C1 et C3 (non commité)

**Filet.** Baseline avant de toucher quoi que ce soit : 3391 passed, 29 skipped, 7 todos,
0 échec. Après : **3423 passed, 0 échec**. PHPStan **No errors**. Pint propre.

**Étape 1 — le code mort est parti.** `TournamentStatusManager` et ses deux fichiers de
tests supprimés (15 tests verts qui ne protégeaient rien). Son entrée dans
`phpstan-baseline.neon` — le `match.unhandled` sur `SETUP`, exactement le défaut décrit
au §2.4 — est partie avec : le baseline passe de 78 à 77 entrées.

**Étape 2 — les classes `State` réparées.** `TournamentStateFactory` mappe `CANCELLED` sur
`CancelledState` ; `LockedState::getStatus()` retourne `LOCKED`. Le troisième défaut annoncé
n'existait pas (voir §2.4). Aucun test ne couvrait ces classes : `TournamentStateMachineTest`
est écrit de zéro, 37 tests.

**Étape 3 — gardes métier reportées.** `PendingState::cancel()` refuse désormais si un match
est `in_progress` ou `completed` ; la garde de `setUp()` et celle de `cancel()` partagent
`refuseIfPlayHasBegun()`. Le `whereNot('completed')` cassé de `close()` est corrigé.

**Étape 4 — la table décrit le parcours réel.** Nouveau verbe `lock()` (le `draft → locked`
du wizard n'avait aucun verbe). `LockedState` gagne `publish()` et `cancel()`. `DraftState`
gagne `cancel()` et `lock()`, et **perd `publish()`** : le wizard ne publie jamais un
brouillon, il le valide d'abord. Effet de bord voulu — `canOpenRegistrations` devient
exactement `canTransitionTo(PUBLISHED)`.

**Étape 5 — les huit écritures passent par la machine.**
`grep -rn "update(\['status' => TournamentStatusEnum" app resources/views` ne renvoie plus
rien. Trois décisions prises avec l'auteur, toutes des changements de comportement assumés :

| Site | Garde nouvelle | Décision |
|---|---|---|
| `confirmCloseRegistrations` + `tournament:close-registrations` | refus si zéro inscrit | **Garde conservée partout.** Un tournoi que personne n'a rejoint ne se referme plus : il faut l'annuler. La commande nocturne le signale (`Left open: …`) et poursuit sa boucle au lieu d'avorter. |
| `cancelTournament` | refus si un match a été joué, ou si déjà clos | **Refus + message à l'écran**, deux messages distincts (joué / déjà terminé). |
| `bulkCancel` | idem, par tournoi | Annule ce qui peut l'être, **compte et annonce** ce qui a été écarté. L'`UPDATE` de masse qui aplatissait tout, tournois clos compris, a disparu. |

Un test existant a dû être adapté : `tournament:close-registrations` fermait un tournoi sans
aucun inscrit. Il attache désormais un inscrit, et deux tests neufs couvrent le refus.

**Étape 6 — les computed du wizard lisent l'état.** Les cinq comparaisons d'enum à la main
sont parties. Deux prédicats ont dû être ajoutés à l'interface, les trois autres existaient :

| Computed | Devient |
|---|---|
| `registrationsOpen` | `state()->canRegisterUsers()` |
| `registrationClosed` | `state()->canCreatePools()` |
| `canOpenRegistrations` | `state()->canTransitionTo(PUBLISHED)` |
| `isContractLocked` | `state()->hasLockedContract()` *(nouveau)* |
| `isLaunched` | `state()->hasBeenLaunched()` *(nouveau)* |

**C1** — close par l'étape 2 : `CancelledState` est instanciée.
**C3** — `Tournament::registrationsAreOpen()`, `Tournament::isOnPublicWebsite()` et le scope
`registrationsOpen()` existent ; les 10 lectures dispersées de `PUBLISHED` (policy, service,
contrôleur, calendrier, commande, 4 vues) passent par eux. Les statuts restent **sept**,
conformément au §4.4.

**Piège rencontré, à retenir.** Les composants `⚡` Livewire compilent dans le **namespace
global** : `use InvalidArgumentException;` y déclenche
`The use statement with non-compound name has no effect` et casse tout l'écran. Attraper ces
exceptions dans un `⚡` exige `catch (\InvalidArgumentException)`.

**Pas encore fait :** la suite `Browser` (dont `TournamentFlowTest`) n'a pas tourné — un
`composer run dev` était actif et `public/hot` doit disparaître avant. À lancer via
`composer test` quand la stack est arrêtée.

---

## 6. Commandes de vérification à relancer avant de commencer

```bash
# Les écritures du statut sont-elles toujours huit, aux mêmes endroits ?
grep -rn "update(\['status' => TournamentStatusEnum" app resources/views

# Le code mort l'est-il toujours ?
grep -rn "TournamentStatusManager\|TournamentStateMachine" app resources/views
grep -rnF -- "->state()" app resources/views

# Combien de fichiers touchent le statut ?
grep -rln "TournamentStatusEnum" app resources/views database tests | wc -l   # 63 au 2026-08-28

# Filet avant de toucher quoi que ce soit
php artisan test --parallel --exclude-testsuite=Browser
```

> La suite `Browser` refuse `--parallel` (voir `tests/Trait/RefusesParallelExecution.php`) :
> la lancer séparément via `composer test` si nécessaire.
