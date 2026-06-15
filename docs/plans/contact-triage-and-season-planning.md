# Plan — Triage des contacts, templates de réponse & planification de saison

> **Statut global :** 🟡 Plan validé en grilling — implémentation pas commencée
> **Branche :** `go_saison_2627`
> **Chef d'équipe :** Claude (supervision). Implémentations déléguées à des agents distincts, en TDD.
> **Dernière mise à jour :** 2026-06-15

Ce document est **vivant** : chaque agent met à jour le journal de progression (§9) et coche les tâches.
Si on s'arrête, on reprend ici.

---

## 1. Contexte & objectif

Le secrétaire reçoit des demandes via le formulaire de contact public. Aujourd'hui :
- Une demande = `Contact` (`interest` via `ContactReasonEnum`, `status` new/pending/processed/rejected, champs membership).
- Réponses = 4 Mailables **en dur** dans `ContactEmailService` (`welcome`, `membership_info`, `request_info`, `polite_decline`) + un email custom libre.
- Un contact peut devenir membre via `OnboardFromContactAction`.

On veut **3 capacités couplées**, qui culminent en un **outil d'aide à la décision pour le comité** :

- **A — Triage des contacts** : taguer/capturer le profil d'une demande **de façon incrémentale** au fil de l'échange (sans friction, tout optionnel).
- **C — Templates de réponse éditables** : qui servent autant à **répondre** qu'à **récolter** l'info manquante (questionnaires).
- **B — Attributs de saison sur le membre** : qui veut la compét, peut conduire, veut être capitaine, aide bénévole — **par saison**.
- **D — Board de planification** : composer des groupes/scénarios, les assigner à des `TrainingPack`, visualiser les **tensions effectif/capacité** pour décider de l'offre. Export/import.

Le fil rouge : **continuité de l'info** depuis la première prise de contact jusqu'à l'organisation de la saison, **sans ressaisie**.

---

## 2. Décisions verrouillées (issues du grilling)

| # | Sujet | Décision |
|---|-------|----------|
| 1 | Foyer des données | A vit sur `Contact` ; B vit sur `Subscription` (par saison) ; carry-over A→membre à l'onboarding. |
| 2 | Périmètre saison | Attributs saison concernent **tous les membres** (pas que les nouveaux) → sur `Subscription`. |
| 3 | Saisie attributs saison | **Les deux** : membre à l'inscription (flux `registration-management` existant) + admin en back-office. |
| 4 | Attributs saison à ajouter | `can_drive` (+ `seats_available`), `wants_to_be_captain`, `volunteer_help`. `is_competitive` **existe déjà**. |
| 5 | Niveau & âge des membres | **Déjà en base** : `users.ranking` (classement) + `users.birthdate` (→ catégorie d'âge dérivée). À **exploiter**, pas recréer. |
| 6 | Livrable saison | Board drag-drop "waouw" pour le comité + détails + export/import. |
| 7 | Cible du board v1 | **Groupes d'entraînement → TrainingPacks** (le team-composer interclubs existe déjà, enrichi plus tard). |
| 8 | Réel vs scénario | **Scénario de planification** séparé (brouillon), amorçable depuis le réel. |
| 9 | Portée scénario | Peut **modéliser l'offre** (packs hypothétiques : ajouter/retirer/redimensionner). **Pas** de volet coûts en v1. |
| 10 | Apply au réel | **Non** en v1 : le scénario est un **artefact de décision**. Les vrais packs se créent via les écrans admin existants. |
| 11 | Pool du board | Les **inscrits de la saison** (carte = nom, classement, catégorie d'âge, compétitif, can_drive…). |
| 12 | Templates | **Éditables en base (CRUD)** avec variables. Migration des 4 Mailables existants. |
| 13 | Envoi template | **Pré-remplit l'éditeur** d'email existant (variables résolues) ; l'admin ajuste puis envoie. |
| 14 | Template → statut | Champ **statut optionnel** appliqué à l'envoi (refus→rejected, bienvenue→processed, ou rien). |
| 15 | Triage contact | **Capture incrémentale par le secrétaire** au fil de l'échange. Form public **inchangé**. Champs structurés **tous optionnels**. Templates aident à **récolter**. |
| 16 | Champs contact | Catégorie d'âge · Expérience · Envie de compét/matchs · Dispo (jours) + conduite famille. |
| 17 | Carry-over onboarding | **Reporté vers le membre** : pré-remplit la 1ʳᵉ inscription (compét→is_competitive, conduite→can_drive, etc.). Nécessite lien `contact → user`. |
| 18 | Permissions | **Admin** : tout. **Comité** : voit tout. **Gérer** : groupe "secrétaire" = `is_admin OR committee_role ∈ {SECRETARY, PRESIDENT, VICE_PRESIDENT}` (motif `showSecretary` déjà utilisé dans `DashboardController`). |
| 19 | Export/import board | **Export** CSV + ODS + XLSX (PhpSpreadsheet, déjà installé) ; **Import** CSV (matching par licence/email). |
| 20 | PDF | **Différé** en v1 (pas de nouvelle dépendance). Impression PDF depuis le tableur en attendant. |

**Hypothèses à confirmer en passant (défauts retenus) :**
- Catégorie d'âge : enfant `< 13`, ado `13–17`, adulte `≥ 18` (configurable). *(à valider)*
- Set de templates de départ : `welcome`, `membership_info`, `request_info`, `polite_decline` (migrés) + `info_request_questionnaire` + `trial_invite`.

---

## 3. Modèle de données (cible)

### Feature A — Contact (champs optionnels, nullable)
Migration sur `contacts` :
- `age_category` (string nullable, enum `AgeCategoryEnum`: CHILD/TEEN/ADULT)
- `experience` (string nullable, enum `PlayerExperienceEnum`: NONE/FEW_MONTHS/FEW_YEARS/RANKED)
- `wants_competition` (boolean nullable)
- `preferred_days` (json nullable) — jours souhaités
- `family_can_drive` (boolean nullable)
- `user_id` (FK nullable) — lien posé à l'onboarding (carry-over §17)

### Feature B — Subscription (par saison)
Migration sur `subscriptions` :
- `can_drive` (boolean, default false)
- `seats_available` (integer nullable)
- `wants_to_be_captain` (boolean, default false)
- `volunteer_help` (boolean, default false)
- *(`is_competitive` existe déjà)*

### Feature C — Templates
Nouvelle table `email_templates` :
- `key` (string unique) · `name` · `subject` · `body` (text)
- `apply_status` (string nullable) — statut à appliquer à l'envoi
- `is_questionnaire` (boolean) — template orienté récolte d'info
- `is_active` (boolean) · timestamps
- Variables supportées (résolues côté service) : `{{first_name}}`, `{{last_name}}`, `{{full_name}}`, `{{interest}}`, `{{club_name}}`, `{{membership_total_cost}}`, …

### Feature D — Planification (scénarios)
Nouvelles tables :
- `training_plans` : `season_id`, `name`, `status` (draft/archived), `created_by`, timestamps.
- `training_plan_packs` : `training_plan_id`, `source_training_pack_id` (nullable = pack hypothétique), `name`, `level`, `day_of_week`, `max_participants`, `position`. (Snapshot modifiable de l'offre dans le scénario.)
- `training_plan_assignments` : `training_plan_id`, `training_plan_pack_id` (nullable = pool non-assigné), `user_id`, `position`.

Le board lit le pool = inscrits de la saison ; les cartes affichent `ranking`, catégorie d'âge (dérivée de `birthdate`), `is_competitive`, `can_drive`, `wants_to_be_captain`.

---

## 4. Enums à créer (Shared/Enums)
- `AgeCategoryEnum` (CHILD/TEEN/ADULT) + helper `fromBirthdate()` + seuils configurables.
- `PlayerExperienceEnum` (NONE/FEW_MONTHS/FEW_YEARS/RANKED).
- `TrainingPlanStatusEnum` (DRAFT/ARCHIVED).
- (Templates : pas d'enum, `key` libre en base.)

---

## 5. Séquencement & lots de délégation

Ordre : **A+C → B → D**. Chaque phase est livrable indépendamment, en TDD (test rouge → vert → refactor), Pint à la fin, tests en parallèle.

### Phase 1 — A + C (Triage contact + Templates) `🔴 à faire`
Couplées car les templates servent à récolter l'info de triage.
- **Agent 1.1 — Data/Model** : migrations `contacts` (champs A) + `email_templates` ; enums `AgeCategoryEnum`, `PlayerExperienceEnum` ; casts/fillable ; factories + seeder du set de templates de départ (dont migration des 4 Mailables). Tests unitaires modèles/enums.
- **Agent 1.2 — Templates service** : refondre `ContactEmailService::sendTemplate` pour résoudre les templates DB + variables + `apply_status` ; conserver l'API publique. Tests feature d'envoi (Mail::fake).
- **Agent 1.3 — UI triage contact** : dans le drawer de détail de `pages::website.contacts.index`, bloc "Profil" éditable inline (champs optionnels A), sauvegarde au fil de l'eau. Sélecteur de template → pré-remplit le composeur existant (variables résolues). CRUD templates (écran admin réservé groupe secrétaire). Filtres inbox par âge/expérience/envie-compét. Tests Livewire + browser smoke.
- **Agent 1.4 — Carry-over** : `OnboardFromContactAction` pose `contact.user_id` et reporte le profil ; pré-remplissage de la 1ʳᵉ inscription. Tests feature.

### Phase 2 — B (Attributs de saison) `🔴 à faire`
- **Agent 2.1 — Data/Model** : migration `subscriptions` (champs B), casts, factory states. Tests.
- **Agent 2.2 — Saisie membre** : ajout des questions dans `registration-management` (self-service). Tests Livewire.
- **Agent 2.3 — Saisie admin + roster** : édition admin sur la fiche/subscription + **roster de saison filtrable** (colonnes classement, catégorie d'âge, compétitif, can_drive, capitaine ; filtres/tri). Tests.

### Phase 3 — D (Board de planification) `🔴 à faire`
- **Agent 3.1 — Data/Model** : tables `training_plans` / `training_plan_packs` / `training_plan_assignments` + `TrainingPlanStatusEnum` + relations + factories. Service de **seed** depuis le réel (packs actifs + inscrits). Tests.
- **Agent 3.2 — Board UI** : Livewire drag-drop (colonnes = plan packs, cartes = membres), compteur **effectif/capacité** avec indicateur de tension, ajout/retrait/redim de packs hypothétiques, pool non-assigné. Tests Livewire + browser.
- **Agent 3.3 — Export/Import** : export CSV/ODS/XLSX (PhpSpreadsheet) d'un scénario ; import CSV (matching licence/email, validation/conflits). Tests feature.

### Phase 4 — Documentation `🔴 à faire`
- **Chef d'équipe** : documenter la feature dans `docs/manual-committee-fr.md` (+ EN `manual-committee.md`) : triage, templates, attributs saison, board. Mettre à jour `docs/FEATURES.md` si pertinent.

---

## 6. Permissions (à appliquer partout)
- **Gérer** (triage, templates CRUD, board édition, attributs admin) : `is_admin OR committee_role ∈ {SECRETARY, PRESIDENT, VICE_PRESIDENT}`. Factoriser ce check (policy ou gate `manage-club-admin`).
- **Voir** : tout membre du comité.
- Tests d'autorisation pour chaque écran.

---

## 7. Stratégie de test (TDD obligatoire — cf. CLAUDE.md)
- Chaque changement = test neuf ou mis à jour, écrit **avant** le code.
- Feature tests prioritaires ; unit tests pour enums/services purs.
- Factories pour tout setup ; états custom (`SubscriptionFactory`, nouveau pour templates/plans).
- Browser smoke tests : chaque nouvel écran = 200 + pas d'erreur JS (cf. mémoire *page-smoke-tests*).
- Lancer : `php artisan test --parallel --compact` (paratest installé). Filtrer par fichier pendant le dev.
- `vendor/bin/pint --dirty --format agent` avant de finaliser chaque lot.
- **Piège délégation worktree** : fuite `APP_BASE_PATH` depuis un sous-agent en worktree → classe Livewire compilée périmée. Vérifier `tests/bootstrap.php` (cf. mémoire *livewire-worktree-testing-pitfall*) si un agent en worktree voit des échecs Livewire inexpliqués.

---

## 8. Hors-scope v1 (déférés, notés pour plus tard)
- Apply d'un scénario au réel (création/maj packs + réaffectation inscriptions + waitlists/paiements).
- Volet coûts/recettes dans les scénarios.
- Export PDF (nécessite dépendance — `dompdf` proposé le moment venu).
- Import ODS/XLSX (v1 = import CSV seulement).
- Board des équipes interclubs (composer existant, enrichi plus tard).
- Capture des attributs saison pour les membres existants en masse (couvert par self-service + admin au cas par cas).

---

## 9. Journal de progression
> Chaque agent ajoute une ligne datée à la fin d'un lot : ce qui est fait, tests passants, fichiers clés, points ouverts.

- **2026-06-15** — Grilling terminé (20 décisions verrouillées). Plan rédigé. Implémentation non commencée.
- **2026-06-15** — 🎨 **UX envoi email refondue (retour user).** Avant : sélectionner un template ne faisait rien (bouton « Use template » `:disabled` lié à un `wire:model` **deferred** non synchronisé → détour absurde par « email perso » + annuler). Maintenant : `<x-select wire:model.live>` + hook `updatedSelectedTemplateKey()` → **choisir un template ouvre directement l'éditeur pré-rempli** ; bouton « Email personnalisé… » → `openCustomEmail()` (éditeur vierge, reset) ; `closeEmailModal()` reset complet ; `applyTemplate()` reset `selectedTemplateKey` (dropdown = simple déclencheur, re-sélectionnable). Bouton « Use template » supprimé. +3 tests (auto-ouverture / custom vierge / close-reset) + nouvelle string traduite FR/NL. ContactTriage 21 passed, smoke `/contacts` vert, Pint clean. ⚠️ `npm run build` requis (blade modifié).
- **2026-06-15** — 🐛 **BUG runtime à l'envoi corrigé (`Undefined array key "contact"`).** `ContactEmailService::sendCustom` (email libre + envoi après prefill template, appelé par Livewire `sendCustomEmail`) passait `['subject','body']` brut à `CustomEmail`, qui attend `contact/message/sender_name/club_name/subject` → crash au RENDU. **Invisible aux tests car `Mail::fake()` ne rend pas le mailable.** Fix : `buildMailData()` réutilisable construit le payload complet pour les 2 chemins (`sendCustom` + `sendTemplate`), `clubName()` aligné sur `Club::own()?->name`. **Garde-fou ajouté** : 3 tests « CustomEmail rendering » qui appellent `$mail->render()` explicitement (auraient cassé avant le fix). Décision onboarding confirmée par user = **groupe gestionnaire (#18)** (état actuel, pas de changement). Contact group 33 passed, Pint clean. ⚠️ Leçon : pour tout envoi d'email, tester le RENDU (`->render()`/`assertSeeInHtml`), pas seulement `Mail::assertSent`.
- **2026-06-15** — 🔧 **CORRECTIFS post-revue user (gate raté → rattrapé).** Le gate précédent a mal lu « 5 failed » : 3 étaient RÉELS (pas du browser). Corrigés : (1) **DB dev non migrée** → `php artisan migrate` + `db:seed --class=EmailTemplateSeeder` (6 templates) → erreurs `no such table: email_templates` sur `/contacts` et `/email-templates` résolues. Le smoke ne peut structurellement pas voir ça (RefreshDatabase migre une DB fraîche). (2) **TranslationCoverageTest** : 43 clés `__()` ajoutées sans traduction → ajoutées à `lang/fr_BE.json` + `lang/nl_BE.json` (ordre préservé, +43 chacune, 0 valeur existante modifiée). (3) **ContactOnboardTest** : `onboardContact` gaté par `manage-contacts` (1.3a) cassait « committee member can onboard » → réécrit en 2 tests conformes à #18 (secrétaire onboarde ✅ / membre comité simple → `assertForbidden`). ⚠️ **Changement de comportement assumé (#18)** : l'onboarding est désormais réservé au groupe gestionnaire (avant : tout membre du comité) — à confirmer par le user. (4) **Form de contact** « Club has no contact email configured. » = préexistant (commit 4ad1ac8c), **config dev** : renseigner l'email de contact du club (réglages club / setup wizard), pas un bug Phase 1. **Suite complète post-fix : 1705 passed, 0 échec hors browser** ; seuls les tests browser restent **flaky** (test en échec varie d'un run à l'autre : LivewireReactivity/SubscriptionFlow puis AuthFlow). Pint clean.
- **2026-06-15** — ✅ **GATE PHASE 1 (A+C) — COMPLÈTE & VÉRIFIÉE.** Suite complète : **1700 passed**, 31 skipped, 11 todos. **2 échecs browser PRÉEXISTANTS** (`LivewireReactivityTest` + `SubscriptionFlowTest`, "filter drawer opens" sur meetings & registrations) — **régression formellement écartée** : `git stash -u` + run sur la base → mêmes 2 échecs sans mes changements. Seul fichier partagé modifié = `navigation.blade.php` (menu sous `@can('manage-contacts')`). ⚠️ **À faire côté user** : `npm run build` (ou `composer run dev`) pour voir l'UI ; vérifier les 2 tests browser préexistants indépendamment. **Phase 2** : consommer `$user->originatingContact()?->subscriptionSeed()`.
- **2026-06-15** — ✅ **Lot 1.2 (Service templates)** livré & vérifié. `EmailTemplateRenderer::render(key, contact)` → `{subject, body, apply_status}`, variables `{{first_name/last_name/full_name/interest/club_name}}` (variable inconnue laissée telle quelle pour relecture admin). `ContactEmailService::sendTemplate` refondu : charge le template DB, envoie via `CustomEmail` générique, applique `apply_status` validé (sinon Log::warning). `sendCustom` inchangé. **Correctif superviseur** : `{{club_name}}` aligné sur `Club::own()?->name` (cohérence commit 4ad1ac8c) avec fallback config. 17 tests verts post-fix, Pint clean. ⚠️ Anciens Mailables `WelcomeEmail/MembershipInfoDetailEmail/RequestInfoEmail/PoliteDeclineEmail` = code mort (à nettoyer plus tard, NE PAS toucher `MemberWelcomeMail`). `{{membership_total_cost}}` pas encore implémenté (à ajouter si l'UI 1.3 le requiert).
- **2026-06-15** — ✅ **Lot 1.3a (UI triage contact)** livré & vérifié. Dans `pages::website.contacts.index` : bloc **Profil** éditable dans le drawer (`age_category`/`experience`/`wants_competition` tri-état/`family_can_drive` tri-état/`preferred_days` via `x-choices-offline`), méthode `updateContactProfile()` (capture incrémentale, tout optionnel, blanc → null). **Sélecteur de template** (`EmailTemplate::active()` par `name`) → `applyTemplate()` pré-remplit `emailSubject`/`emailBody` (variables résolues via `EmailTemplateRenderer`) + mémorise `pendingApplyStatus`, ouvre la modale ; `sendCustomEmail()` applique le statut validé après envoi puis reset (email libre = aucun statut). **Filtres inbox** `ageCategory`/`experience`/`wantsCompetition` en `#[Url]` + chips + query. **Permissions** : Gate `manage-contacts` + helper `User::canManageClubAdmin()` (factorise `showSecretary`), guard sur toutes les actions de gestion ; comité hors-groupe voit mais ne gère pas. **`sendTemplateEmail()` (envoi direct) RETIRÉE** (remplacée par prefill, décision #13) + usages blade supprimés. Enum age trié explicitement enfant→ado→adulte (Pint réordonne). 15 tests dédiés + suite Contact (71 passed/2 skipped) + browser smoke `/contacts` (200, no JS error) + Users (252) + Dashboard (13) verts, Pint clean.
- **2026-06-15** — ✅ **Lot 1.3b (CRUD templates)** livré & vérifié. Nouvelle page Livewire `pages::website.contacts.email-templates` (dir `resources/views/pages/website/contacts/⚡email-templates/`) : table de tous les `EmailTemplate` (name, key, apply_status, badges *Questionnaire*/*Inactive*), modale create/edit unifiée, suppression via `x-confirm-modal`, toggle `is_active` (icône œil). Formulaire : `name`/`key`/`subject`/`body` (textarea 10 lignes) + select `apply_status` (aucun/new/pending/processed/rejected) + toggle `is_questionnaire`. **Aide variables** (`{{first_name}}` `{{last_name}}` `{{full_name}}` `{{interest}}` `{{club_name}}`) affichée sous le body. **Validation** : name/subject/body requis ; key requis + `regex:/^[a-z0-9_]+$/` + `unique:email_templates,key` (`ignore` en update) ; apply_status `nullable|in:new,pending,processed,rejected`. **Édition de `key` des templates système** : les 6 clés du seeder (`welcome`, `membership_info`, `request_info`, `polite_decline`, `info_request_questionnaire`, `trial_invite`) sont listées en `const SYSTEM_KEYS` ; en édition la `key` devient read-only (`keyLocked`) et est re-forcée côté serveur avant save (anti-tampering) — le reste du template reste éditable. **Route** `admin.website.contacts.email-templates` sous le groupe `admin/website` (`auth`/`verified`/`committee`) + middleware `can:manage-contacts`. **Permissions (#18)** : `Gate::authorize('manage-contacts')` dans `mount()` ET sur chaque action (openCreate/openEdit/save/toggle/delete) ; non-gestionnaire → 403 (mount + route). **Lien menu** : entrée *Email templates* dans le sous-menu Website, sous `@can('manage-contacts')`. 15 tests dédiés (`tests/Feature/ClubAdmin/Contact/EmailTemplateCrudTest.php`) + suite Contact (86 passed/2 skipped) + browser smoke `email-templates` (200, no JS error) verts, Pint clean. Note : les deux tests « forbidden create/delete » ont été fusionnés dans l'assertion de mount gardé (mount gated → impossible d'agir après un mount refusé). Points ouverts : pas de filtre/recherche ni bulk-actions sur cette liste (volume faible, non requis) ; champ `is_active` non exposé dans le form (toggle dédié en liste).
- **2026-06-15** — ✅ **Lot 1.4 (Carry-over onboarding)** livré & vérifié. **Lien** : `OnboardFromContactAction::handle()` pose désormais `contact.user_id = $user->id` en plus de `status = 'processed'` (même `update()`, lien durable — aucun autre code ne réécrit le contact ensuite). **Seed** : choisi `Contact::subscriptionSeed(): array` (forme retenue — la donnée vit sur le Contact, pas de VO à enregistrer, directement testable). Mappe vers les NOMS de colonnes subscription cibles (Phase 2) : `is_competitive`⟵`wants_competition`, `can_drive`⟵`family_can_drive`, + `experience`/`age_category` (`->value`) + `preferred_days` bruts. **Ne retourne que les clés renseignées** (skip si null), array shape documentée en PHPDoc. **Accès Phase 2** : `User::originatingContact(): ?Contact` (`Contact::where('user_id', id)->latest()->first()`) pour retrouver le contact source et lire son seed. Tests : nouveau `tests/Feature/ClubAdmin/Contact/ContactSubscriptionSeedTest.php` (5 tests : mapping complet, vide quand tout null, omission sélective, originatingContact trouvé/null) + test d'onboarding existant étendu pour asserter `user_id` (`UserActionsTest`, 18 verts). Pint clean (réordonne `subscriptionSeed` avant `user()`). ⚠️ **Phase 2** : la consommation réelle (pré-remplir la 1ʳᵉ inscription) reste à faire — les colonnes saison `is_competitive`/`can_drive` n'existent pas encore et l'onboarding ne crée pas de subscription ; le seed est un simple dictionnaire que 2.x lira via `originatingContact()->subscriptionSeed()`.
- **2026-06-15** — ✅ **Lot 1.1 (Data/Model)** livré & vérifié. Enums `AgeCategoryEnum` (+`fromBirthdate`, seuils 13/18 en constantes), `PlayerExperienceEnum`. Migrations : champs profil nullable + `user_id` sur `contacts` ; table `email_templates`. Modèle `EmailTemplate` (+scope `active()`). Casts/relation `user()`/PHPDoc sur `Contact`. `EmailTemplateFactory` (états questionnaire/inactive/appliesStatus), état `ContactFactory::withProfile()`. `EmailTemplateSeeder` (6 clés, `welcome`→processed, `polite_decline`→rejected). **18 tests dédiés verts**, 156 unit verts, Pint clean. ⚠️ Pint réordonne les cases d'enum alphabétiquement → trier explicitement à l'affichage. `apply_status` reste string libre, validation à faire dans 1.2.

---

## 10. Checklist de suivi
**Phase 1 — A+C**
- [x] 1.1 Data/Model (migrations, enums, factories, seeder templates) ✅
- [x] 1.2 Templates service (résolution DB + variables + apply_status) ✅
- [x] 1.3a UI triage (champs profil drawer) + prefill template + filtres inbox ✅
- [x] 1.3b CRUD templates (écran admin réservé groupe secrétaire) ✅
- [x] 1.4 Carry-over onboarding (lien `contact.user_id` + `Contact::subscriptionSeed()` + `User::originatingContact()` ; consommation réelle = Phase 2) ✅

**Phase 2 — B**
- [ ] 2.1 Data/Model subscriptions
- [ ] 2.2 Saisie membre (registration-management)
- [ ] 2.3 Saisie admin + roster filtrable

**Phase 3 — D**
- [ ] 3.1 Data/Model scénarios + seed
- [ ] 3.2 Board UI drag-drop + tensions
- [ ] 3.3 Export/Import

**Phase 4**
- [ ] Doc manuel comité (FR+EN) + FEATURES.md
