# Features principales & FAQ

> Comment utiliser les features principales de l'application.

---

## Competitions

### 1. Créer une saison + planning interclubs

**Qui peut faire ça**: Sélectionneur, Secrétaire

**Étapes**:
1. Admin → Compétitions → Seasons → Create
2. Entrer le nom (ex: "2024-2025"), dates de début/fin
3. Sauvegarder
4. Une fois la saison créée, créer le planning:
   - Admin → Interclubs → Create Planning
   - Pour chaque semaine (1-52), ajouter un match
   - Remplir: date, équipe visitante, lieu, salle

**Output**: Saison avec 52 matchs planifiés

---

### 2. Gérer les sélections (Compétiteurs → Sélections)

**Workflow complet**:
1. **Sélectionneur** crée la saison et le planning
2. **Compétiteurs** donnent leur disponibilité
   - Public → Compétitions → My Availabilities
   - Pour chaque match: "Available" / "Unavailable" / "Uncertain"
3. **Capitaines** sélectionnent leurs joueurs
   - Admin → Competitions → Select Players (pour leur équipe)
   - Voir les joueurs disponibles, confirmer la sélection
4. **Sélectionneur** supervise
   - Admin → Competitions → Selections Overview
   - Voir tous les matchs, identifier les conflits (pas assez joueurs, malades)
   - Aider les capitaines à résoudre

**États de la sélection**:
- `subscribed`: joueur s'est manifesté (a une dispo)
- `selected`: capitaine l'a confirmé
- `played`: joueur a joué le match

---

### 3. Tracker les résultats interclubs

**Après le match**:
1. Sélectionneur → Admin → Interclubs → Results
2. Entrer le score
3. Checker les joueurs qui ont joué
4. Sauvegarder

**Résultats visibles**:
- Compétiteurs → Competitions → My Results
- Public → Club Results (actualisé automatiquement)

---

## Trainings

### 1. Créer un pack d'entraînement

**Qui peut faire ça**: Secrétaire

**Étapes**:
1. Admin → Trainings → Packs → Create
2. Entrer:
   - Nom (ex: "Débutants - Lundi")
   - Niveau (Débutant/Intermédiaire/Avancé)
   - Entraîneur (assigner)
   - Jour/heure/durée
   - Salle
   - Capacité max
   - Prix
   - Dates ouverture/fermeture
3. Sauvegarder

**Auto-generated**: Sessions individuelles créées chaque semaine basé sur le pattern

---

### 2. Inviter les membres à s'inscrire

**Secrétaire**:
1. Admin → Trainings → Packs → (select) → Invite Members
2. Choisir les membres à inviter
3. Envoyer invitation (via notification)

**Membres**:
1. Reçoivent une invitation
2. Dashboard → Training Packs → Register
3. Voient le prix, confirmations nécessaires
4. Paiement

---

### 3. Tracker présences/absences

**Entraîneur**:
1. Admin → Trainings → Sessions → (today's sessions)
2. Pour chaque participant: Mark as "Present" / "Absent" / "Late"
3. Sauvegarder

**Membre**:
- Peut voir l'historique de ses présences dans Dashboard

---

## Meetings

### 1. Créer une réunion (AG, comité, festive)

**Qui peut faire ça**: Président

**Étapes**:
1. Admin → Meetings → Create
2. Entrer:
   - Titre (ex: "AG 2024")
   - Type (AG / Comité / Festive)
   - Format (En personne / Zoom / Hybride)
   - Description
   - Lieu / lien Zoom
3. Inviter participants (ou juste comité)
4. Proposer 2-3 dates
5. Sauvegarder

**Auto-triggered**: Invitation envoyées aux participants

---

### 2. Voter pour la date

**Participants**:
1. Reçoivent une notification
2. Dashboard → Meetings → (select) → Vote
3. Pour chaque date proposée: "Yes" / "No" / "Maybe"
4. Soumettre

**Auto-confirmation**: Une fois tous les votes reçus, la date avec le plus de votes est auto-sélectionnée

---

### 3. Prendre des minutes et les publier

**Pendant la réunion**:
1. Admin → Meetings → (select) → Minutes
2. Ajouter des points:
   - Annonces
   - Décisions
   - Points d'action
3. Sauvegarder

**Après la réunion**:
1. Admin → Meetings → (select) → Minutes → Publish
2. Choisir: publier au comité seulement ou à tous les membres
3. Publier

**Output**: Membres voient les minutes dans Dashboard → Meetings

---

## Subscriptions / Memberships

### 1. Onboarding d'un nouveau membre (Prospect → Member)

**Contact depuis le site public**:
1. Public → Contact → "I'm interested" form
2. Remplit: nom, email, intérêts (entraînement, compétition, etc)

**Secrétaire** (Admin):
1. Admin → Contacts → (voir le prospect)
2. Vérifier les informations
3. "Convert to Member" → crée un User
4. Assigner à une saison

**Trésorier** (Admin):
1. Admin → Subscriptions → (see the subscription)
2. Vérifier que l'affiliation a été payée
3. Si oui, marquer comme CONFIRMED

**Output**: Membre créé, peut s'inscrire à des packs

---

### 2. S'inscrire à des packs (Membre)

**Membre**:
1. Dashboard → Training Packs → Available
2. Choisir les packs voulus
3. Voir le prix total (avec réductions si applicable)
4. "Confirm my subscription"
5. Paiement (intégral ou partiel)

**Trésorier** (optionnel - si paiement manuel):
1. Admin → Payments → (voir le payment)
2. Vérifier que le paiement est arrivé
3. Marquer comme PAID

**Output**: Membre est inscrit au pack, peut voir le schedule

---

### 3. Relances de paiement

**Auto-system**:
- Email rappel envoyé X jours avant la deadline
- Si non-paiement, accès aux packs arrêté automatiquement
- Trésorier peut marquer comme REFUNDED si besoin

---

## Communication

### 1. Rédiger et publier un article

**Président/Secrétaire**:
1. Admin → Articles → Create
2. Entrer:
   - Titre
   - Contenu (markdown support)
   - Catégorie (Résultats, Témoignages, News)
   - Image featured (optionnel)
3. Sauvegarder (DRAFT)
4. Quand prêt: "Publish"

**Output**: Article visible sur le site public

---

### 2. Annoncer un événement

**Président/Secrétaire**:
1. Admin → Events → Create
2. Lier à un événement réel (Tournament / Training / Meeting / Interclub)
3. Entrer:
   - Titre
   - Description (pourquoi c'est cool)
   - Prix (si applicable)
   - Max participants
   - Featured (mettre en avant sur la homepage)
4. Publier

**Output**: Visible sur site public, membres peuvent s'inscrire

---

## Dashboard

### Que voit chaque rôle?

#### Compétiteur
- Mes compétitions (équipes, résultats)
- Mes disponibilités (pour sélections)
- Mes réunions à venir
- Mes entraînements (schedule + présences)
- Mes paiements (statuts)

#### Entraîneur
- Mes sessions d'entraînement (aujourd'hui + semaine)
- Présences/absences à tracker
- Mon pack (participants, stats)

#### Secrétaire
- Tâches en attente (nouveaux contacts, inscriptions à valider)
- Prochains événements
- Packs actifs

#### Trésorier
- Paiements en attente
- Abonnements à confirmer
- Remboursements en cours

#### Président
- Réunions à venir
- Points d'action en attente
- Articles/événements à publier

---

## FAQ

### Q: Comment changer le rôle d'un membre?
**A**: Admin → Users → (select) → Edit → Change Role

### Q: Comment annuler une session d'entraînement?
**A**: Admin → Trainings → Sessions → (select) → Marquer comme "Cancelled" → notification auto-envoyée aux participants

### Q: Comment voir qui a voté pour quelle date lors du vote de réunion?
**A**: Admin → Meetings → (select) → View Votes → voir breakdown par participant

### Q: Que se passe-t-il si un participant n'a pas payé sa subscription avant la deadline?
**A**: Automatiquement bloqué de la sélection (par le système). Trésorier peut débloquer manuellement si besoin.

### Q: Comment gérer une réduction familiale?
**A**: Secrétaire → à la création du Training Pack, cocher "Discount allowed" → lors de l'inscription au pack, si le membre a un `family_id`, la réduction est automatiquement appliquée.

### Q: Je peux créer une réunion sans proposer de date à l'avance?
**A**: Non, au moins 2 dates doivent être proposées. Le système vote automatiquement sur la meilleure date.

### Q: Quel est le statut final d'une sélection (interclub)?
**A**: `has_played: true` — le joueur a effectivement joué le match. Différent de `is_selected` (confirmé par le capitaine).

### Q: Comment voir les archives (anciennes réunions, anciens tournois)?
**A**: Admin → (domaine) → Filter → Archivé → voir les archives

### Q: Peut-on modifier une réunion après avoir publié les minutes?
**A**: Non, elle devient read-only une fois les minutes publiées.

### Q: Comment fusionner deux contacts (si doublon)?
**A**: Admin → Contacts → (select) → Merge with... → (select other contact)

### Q: Quel est le flux de paiement exact?
**A**: 
1. Subscription créée (amount_due calculé)
2. Payment créé (unpaid)
3. User paie (partiel ou intégral)
4. Trésorier valide ou paie manuellement
5. Payment status → paid / partially_paid / refunded

---

## Site public — Calendrier des activités (homepage)

### Comment fonctionne la section "Horaires" de la page d'accueil ?

La section affiche les `TrainingPack` actifs selon une **chaîne de priorité** :

| Priorité | Condition | Bandeau affiché |
|----------|-----------|-----------------|
| 1 | Saison future (non active, `start_at` > maintenant) avec packs | "Ces horaires entrent en vigueur dès le {date}" |
| 2a | Saison active, pas encore commencée | "Ces horaires entrent en vigueur dès le {date}" |
| 2b | Saison active, commencée | Aucun bandeau |
| 3 | Aucune saison active → fallback sur la dernière saison passée avec packs | "Saison terminée – reprise prévue en septembre" |

**Filtres automatiques sur les packs** :
- `is_active = true`
- `day_of_week` non nul (les stages ponctuels sont exclus)
- `pack_end_date` nul ou futur (packs expirés exclus)

### Comment mettre à jour les horaires de la saison suivante ?

1. Créer la nouvelle saison (Admin → Seasons) avec `start_at` = 1er septembre
2. Créer les `TrainingPack` pour cette saison (même si `is_active = false` sur la saison)
3. Le site affiche automatiquement ces horaires avec le bandeau "Dès le 1er septembre"

Quand la saison précédente est terminée et qu'aucune saison n'est active, le site bascule automatiquement sur le fallback (saison passée + bandeau "Saison terminée").

### Comment configurer la ligne "Interclubs" du calendrier ?

La ligne Interclubs est configurable depuis **Admin → Club Settings → Interclub Schedule** (section en bas de page).

Paramètres éditables :
- Activer / désactiver la ligne Interclubs
- Jour de la semaine
- Heure début / heure fin
- Salle
- Description

**Règle** : la ligne Interclubs n'apparaît que lorsqu'une saison existe (active ou future). Elle est masquée automatiquement hors-saison.

Les valeurs sont stockées dans `AppSetting` avec les clés `interclub_schedule_*`.

