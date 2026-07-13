# Plan — Espace membre : règlement, confidentialité, annuaire, recherche, paiements & amendes

> **Statut global :** 🟡 Plans validés en grilling — implémentation pas commencée
> **Branche :** `go_saison_2627`
> **Chef d'équipe :** Claude (supervision). Implémentations déléguées à des agents distincts, en **TDD** via le skill `handoff`.
> **Dernière mise à jour :** 2026-07-13

Ce document est **vivant** : chaque agent met à jour le journal (§9) et coche ses tâches.
Chaque chantier (§3→§8) est **autonome et handoff-able**.

---

## 1. Contexte & objectif

Six chantiers pour enrichir l'**espace membre** (`admin/my-space/{user}/…`) et sa gouvernance :

1. **Règlement AFTTB** — page moderne, digest en chapitres (autonome, quick win).
2. **Confidentialité des coordonnées** — réglages opt-in par champ (tel / email / adresse).
3. **Recherche noms composés** — scope réutilisable (« Jean Pierre » → « Jean‑Pierre Van Oudenhove »).
4. **Annuaire membre** — trombinoscope consultable, respectant §2 ; exception capitaine dans l'écran de sélection.
5. **Hub paiements membre** — consulter + payer (soi + dépendants), sans stats de totaux.
6. **Amendes** — répercuter une amende fédérale, génère un paiement + email pédagogique.

**Ordre d'implémentation retenu (quick win d'abord) : 1 → 2 → 3 → 4 → 5 → 6.**
Dépendances : 2 précède 4 ; 3 sert 4 ; 5 précède 6 (l'amende surface dans le hub).

---

## 2. Décisions transverses verrouillées (grilling 2026-07-13)

| # | Sujet | Décision |
|---|-------|----------|
| T1 | Visibilité coordonnées par défaut | **Opt-in** : rien n'est partagé tant que le membre ne l'active pas (RGPD). |
| T2 | Granularité | **3 booléens** indépendants : tel, email, adresse. « Visible » = tous les membres actifs. |
| T3 | Stockage flags | JSON `contact_visibility` sur `users` (clé absente = caché). Miroir de `notification_preferences`. |
| T4 | Règle de visibilité (centralisée) | `voir coordonnée X de U` = **soi** OU **comité** (`is_admin \|\| is_committee_member`) OU **U a partagé X**. Le **capitaine** est traité à part, uniquement dans l'écran de sélection. |
| T5 | Présence annuaire | **Toujours listé** : tout membre actif figure par nom + classement + force. Seules les coordonnées sont masquables. Pas d'opt-out total. |
| T6 | Roster annuaire | **Membres actifs de la saison courante** (compétitifs + récréatifs), via `is_active` (souscription confirmée/payée). |
| T7 | Archi annuaire | **Nouveau composant dédié lecture seule** (pas de réutilisation de `⚡index`). |
| T8 | Exception capitaine | Coordonnées des joueurs de **son** équipe visibles **dans `⚡captain-selection`**, pas dans l'annuaire général. |
| T9 | Recherche | **Tokens croisés + équivalence tiret/espace**, DB-agnostique. Scope `User` réutilisable (admin + annuaire). |
| T10 | Règlement | **Figé dans le code** (Markdown/Blade) v1. Contenu métier fourni par le comité ; je livre structure + placeholders. |
| T11 | Périmètre paiements | **Hiérarchique** : soi + users dont on est tuteur (`Guardian.user_id = moi → users()`). Pas de partage symétrique entre adultes. |
| T12 | Hub paiements | Point **unique** : consulter (en attente + payés) **et** payer (QR/réf). `event-subscription` pointera vers lui. Pas d'actions trésorier, pas de stats de totaux. |
| T13 | Organisation hub | **Liste plate + chips de filtre** (statut, type, personne). |
| T14 | Modèle amende | **Modèle `Fine` dédié** implémentant `DescribesPayment` → génère un `Payment`. |
| T15 | Entrée amende | Depuis la **fiche membre** + **page amendes** sous trésorerie (suivi). Gate : trésorier/président/admin. |
| T16 | Email amende | **Message perso obligatoire**, template pré-rempli **éditable**, **aperçu**, envoi **à la création**. Destinataires : membre + tuteurs si mineur. Contient réf. paiement + lien hub. |

**Faits techniques confirmés (repo) :**
- Coordonnées : `users.phone_number`, `users.email`, adresse = `street` + `city_code` + `city_name`.
- Rôles : `is_admin` (bool), `is_committee_member` (bool), `committee_role` (`CommitteeRolesEnum` : ADMINISTRATOR/PRESIDENT/SECRETARY/TREASURER/VICE_PRESIDENT).
- `is_active` / `is_competitor` dérivés de `Subscription` (saison courante). `Season::current()`.
- `ForceList` service → `users.force_list` ; classement `users.ranking`.
- Guardian : `Guardian` belongsTo `User` via `user_id` (`member()`) ; `Guardian::users()` = les personnes dont il est responsable (pivot `guardian_user`). `User::guardians()` = ses tuteurs.
- Paiements : `Payment morphTo payable` ; `DescribesPayment::getPaymentLabel(): {type,name}` implémenté par `Subscription`, `TournamentRegistration`, `MeetingUser`. Actions : `GeneratePaymentReference`, `GeneratePaymentQR`, `GeneratePayment`.
- Capitaine : `Team.captain_id → User` ; `User::captainOf(): HasOne(Team)`.
- Avatar : `users.avatar_url` + `users.photo` existent.
- User-space routes : `routes/web.php` prefix `admin/my-space/` + middleware profil ; middleware `committee` = `CommitteeMemberMiddelware` (`is_admin || is_committee_member`).
- Écran capitaine : `resources/views/pages/club-events/interclubs/⚡captain-selection/`.
- i18n : chaque `__()` doit avoir FR+NL dans `lang/*_BE.json` (sinon arch test échoue).

---

## 3. Chantier 1 — Règlement AFTTB *(quick win, autonome)*

**Objectif.** Page moderne, agréable, résumant le règlement fédéral en chapitres digestes, accessible en permanence aux membres connectés. Lien vers le PDF officiel pour le texte de référence.

**Décisions.** Contenu figé (Markdown rendu ou sections Blade). 4 chapitres :
1. **Déroulement d'une rencontre interclub** (ordre simples/doubles, feuille de match, capitaine, horaires, forfaits/WO).
2. **Règles de jeu essentielles** (service, let, filet, comptage, matériel).
3. **Conduite, sanctions & amendes** (comportement, absences injustifiées, sanctions fédérales — fait le lien avec le chantier 6).
4. **Classements & montées/descentes** (NC→E→D→C→B→A, calcul, divisions — éclaire l'indice de force de l'annuaire).

**Contenu métier = à fournir par le comité.** Je livre la **structure + placeholders** (`{{-- TODO comité --}}`), ton pédagogique, mise en page.

**Fichiers / tâches.** ✅ **LIVRÉ 2026-07-13**
- [x] Route : `admin/my-space/{user}/reglement` → `pages::club-admin.users.user-space.reglement` (Volt SFC, self-only comme les siblings).
- [x] Vue : bandeau intro + bouton « Règlement AFTTB complet » (externe), TOC d'ancres, 4 sections en cartes ancrées avec icônes Heroicons.
- [x] Design : design-system (cartes plates `border-base-300`, `rounded-xl/2xl`, accents `primary`/`warning`, pas d'emoji admin).
- [x] Nav : entrée « Règlement » (`o-book-open`) dans le menu user-space.
- [x] i18n FR+NL : 25 clés ajoutées à `lang/fr_BE.json` + `lang/nl_BE.json`.

**Tests (Pest).** ✅
- [x] `ReglementTest` : 4 titres de chapitres + lien PDF + guest redirigé login.
- [x] Route ajoutée à la matrice self-only `MySpaceAuthorizationTest` (membre OK, autre membre 403, admin 403).
- [x] Smoke (browser 200 + JS) : passe (page auto-découverte).

**Contenu.** Bullets généraux vrais (pointant vers le PDF officiel comme source faisant foi) ; **le comité affinera** le texte de chaque chapitre. Note interne laissée en commentaire Blade.

**Risques.** Aucun majeur. Bullets volontairement génériques + renvoi au règlement officiel.

---

## 4. Chantier 2 — Confidentialité des coordonnées

**Objectif.** Dans `⚡settings`, permettre au membre d'autoriser (opt-in) l'affichage de chacune de ses coordonnées aux autres membres.

**✅ LIVRÉ 2026-07-13.** Migration à jouer sur le dev/prod MariaDB (`php artisan migrate`).

**Modèle de données.**
- [x] Migration : `users.contact_visibility` JSON **nullable** (DB-agnostique). `null` = tout caché.
- [x] `User` : cast `array`, `contact_visibility` en `$fillable`.
- [x] Helpers `sharesContact()` + `contactVisibleTo()` (source de vérité T4) :
  ```php
  // Champs : 'phone' | 'email' | 'address'
  public function sharesContact(string $field): bool
  {
      return (bool) ($this->contact_visibility[$field] ?? false);
  }

  // Le viewer peut-il voir ce champ de $this ?
  public function contactVisibleTo(User $viewer, string $field): bool
  {
      return $viewer->is($this)
          || $viewer->is_admin || $viewer->is_committee_member
          || $this->sharesContact($field);
      // NB : exception capitaine gérée hors de cette méthode (chantier 4, écran sélection).
  }
  ```

**UI (`settings.php` / `.blade.php`).**
- [x] Section « Contact visibility » : 3 toggles (tel, email, adresse), **auto-save** au flip via `updated()` (prefix `share`), toast « Privacy preferences saved. ».
- [x] Texte d'aide + rappel comité/capitaine gardent l'accès.
- [x] `abort_unless(Auth::user()->is($this->user), 403)` en tête de `updated()`.
- [x] i18n FR+NL (8 clés).

**Tests.** ✅ `ContactVisibilityTest` (10) :
- [x] `sharesContact()` / `contactVisibleTo()` : matrice (soi, comité, admin, autre membre partagé/non partagé).
- [x] Toggle persiste dans `contact_visibility`.
- [x] Défaut : nouvel utilisateur → tout `false`.
- [x] Cross-user (attacker/victim) → 403 au mount.

**Risques traités.** Opt-in par défaut (aucune fuite). ⚠️ **À faire au chantier 4** : filtrer les coordonnées **côté serveur** dans l'annuaire (jamais de fuite en HTML masqué CSS).

---

## 5. Chantier 3 — Recherche noms composés *(scope réutilisable)*

**Objectif.** Corriger les deux cas : « Jean Pierre » → « Jean‑Pierre » ; « Jean Van » → prénom « Jean‑Pierre » + nom « Van Oudenhove ». Réutilisable par `⚡index` (admin) **et** l'annuaire (chantier 4).

**✅ LIVRÉ 2026-07-13.** Pas de migration. **DB-agnostique confirmé** : `LOWER()` + `LIKE` par colonne (aucun `CONCAT`/`||`), même pattern que `scopeSearchTerms` déjà en prod → pas de risque MariaDB.

**Approche retenue (plus simple que le plan initial).** Tokeniser sur espaces **et** tirets ; chaque token doit matcher (LIKE) dans `first_name` OU `last_name` OU `email` (chacun `LOWER`é). Pas besoin de concaténer les colonnes : un token individuel est un sous-mot de la colonne, donc « Jean Van » → jean∈first, van∈last (AND entre tokens). Bonus : marche quel que soit l'ordre des mots.

- [ ] `User::scopeSearchName(Builder $q, string $term): Builder` :
  ```php
  $tokens = preg_split('/[\s-]+/', trim($term), -1, PREG_SPLIT_NO_EMPTY);
  $q->where(function ($q) use ($tokens, $term) {
      foreach ($tokens as $token) {
          $like = '%'.str_replace('-', ' ', $token).'%';
          // full = REPLACE(first_name,'-',' ') || ' ' || REPLACE(last_name,'-',' ')
          $q->whereRaw(
              "REPLACE(first_name,'-',' ') || ' ' || REPLACE(last_name,'-',' ') LIKE ?",
              [$like]
          );
      }
      $q->orWhere('email', 'like', '%'.$term.'%'); // garde le match email global
  });
  ```
  Implémenté avec `LOWER(col) LIKE ?` (aucune concaténation) → pas de souci de portabilité.
- [x] `scopeSearchName(EloquentBuilder, string)` ajouté à `User`.
- [x] Bloc inline de `⚡index/index.php` remplacé par `->when($this->search, fn ($q) => $q->searchName($this->search))`.

**Tests.** ✅ `UserSearchNameTest` (7, model-level) + 1 intégration dans `IndexTest` :
- [x] « Jean Pierre » trouve `Jean-Pierre` ; « Jean Van » trouve `Jean-Pierre Van Oudenhove` ; ordre indifférent ; casse ignorée ; email matché ; AND entre tokens ; tiret dans la requête ignoré.
- [x] Non-régression `⚡index` (454 tests ClubAdmin/Users verts).

**Risques.** Perf : LIKE `%…%` non sargable — acceptable à l'échelle d'un club.

---

## 6. Chantier 4 — Annuaire membre *(dépend de 2 & 3)*

**Objectif.** Trombinoscope consultable par tout membre actif : nom, classement, indice de force, équipes, avatar, et coordonnées **selon les flags** (T4). Consultation pure, aucune action.

**✅ LIVRÉ 2026-07-13 (4a annuaire + 4b exception capitaine).**

**Archi (T7).** Nouveau composant dédié.
- [x] Route `admin.user.directory` (`{user}/directory`), self-only, dans la matrice `MySpaceAuthorizationTest`.
- [x] Query roster (T6) : `User::active()`, tri nom, eager-load `teams:id,name`, paginate 24.
- [x] Recherche `->searchName()` + filtres drawer (classement, équipe) via `HasFilterDrawer` + chips.
- [x] Cartes : avatar (`photo` fallback), nom, classement, indice de force, chips équipes.
- [x] Coordonnées filtrées **côté serveur** via `contactVisibleTo()` ; champs partagés → liens `tel:`/`mailto:` ; sinon « Aucune coordonnée partagée ».
- [x] Grille responsive + empty state + pagination.
- [x] Nav : entrée « Annuaire des membres » (top-level, `o-users`).
- [x] i18n FR+NL (5 clés).

**Exception capitaine (T8) — dans `⚡captain-selection`.**
- [x] `buildPlayerData()` expose `phone_number` + `email` ; le roster (drawer de sélection) affiche tel/email des joueurs **inconditionnellement** (override, `@click.stop` pour ne pas toggler). Écran déjà gated capitaine/sélecteur/comité/admin, teams scopés aux teams accessibles.

**Tests.** ✅ `DirectoryTest` (6) + `CaptainSelectionTest` (+1) :
- [x] Roster actifs seulement ; ranking affiché ; coordonnées masquées par défaut / visibles après opt-in ; comité voit tout ; recherche noms composés.
- [x] Capitaine voit tel/email de ses joueurs même non partagés.
- [x] 149 UserSpace + 23 captain-selection + smoke/coverage verts.

**Risques traités.** Filtrage **côté serveur** (aucune fuite HTML). Eager-load teams (pas de N+1 sur les chips).

---

## 7. Chantier 5 — Hub paiements membre *(précède 6)*

**Objectif.** Page unique où le membre consulte **et** paie ses paiements + ceux de ses dépendants. Inspiré de la vue trésorier, **sans actions trésorier**, **sans stats de totaux**.

**✅ LIVRÉ 2026-07-13.**

**Périmètre (T11).**
- [x] `User::payableUserIds(): array` = soi + users dont il est tuteur (`whereHas('guardians', user_id = moi)`). Sens confirmé : `Guardian.user_id` = compte du tuteur, `guardian_user` = ses pupilles.
- [x] Query : `Payment::whereHasMorph('payable', [Subscription, TournamentRegistration, MeetingUser], user_id ∈ ids)` + morphWith. (`Fine` à ajouter au chantier 6.)

**UI (T12/T13).**
- [x] Route `admin.user.payments` (`{user}/payments`), self-only, dans la matrice.
- [x] Liste plate triée date desc : objet (type+nom via `getPaymentLabel`), personne (`getPayerName`, seulement si tuteur de plusieurs), montant, badge statut, date, réf, **bouton Payer (QR)** pour les `pending`.
- [x] Filtres drawer + chips : statut, type, personne (personne seulement si pupilles).
- [x] **Aucun total** à payer/payé.
- [x] Modal QR réutilisé (générique via `getPaymentLabel`), `GeneratePaymentQR` + `ourClub`.
- [x] `event-subscription` : lien « Tous mes paiements » vers le hub dans le bloc paiements (bloc conservé pour le contexte événement).
- [x] Nav : entrée « Mes paiements » (`o-credit-card`) dans le sous-menu perso. i18n FR+NL (7 clés).

**Tests.** ✅ `PaymentsHubTest` (8) :
- [x] Voit ses paiements (pending+paid) + ceux de ses pupilles ; **jamais** un tiers ; pas les paiements d'un user non gardé.
- [x] `openPaymentModal` : QR pour un paiement en scope ; **403** hors scope. Page self-only (403 cross-user).
- [x] 162 UserSpace + smoke/coverage verts.

**Risques traités.** Scope strict `payableUserIds` (jamais élargi par un filtre) ; garde 403 dans `openPaymentModal` contre un `payment_id` arbitraire.

---

## 8. Chantier 6 — Amendes *(dépend de 5)*

**Objectif.** Le comité répercute une amende fédérale sur un membre → crée un `Fine` → génère un `Payment` (qui surface dans le hub §7 + vue trésorier) → envoie un email pédagogique personnalisé.

**Modèle (T14).**
- [ ] Migration `fines` : `user_id` (membre sanctionné), `amount` (decimal), `reason`/`category` (enum motif : conduite, absence injustifiée, forfait, autre…), `federation_reference` (nullable), `description` (text nullable), `issued_by` (user_id comité), `pedagogical_message` (text), timestamps, soft-deletes + audit (cf. `HasAuditLog`).
- [ ] Modèle `Fine` (domaine `ClubAdmin\Payment` ou nouveau `ClubAdmin\Fines`) implémentant `DescribesPayment` :
  - `getPayerName()` → nom du membre sanctionné.
  - `getPaymentLabel()` → `['type' => __('Fine'), 'name' => <motif lisible>]`.
  - `payment(): MorphOne` (comme les autres payables).
- [ ] Factory + enum motif. Enregistrer `Fine` dans les listes `whereHasMorph`/`morphWith` des vues paiements (hub §7, event-subscription si pertinent, trésorier).

**Action métier.**
- [ ] `IssueFineAction` : crée `Fine`, génère un `Payment` `pending` (`GeneratePaymentReference` + montant), envoie l'email. Transaction DB.
- [ ] Gate `canManageFinances()` sur `User` : `is_admin || committee_role ∈ {TREASURER, PRESIDENT}` (+ VICE_PRESIDENT ? à confirmer). Policy `FinePolicy`.

**UI (T15).**
- [ ] **Fiche membre** (`⚡index` détail ou vue membre admin) : action « Infliger une amende » (drawer/modal) → formulaire : montant, motif, réf. fédérale, description, **message pédagogique** (pré-rempli, éditable), **aperçu**.
- [ ] **Page trésorerie amendes** : `admin/treasury/fines` (middleware `committee` + gate finances) → liste des amendes (membre, motif, montant, statut paiement, date, émetteur), filtres statut. Pas d'action de paiement (le membre paie via le hub).
- [ ] i18n FR+NL.

**Email pédagogique (T16).**
- [ ] Mailable/Notification `FineIssuedNotification` : template pré-rempli (motif, montant, réf. paiement, lien vers le hub §7, ton bienveillant), corps = `pedagogical_message` du comité.
- [ ] Destinataires : le membre **+ ses tuteurs** si mineur (`user->guardians` / `requiresGuardian()`).
- [ ] Envoi **à la création** (dans `IssueFineAction`). Aperçu avant envoi côté UI.
- [ ] Tester le **rendu** (`->render()` / `assertSeeInHtml`), pas seulement `Notification::fake()` (cf. mémoire email rendering).

**Tests.**
- [ ] `IssueFineAction` crée Fine + Payment `pending` lié + montant correct.
- [ ] L'amende apparaît dans le hub paiements du membre (et de son tuteur si dépendant) et dans la vue trésorier.
- [ ] Gate : un membre lambda / capitaine ne peut pas infliger ; trésorier/président/admin oui.
- [ ] Email : envoyé au membre (+ tuteurs si mineur), contient motif/montant/réf/lien, message perso rendu.
- [ ] `getPaymentLabel()` → type « Amende ».
- [ ] Migration rejouée sur **MariaDB** avant merge.

**Risques.** Financier/RGPD : l'amende crée une dette réelle — auditer (`HasAuditLog`), transaction atomique (pas de Fine sans Payment ni email). Montant : saisie libre par le comité (montant fédéral, éventuellement + frais club).

---

## 9. Journal de progression

| Date | Chantier | Agent | Fait |
|------|----------|-------|------|
| 2026-07-13 | — | Claude | Grilling complet (16 décisions transverses), plans rédigés. |
| 2026-07-13 | 1 — Règlement | Claude | ✅ Livré + commité (`4d61c0a6`). Menu déplacé hors espace perso (après Notifications). |
| 2026-07-13 | 2 — Confidentialité | Claude | ✅ Livré + commité (`484205e0`). Password déplacé en bas + rétréci. **Migration à jouer sur MariaDB.** |
| 2026-07-13 | 3 — Recherche | Claude | ✅ Livré + commité (`678a919f`). |
| 2026-07-13 | 4 — Annuaire | Claude | ✅ Livré + commité (`e480bb77`). Chips/filtre équipe avec catégorie + filtre saison (défaut courante, tri ascendant). |
| 2026-07-13 | 5 — Hub paiements | Claude | ✅ Livré en TDD : `User::payableUserIds` (soi + pupilles), composant `payments` (liste plate, filtres statut/type/personne, QR payer, pas de totaux), lien depuis event-subscription, nav. `PaymentsHubTest` (8) + 162 UserSpace verts. Non commité. |

---

## 10. Rappels d'exécution (mémoire projet)

- **TDD** systématique ; `php artisan test --parallel --compact` (paratest).
- Chaque `__()` → FR **et** NL dans `lang/fr_BE.json` / `lang/nl_BE.json` (sinon arch test échoue).
- **Migrations DB-agnostiques** ; rejouer sur MariaDB avant prod (SQLite en test masque les crashes).
- `vendor/bin/pint --dirty --format agent` avant de finaliser.
- Pas de JS custom (TALL) ; pas de commit sans feu vert explicite ; pas de signature Co-Authored-By.
- En cas de centaines de tests cassés d'un coup : `view:clear` + `optimize:clear` (cache Livewire périmé) avant de debugger.
