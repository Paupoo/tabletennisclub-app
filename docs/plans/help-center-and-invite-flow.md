# Plan — Centre d'aide `/aide` & correction du flux d'invitation

> **Statut global :** 🟢 Tranche verticale livrée (non commitée) — chantier UI à faire en TDD
> **Branche :** `go_saison_2627`
> **Chef d'équipe :** Claude (supervision). Implémentations déléguées à des agents distincts, en TDD.
> **Dernière mise à jour :** 2026-07-17

Ce document est **vivant** : chaque agent met à jour le journal (§7) et coche les tâches.
Si on s'arrête, on reprend ici.

---

## 1. Contexte & objectif

Le comité ne sait pas se servir de l'application et pose toujours les mêmes questions. Un manuel existait déjà — `docs/manual-committee-fr.md`, 457 lignes — et **personne ne l'a jamais lu** : il vit sur GitHub, où aucun membre du comité ne va. Périmé de 133 commits, il annonce des invitations valables 48h alors que `User::INVITATION_LINK_VALIDITY_DAYS = 7`.

Le cercle vicieux à casser : **il est périmé parce que personne ne le lit, et personne ne le lit parce qu'il est introuvable.** Un document que personne n'ouvre, personne ne remarque qu'il ment.

Deux livrables couplés :

- **A — Centre d'aide dans l'app** (`/admin/aide`). La distribution est gratuite : le comité est déjà connecté. Des lecteurs réels qui râlent = la seule boucle de correction qu'on aura.
- **B — Correction du flux d'invitation & d'affiliation.** Écrire le premier article honnête a fait tomber trois défauts UI réels. Les documenter serait entériner des contournements.

---

## 2. Décisions verrouillées (issues du grilling)

| # | Sujet | Décision |
|---|-------|----------|
| 1 | Format | **Ni wiki ni PDF.** Markdown dans le repo, rendu dans l'admin. Les deux autres échouent sur la distribution, exactement comme `docs/`. |
| 2 | Anti-pourriture | La boucle = des lecteurs dans l'app. Aucune revue calendaire, aucun propriétaire nommé : ça a déjà échoué. |
| 3 | Contenu | Leurs vraies questions + tâches de bout en bout + **règles que l'écran cache**. **Aucun inventaire de champs** — c'est ce qui a tué le manuel précédent. |
| 4 | Champ vs règle | On documente `committee_role` effacé en silence (l'écran se tait). On ne documente **pas** le toggle licence verrouillé (l'écran l'explique déjà) — la doubler, c'est la faire diverger. |
| 5 | Organisation | **Une tâche = une page = écrite une fois**, taguée des rôles concernés. Le rôle est un filtre d'entrée, jamais un dossier. Les rôles s'additionnent (`DashboardController`) : six dossiers persona laisseraient le président sans réponse. |
| 6 | Langue | **FR urgent**, plomberie par locale dès le départ. Repli sur **FR**, pas sur `config('app.fallback_locale')` = `'en'` (aucune aide anglaise n'existe). |
| 7 | Première ouverture | **Ne va pas dans `/aide`** : celui qui demande « je fais quoi » est déjà perdu dedans. → dernière étape de l'assistant d'onboarding, qui les tient captifs et connaît leur rôle. |
| 8 | Observation utilisateurs | **Non.** Assumé : on écrit, on ajuste après. Corollaire : tout repose sur `/aide` étant réellement trouvable. |
| 9 | `docs/` | La doc technique reste. La doc « users » (10 manuels) migre vers `resources/help/`. **Nettoyage validé en fin de chantier, pas avant.** |
| 10 | Ordre docs/UI | Le chantier UI **réécrit** `creer-un-membre` et `affilier-un-membre`. Ces articles appartiennent au chantier (§4), pas au lot parallèle (§5). |

---

## 3. Les trois défauts (constatés dans le code, pas supposés)

### D1 — Le bouton « Créer » n'invite personne

- `⚡form/form.php` : `'password' => [$this->user?->exists ? 'nullable' : 'required', ...]` — **obligatoire à la création**.
- `CreateUserAction.php:44` : `if (! $hasPassword) { SendInvitationAction::handle($user); }` — **l'invitation ne part que si le mot de passe est vide**.
- Conséquence : le formulaire complet **n'envoie jamais d'invitation**. Le secrétaire doit inventer un mot de passe pour le compte d'autrui.
- Le blade n'affiche aucune mention type « laissez vide pour inviter ».
- `⚡form/form.blade.php` : les boutons **« Renvoyer l'invitation »** / **« Envoyer un lien de réinitialisation »** sont dans un `@if ($user)` — ils n'existent **qu'en modification**, donc invisibles au moment précis où on en a besoin.
- Le manuel actuel affirme « *un email d'invitation est envoyé automatiquement* ». **Faux.**

### D2 — Le chemin qui marche est caché

- `⚡index/index.blade.php` : le bouton **primaire** (`btn-primary`, `o-plus`) est **« Créer »** → formulaire complet → D1.
- **« Invitation rapide »** (`quickInvite()` : prénom + nom + e-mail → invitation envoyée) est **dans le dropdown `⋯`**.
- Le gros bouton évident est celui qui ne fait pas ce qu'ils veulent.

### D3 — Le panneau d'affiliation se présente comme réservé aux familles

- Bouton **« Inscrire un membre »** → drawer **« Inscription famille »** → *« Rechercher un membre à ajouter au **groupe** »* → *« Inscription de **groupe** réussie ! »*.
- Or `⚡registrations/registrations.php:844` cherche **tous les `users`**, sans aucune restriction de parenté.
- Un secrétaire qui veut affilier un adulte isolé sans Internet lit « famille » et referme.

> **Contrainte de test :** aucun test n'exige aujourd'hui le mot de passe à la création — tous les tests de `UserFormTest` sont en mode édition (`->set('password', '')` sur un user existant). Le chantier n'est pas verrouillé par la suite existante, mais **chaque incrément doit ajouter le test qui manquait**.

---

## 4. Chantier UI — en TDD

Chaque incrément : **rouge → vert → refactor → doc**. L'article d'aide fait partie du « done », il n'est pas une étape suivante.

### Incrément 1 — L'invitation redevient le défaut (D1)

- [ ] **Rouge** : test « créer un membre sans mot de passe envoie l'invitation » (`Mail::assertQueued(InviteNewUserMail::class)`).
- [ ] **Rouge** : test « créer un membre avec un mot de passe n'envoie pas d'invitation » (verrouille le comportement voulu).
- [ ] **Vert** : `password` devient `nullable` à la création. `CreateUserAction` est déjà correct — ne pas y toucher.
- [ ] Blade : hint explicite sur le champ (« Laissez vide pour envoyer une invitation — c'est le cas courant »).
- [ ] **Doc** : réécrire `resources/help/fr/creer-un-membre.md` — la section « Le bouton Créer » disparaît.

### Incrément 2 — Les actions d'invitation sortent du `@if ($user)` (D1)

- [ ] Décider : à la création, « Renvoyer l'invitation » n'a pas de sens (rien n'existe encore). Le vrai correctif est le hint de l'incrément 1 + un message de succès explicite (« Invitation envoyée à … »).
- [ ] **Rouge** : test sur le message de succès après création sans mot de passe.
- [ ] **Vert** + doc.

### Incrément 3 — Renommer le panneau d'affiliation (D3)

- [ ] Clés à changer (FR **et** NL, sinon `TranslationCoverageTest` casse) :
  - `Family Registration` → « Inscrire un ou plusieurs membres »
  - `Search for a member to add to the group...` → « Rechercher un membre… »
  - `Group registration successful!` → « Inscription enregistrée. »
- [ ] **Rouge** : test que le drawer affiche le nouveau libellé.
- [ ] **Doc** : retirer l'encadré « Passez outre » de `affilier-un-membre.md`.
- [ ] ⚠️ `familyBasket` / `saveFamilyRegistration()` gardent leur nom côté code — renommer est un refactor séparé, hors périmètre.

### Incrément 4 — Hiérarchie des boutons de création (D2)

> **Décision produit ouverte — à trancher avant de coder.**
> Recommandation : **« Inviter un membre » devient le bouton primaire**, le formulaire complet passe en action secondaire (« Encoder une fiche complète »). Le cas courant mérite le gros bouton ; encoder une fiche avec mot de passe est l'exception.

- [ ] Trancher.
- [ ] **Rouge** : test de présence/rôle des boutons.
- [ ] **Vert** + doc.

### Incrément 5 — Orientation en fin d'onboarding (décision #7)

- [ ] Dernière étape de `⚡onboarding` : « voilà ce que vous pouvez faire, voilà où est l'aide », en fonction du rôle (réutiliser `HelpAudience`).
- [ ] **Rouge** : test que l'écran final pointe vers `admin.help.index`.

---

## 5. Documentation — le lot réellement parallèle

Périmètre **intouché** par le chantier UI, donc rédigeable en même temps sans réécriture :

- [x] **Sélection d'équipe (capitaine)** — `composer-ma-selection.md`, écrit contre le code (2026-07-17). L'ancien `manual-captain-fr.md` décrivait colonnes et code couleur : rien n'en a été repris. Règles cachées récupérées : double-réservation d'un joueur sur la semaine, panneau muet sur un match passé, relance limitée aux non-répondants, notification restreinte aux ajoutés/retirés en modification.
- [x] **Vue sélectionneur** — `depanner-une-selection.md` (2026-07-17). **Pas un doublon** (décision #5) : c'est le même écran que le capitaine, l'article ne couvre que le delta et renvoie à `composer-ma-selection` pour la mécanique. Règles récupérées : la recherche de remplaçant filtre en silence par catégorie d'équipe (messieurs/dames/vétérans 40+ à la fin de saison) et exclut les compétiteurs déjà alignés ailleurs — l'ancien manuel prétendait qu'ils apparaissaient « avec un cadenas », c'est faux, ils sont absents.
- [x] **Trésorerie — rapprochement** : `rapprocher-les-paiements.md` (2026-07-17). Règles récupérées : l'import **ignore silencieusement** les lignes illisibles (`catch (Exception) { continue; }`) et n'annonce que le compte réussi ; l'auto-match exige communication structurée exacte **ET** montant au centime près.
- [x] **Amendes fédérales** — `infliger-une-amende.md` (2026-07-17). `⚡fines` + `IssueFine`, livré le matin même, jamais documenté. Règle capitale : **aucune marche arrière** (voir I3, §8).
- [x] **PV de réunion** — `rediger-le-pv-d-une-reunion.md` (2026-07-17). Refonte UX réunions vérifiée **déjà livrée** (commits `a0be43bd`→`5b2edd90`), donc pas de conflit d'ordre. Règles récupérées : ouvrir la page **prend le stylo automatiquement**, verrou périmé après `Meeting::MINUTES_LOCK_MINUTES = 10`, publication bloquée tant que la réunion n'a pas eu lieu **et** tant qu'on ne tient pas le stylo.
- [x] **Réunions — cycle de vie** : `organiser-une-reunion.md` (2026-07-17). Règles : sondage limité à **un envoi / 48 h** (refus silencieux avec rappel de la date), convocation bloquée tant que le statut n'est pas `CONFIRMED` **et** que la checklist n'est pas vide (ordre du jour, lieu si présentiel, lien si virtuel).
- [x] **Tournois — inscriptions & liste d'attente** : `comprendre-les-inscriptions-tournoi.md` (2026-07-17). Règles : promotion liste d'attente = 48 h pour confirmer ; délai de paiement = **date de clôture** (pas 72 h), 3 j si tardive, aucun le jour même. Défauts I5/I6 sortis d'ici.
- [x] **Tournois — le jour J** : `faire-tourner-un-tournoi.md` (2026-07-17). Règles : tableau final bloqué tant que les poules ne sont pas finies ; **taille du tableau déduite** de `nb_pools × nb_qualifiers_per_pool` (≥9 → 16ᵉˢ, ≥5 → 8ᵉˢ, sinon demies) ; clôture bloquée tant que tous les matchs ne sont pas encodés. Défaut I7 sorti d'ici.
- [x] **Trésorerie — extraits bancaires** : `importer-les-extraits-bancaires.md` (2026-07-17). A révélé que le **bon** import existe ici (empreinte, rapport, historique) et que celui des Paiements en est une copie dégradée → **I1 requalifié**. `rapprocher-les-paiements.md` corrigé en conséquence : il envoyait le trésorier sur le mauvais import. Défaut I8 sorti d'ici.
- [x] **Matériel / clés** : `suivre-le-materiel-confie.md` (2026-07-17). Angle « qui détient quoi » plutôt que champ-par-champ. Règle que l'ancien manuel n'explicitait pas : trousseau modifiable par **tout le comité** (fiche membre), mais détenteur de caisse réservé à **trésorier + admin** (pas le président). L'ancien `manual-equipment-fr.md` était exact sur les filtres — vérifié.
- [ ] Contenu du site : articles, événements, contacts — **à écrire après le nettoyage/refonte contenu ; I7 corrigé, mais l'écran admin de publication reste à documenter**

> **Règle non négociable, apprise à la dure :** chaque affirmation se vérifie dans le code avant d'être écrite. Sur les trois seuls articles déjà rédigés, deux affirmations « évidentes » se sont révélées fausses (l'affiliation par procuration existe bel et bien ; téléphone/rue/code postal/localité sont obligatoires). **La qualité vient de la vérification, pas de la rédaction.** Un article écrit de mémoire, c'est le fantôme de 457 lignes qui revient — juste ailleurs.
>
> Corollaire : ne pas viser « toute la documentation » d'un bloc. Livrer tâche par tâche, dans l'ordre de ce qu'ils demandent.

---

## 6. Definition of done

- [ ] `php artisan test --parallel --compact` au vert (paratest installé)
- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] `vendor/bin/phpstan analyse --memory-limit=512M` sans régression (baseline 713)
- [ ] Toute clé `__()` présente en **FR et NL** (`TranslationCoverageTest`)
- [ ] Aucun article d'aide ne décrit un écran champ par champ (décision #3)
- [ ] Nettoyage `docs/` (décision #9) — **en dernier**, avec réparation des liens depuis `docs/CHANGELOG.md:171-172` et `docs/plans/contact-triage-and-season-planning.md:129,165`

---

## 7. Journal de progression

- **2026-07-17** — 🟢 **Tranche verticale livrée (non commitée).** `app/Support/Help/` (`HelpArticle`, `HelpLibrary`, `HelpAudience`), `resources/help/fr/` (3 articles), `resources/views/pages/help/` (index + show), routes `admin.help.index` / `admin.help.show`, lien de nav, 4 clés FR+NL. **11 tests d'aide, 29 smoke, 42 arch — tout passe.** Pint OK, PHPStan propre sur `app/Support/Help`.
  Défauts D1/D2/D3 constatés pendant la rédaction du premier article — **le manuel a servi de détecteur, comme prévu (décision #3)**.
  Rien supprimé dans `docs/` : les 10 manuels sont intacts, nettoyage validé en fin de chantier.
- **2026-07-17** — 📝 4ᵉ article : `composer-ma-selection.md` (capitaine/sélectionneur/comité). Au passage, **`HelpAudience` avait raté le rôle `is_selector`** — un vrai flag sur `users`, exigé par le `mount()` de `⚡captain-selection`. Corrigé + testé. 12 tests d'aide, 42 arch, Pint OK.
- **2026-07-17** — 📝 Articles 5 et 6 : `depanner-une-selection.md`, `rapprocher-les-paiements.md`. **6 articles, 12 tests d'aide + 42 arch au vert.** Deux défauts de plus repérés en chemin, à arbitrer (§8).
- **2026-07-17** — 📝 Articles 7 et 8 : `infliger-une-amende.md`, `rediger-le-pv-d-une-reunion.md`. **8 articles.** Suite complète vérifiée au vert avant ce lot (**2330 passed**). Deux défauts de plus (I3, I4) — **I3 est le plus grave repéré jusqu'ici** : une amende ne s'annule pas.
- **2026-07-17** — 📝 Articles 9 et 10 : `organiser-une-reunion.md`, `comprendre-les-inscriptions-tournoi.md`. **10 articles.** Défauts I5 et I6. **Bilan du détecteur : 10 articles → 6 défauts**, dont deux qui touchent l'argent (I1 import muet, I3 amende irréversible) et un qui fait perdre sa place à un membre sans le prévenir (I5). Plan d'action sur les défauts : **à faire plus tard, décidé avec l'auteur.**
- **2026-07-17** — 📝 Article 12 : `importer-les-extraits-bancaires.md`. **12 articles, 8 défauts.** Leçon de la journée : **un article déjà écrit peut être faux.** `rapprocher-les-paiements.md` envoyait le trésorier sur l'import cassé, faute d'avoir vu que le bon existait une page plus loin. Corrigé. C'est exactement le mécanisme qui a tué `docs/manual-committee-fr.md` — sauf qu'ici la correction a coûté cinq minutes parce que le texte vit dans le repo, à côté du code.
- **2026-07-17** — ✅ **I7 corrigé en TDD** (fuite d'articles non publiés sur le site public). Détail dans le bandeau §8. Premier défaut de la série effectivement réparé — code applicatif touché (contrôleur, modèle, composant, factory) + 6 tests de non-régression.
- **2026-07-17** — 📝 Article 13 : `suivre-le-materiel-confie.md`. **13 articles. Lot parallèle bouclé** (reste seulement « contenu du site », volontairement en attente — dépend de la refonte contenu + écran admin de publication). **Bilan détecteur : 13 articles → 8 défauts, 1 corrigé (I7), 7 en attente d'arbitrage.** Prochaine étape non technique : **retour du comité** pour prioriser le chantier UI (§4) et le plan d'action défauts (§8).
- **2026-07-17** — ✅ **I5 corrigé en TDD** (inscription tournoi non payée annulée sans prévenir). `expirePaymentDeadlines()` notifie le membre (nouvelle `TournamentPaymentExpiredNotification`, calquée sur la liste d'attente) : sa place a été libérée faute de paiement. 3 tests, 2 clés FR+NL, article `comprendre-les-inscriptions-tournoi.md` mis à jour. Détail §8.
- **2026-07-17** — ✅ **I4 corrigé en TDD** (ouvrir le PV volait le stylo). `mount()` n'acquiert plus le verrou ; il se prend à la première frappe (`claimPen()`). Champs éditables quand le stylo est libre, lecture seule si un autre écrit. 5 tests de verrou (2 anciens réécrits), article `rediger-le-pv-d-une-reunion.md` mis à jour. Détail §8.
- **2026-07-17** — ✅ **I3 corrigé en TDD** (amende irréversible — le plus grave de la série). Nouvelle action `CancelFine` : annule le paiement en attente, soft-delete la `Fine`, notifie le membre (+ tuteurs). Amende déjà payée refusée. UI `⚡fines` : « Annuler cette amende » + modale récap. 7 tests, `FineCancelledNotification` + mail, 10 clés FR+NL, article `infliger-une-amende.md` réécrit. Détail §8.
- **2026-07-17** — ✅ **I2 corrigé en TDD** (recherche de remplaçant : filtrage muet). Sur résultat vide, `⚡captain-selection` explique désormais pourquoi (catégorie / joueur déjà aligné cette semaine). 4 tests, 5 clés FR+NL, article `depanner-une-selection.md` mis à jour. Détail §8.
- **2026-07-17** — ✅ **I1 corrigé en TDD** (import bancaire dégradé sur la page Paiements). L'import cassé est retiré de la page Paiements ; l'action renvoie vers Trésorerie → Transactions, seul import correct. 2 tests de non-régression, avertissement retiré de `rapprocher-les-paiements.md`. Détail dans le bandeau §8. Deuxième défaut de la série effectivement réparé.
- **2026-07-17** — 📝 Article 11 : `faire-tourner-un-tournoi.md`. **11 articles.** Et **I7** : documenter la clôture de tournoi a mené à `closeTournament()` → `NewsPost(is_public: false)` → au contrôleur public, qui ne filtre rien. **Fuite de brouillons prouvée par test.** Premier défaut de la série qui ne soit pas qu'une gêne d'usage. L'article `faire-tourner-un-tournoi.md` porte un avertissement en attendant le correctif — **à retirer une fois I7 traité**.

---

## 8. Défauts repérés par la rédaction — à arbitrer

Ni corrigés ni planifiés : ils sont sortis en documentant, hors périmètre du chantier §4.

> ### ✅ I7 — Fuite d'articles non publiés sur le site public — **CORRIGÉ le 2026-07-17**
>
> **Fait, en TDD.** Scope `NewsPost::scopePubliclyVisible()` (`status = PUBLISHED` ET `is_public = true`), appliqué dans `PublicNewsPostController::show()` (article + `relatedArticles`) et `ArticleList` (liste + `resolveDefaultSeasonId`). Factory `NewsPost` : défaut `is_public => true` (un article de factory est publié *et* visible). 6 tests de non-régression dans `tests/Feature/ClubPosts/PublicNewsPostVisibilityTest.php`. Suite complète verte (2335 passed ; un test navigateur d'auth flaky sous parallèle, vert en isolé). Dette laissée intacte : `getFullArticleContent()` (méthode morte, ~50 lignes de contenu prototype d'un autre club) — signalée, pas touchée, hors périmètre sécurité.
>
> <details><summary>Constat d'origine (conservé)</summary>
>
> `PublicNewsPostController::show()` fait `NewsPost::whereSlug($slug)->firstOrFail()` — **sans filtrer ni sur `status`, ni sur `is_public`**. Aucun global scope sur le modèle (`boot()` ne calcule que `reading_time`). La route `GET /clubPosts/{slug}` est dans le groupe public, sans authentification.
>
> **Prouvé par test le 2026-07-17** : un `NewsPost` en `status = DRAFT` et `is_public = false` est servi en 200 à un visiteur anonyme, titre et contenu compris. (Test de vérification supprimé après coup — à réintroduire comme test de non-régression lors du correctif.)
>
> Deux problèmes distincts :
> 1. **Brouillons et archives lisibles.** La liste publique (`ArticleList.php:72`) filtre bien sur `PUBLISHED`, donc ils ne sont pas listés — mais l'URL directe les sert quand même. Non listé ≠ protégé.
> 2. **`is_public` ne sert à rien côté public.** Ni la liste ni la fiche ne le regardent. Or `closeTournament()` crée son article avec `is_public => false` **et** `status => PUBLISHED` : l'intention « réservé aux membres » est explicite dans le code, et le site public l'ignore. Le slug est en plus prévisible (`Str::slug($titre . '-' . année)`).
> 3. `relatedArticles` (même contrôleur) ne filtre pas non plus — un brouillon peut apparaître en lien « article similaire » sur une page publique.
>
> **Enjeu :** un brouillon en cours de rédaction — départ d'un membre, sujet disciplinaire, communication non arbitrée — est lisible par n'importe qui. C'est le seul défaut de cette liste qui ne soit pas qu'une gêne.
> </details>

> ### ✅ I1 — Import bancaire dégradé sur la page Paiements — **CORRIGÉ le 2026-07-17**
>
> **Fait, en TDD.** L'import cassé est **retiré de la page Paiements** : `processImport()` supprimé, avec ses propriétés (`importFile`, `importModal`), ses helpers (`parseAmount`, `parseDate`, `normalizeHeader`), le trait `WithFileUploads` et le modal d'import du blade. L'action « Importer un relevé bancaire » (desktop + mobile) **renvoie désormais vers Trésorerie → Transactions**, seul import correct (empreinte SHA-256, comptage new/duplicate/error, `failed_rows`, journal `BankImport`). 2 tests de non-régression dans `TreasuryPaymentsTest.php` (`processImport` n'existe plus ; la page pointe vers l'import Transactions). `rapprocher-les-paiements.md` : encadré d'avertissement retiré, §1 simplifié.
>
> <details><summary>Constat d'origine (conservé)</summary>
>
> **La page Paiements héberge une copie dégradée de l'import bancaire.** `⚡payments/processImport()` : `catch (Exception) { continue; }` (lignes perdues en silence, seul `$importedCount` est annoncé) **et aucune déduplication** — réimporter le même extrait recrée tout en double. **Or `⚡transactions/processImport()` fait tout correctement** : `import_fingerprint` SHA-256, comptage new/duplicate/error, `failed_rows` (ligne + données + motif), journal `BankImport`, toast en `warning` dès qu'il y a une erreur. Deux imports vers la même table `transactions`, un bon, un piège. **Enjeu : intégrité comptable.**
> </details>

> ### ✅ I2 — Recherche de remplaçant : filtrage muet — **CORRIGÉ le 2026-07-17**
>
> **Fait, en TDD.** Quand la recherche de remplaçant ne renvoie rien alors que des compétiteurs correspondaient au nom, `⚡captain-selection` affiche désormais **un message expliquant pourquoi** : catégorie d'équipe (messieurs/dames/vétérans 40+) et/ou joueurs déjà sélectionnés ici ou alignés ailleurs cette semaine. Refactor du bloc de recherche : les noms correspondants sont récupérés avant filtrage (`buildSearchNote()`), la note n'apparaît que sur résultat vide. Le cas « membre récréatif » n'est pas couvert par le message (il n'entre jamais dans `competitor()`) — documenté tel quel dans `depanner-une-selection.md`. 4 tests (catégorie, alignement, silence quand aucun nom ne correspond, remplaçant éligible toujours renvoyé). 5 clés FR+NL.
>
> <details><summary>Constat d'origine (conservé)</summary>
>
> **Recherche de remplaçant : filtrage muet.** `⚡captain-selection` filtre par catégorie d'équipe (genre, 40+ pour vétérans) et retire les joueurs déjà alignés — sans un mot. Un sélectionneur cherche un joueur dont il est sûr, n'obtient rien, et n'a aucune explication.
> </details>

> ### ✅ I3 — Une amende était irréversible — **CORRIGÉ le 2026-07-17**
>
> **Fait, en TDD.** Nouvelle action `CancelFine` : annule le paiement en attente (`status = 'cancelled'`), **soft-delete** la `Fine` (le modèle avait déjà `SoftDeletes`), et **notifie le membre** (+ tuteurs si mineur) via `FineCancelledNotification` (mail + database) qu'il n'a plus rien à payer. Une **amende déjà payée est refusée** (`DomainException`) — elle relève du remboursement, pas de l'annulation. UI `⚡fines` : menu ⋯ « Annuler cette amende » (masqué sur une amende payée) + modale récapitulative (membre, motif, montant). Une fois annulée, l'amende disparaît de la liste trésorier **et** de l'espace membre (le `whereHasMorph` respecte le soft-delete). 7 tests (action + page), 10 clés FR+NL, article `infliger-une-amende.md` réécrit (l'encadré « pas de marche arrière » devient « validation immédiate + annulation possible »). **Convention respectée** : pas de classe d'exception maison (l'arch test interdit `Throwable` hors `App\Exceptions`) → `\DomainException`.
>
> <details><summary>Constat d'origine (conservé)</summary>
>
> **Une amende est irréversible.** `IssueFine` est la **seule** action du domaine : pas d'annulation, pas de suppression, pas de brouillon. Valider crée le paiement **et** notifie le membre (+ ses tuteurs s'il est mineur) d'un bloc. **Le trésorier se trompera de membre un jour** et n'aura aucun recours dans l'app.
> </details>

> ### ✅ I4 — Ouvrir le PV volait le stylo — **CORRIGÉ le 2026-07-17**
>
> **Fait, en TDD.** `mount()` n'acquiert plus le verrou : ouvrir la page (même sur un stylo périmé) ne dépossède plus personne. Le verrou est pris **à la première modification** via un helper `claimPen()` (`acquireMinutesLock` non-forcé) partagé par `persistDraft()`, `toggleDiscussed()` et `publishMinutes()` — bloqué seulement si un autre le tient en direct. Le blade rend les champs éditables quand le stylo est libre/à soi (`$this->lockHolder && ! $this->holdsLock` en garde de `<fieldset>`, agenda, bouton publier), sinon lecture seule. 5 tests de verrou réécrits/ajoutés (dont les 2 anciens qui encodaient le bug : « premier à ouvrir prend le verrou » et « verrou périmé repris à l'ouverture »). PHPStan 23→22 sur le fichier. Pas de nouvelle clé i18n. Article `rediger-le-pv-d-une-reunion.md` mis à jour.
>
> <details><summary>Constat d'origine (conservé)</summary>
>
> **Ouvrir le PV vole le stylo.** `⚡minutes/minutes.php:mount()` appelle `acquireMinutesLock()` sans rien demander : venir lire dépossède celui qui écrivait. Mineur (se répare seul en 10 min, reprise possible), mais surprenant.
> </details>

> ### ✅ I5 — Inscription tournoi non payée annulée sans prévenir — **CORRIGÉ le 2026-07-17**
>
> **Fait, en TDD.** `expirePaymentDeadlines()` notifie désormais le membre via une nouvelle `TournamentPaymentExpiredNotification` (mail + database), calquée sur `TournamentConfirmationExpiredNotification` de la liste d'attente — l'asymétrie est levée. Le message explique que la place a été libérée **faute de paiement à temps** (générique « inscription annulée » aurait laissé le membre dans le flou). Réutilise 5 clés existantes, 2 nouvelles (FR+NL). 3 tests (notifié à l'expiration, pas de notif quand rien n'expire, rendu e-mail en locale FR). Article `comprendre-les-inscriptions-tournoi.md` mis à jour (l'encadré « ne sera pas averti » disparaît).
>
> <details><summary>Constat d'origine (conservé)</summary>
>
> **Une inscription tournoi non payée est annulée sans prévenir le membre.** `expirePaymentDeadlines()` fait un `update(['registration_status' => 'cancelled'])` brut puis `openSpot()` — **aucune notification**, contrairement à `expireConfirmationDeadlines()` juste au-dessus. Le membre reçoit des rappels **avant**, puis perd sa place en silence et le découvre le jour du tournoi.
> </details>

| # | Constat | Enjeu |
|---|---|---|
| I8 | **La tuile et le filtre « non rapprochées » se contredisent.** `⚡transactions/stats()` compte `doesntHave('payment')->where('amount', '>', 0)` (crédits seuls) ; le filtre `reconciledFilter === 'unreconciled'` fait `doesntHave('payment')` tout court, débits compris. La tuile annonce 12, le filtre en montre 40. | Mineur mais fait douter des chiffres — le pire effet possible sur un outil comptable. Correctif : aligner le filtre sur la stat (`amount > 0`). |
| I6 | **Commentaire de code trompeur.** `expirePaymentDeadlines()` annonce « 72h window » ; la vraie règle (`TournamentService:313-318`) est : délai = **la date de clôture des inscriptions**, sauf inscription tardive (3 j) ou inscription le jour même (aucun délai). | Piège pour le prochain qui lira le code — moi compris, je m'y suis fié avant de vérifier. Correctif : corriger le commentaire. |
