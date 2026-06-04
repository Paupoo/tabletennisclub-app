# Domaines métier — Table Tennis Club App

> Cette application est organisée autour de **6 domaines métier indépendants**. Chaque domaine a sa propre logique, ses modèles, et ses workflows.

---

## 1. Competitions (Équipes + Interclubs)

**Objectif**: Gérer les équipes du club engagées en compétition ligue et les matchs inter-clubs.

### Tables impliquées
- `teams`, `leagues`, `seasons`, `match_results`
- `interclubs`, `interclub_user`, `match_sets`, `tournament_matches`

### Features principales

#### Teams (Équipes ligue)
- Créer/gérer les équipes engagées en compétition officielle
- Assigner un capitaine par équipe
- Tracker les résultats ligue (wins/losses/position)
- Gérer les membres du noyau de chaque équipe

#### Interclubs (Matchs inter-clubs)
- Créer/planifier les matchs inter-clubs (semaine, adversaire, lieu)
- Gérer le planning de la saison (52 semaines)
- **Sélections**: permettre aux joueurs de donner leur disponibilité (disponible/indisponible/incertain)
- Permettre au sélectionneur de confirmer les sélections (par match)
- Tracker les résultats (score, joueurs qui ont joué, forfaits)
- Gérer les remplaçants et les absences

### Personas & usage

| Persona | Actions |
|---------|---------|
| **Sélectionneur** | Créer saison + planning, superviser sélections club-wide, résoudre conflits (manque joueurs, malades) |
| **Capitaine (1+ par équipe)** | Gérer noyau, sélectionner joueurs pour chaque match interclub, faciliter sélectionneur |
| **Compétiteur** | Donner disponibilité, voir sa sélection, tracker résultats |

### États/Workflows

```
Interclub:
  - PLANNING → INSCRIPTIONS_OUVERTES → EN_COURS → RESULTAT
  - À chaque état, différentes actions disponibles/interdites
```

---

## 2. Trainings (Entraînements)

**Objectif**: Gérer les sessions d'entraînement, les packs (abonnements), et tracker les présences.

### Tables impliquées
- `trainings`, `training_packs`, `training_user`
- `rooms` (where trainings happen)

### Features principales

#### Training Packs (Abonnements)
- Créer des packs d'entraînement par niveau (débutant, intermédiaire, avancé)
- Assigner un entraîneur par pack
- Définir jour/heure, durée, salle
- Fixer le prix et les réductions familiales
- Fixer capacité max de participants
- Gérer les dates d'ouverture/fermeture du pack
- Support: listes d'attente, confirmations, deadlines

#### Training Sessions (Sessions individuelles)
- Créer sessions à partir des packs (ou standalone)
- Tracker présences/absences
- Annuler une session (avec notification)
- Voir qui est attendu/absent/en retard

### Personas & usage

| Persona | Actions |
|---------|---------|
| **Secrétaire** | Créer packs, assigner entraîneurs, encoder sessions, inviter membres |
| **Entraîneur** | Tracker présences/absences, annuler session si besoin |
| **Membre** | S'inscrire à packs, voir schedule, se désinscrire |

### États/Workflows

```
TrainingPack:
  - CREATION → OPEN_REGISTRATION → ONGOING → CLOSED
  
TrainingSession:
  - SCHEDULED → IN_PROGRESS → COMPLETED / CANCELLED
```

---

## 3. Meetings (Réunions du comité)

**Objectif**: Organiser les réunions du comité (AG, réunions comité, réunions festives).

### Tables impliquées
- `meetings`, `meeting_user`, `meeting_date_proposals`, `meeting_date_votes`
- `meeting_agenda_items`, `meeting_action_items`, `meeting_minutes`

### Features principales

#### Créer/Planifier une réunion
- Titre, type (AG / comité / festive), description
- Format (en personne / zoom / hybride)
- Lieu et lien zoom
- Inviter membres (ou juste comité)
- Définir deadline pour RSVP

#### Voter pour une date
- Proposer plusieurs dates
- Membres votent (oui/non/incertain)
- Auto-sélection de la date avec plus de votes
- Auto-confirmation si date validée

#### Agenda & Minutes
- Ajouter items à l'agenda
- Prendre des notes pendant la réunion
- Marquer les décisions et les points d'action
- Publier les minutes (au comité ou à tous les membres)

### Personas & usage

| Persona | Actions |
|---------|---------|
| **Président** | Créer réunion, gérer agenda, orchestrer vote, publier minutes |
| **Comité members** | Voter pour la date, contribuer à l'agenda, consulter minutes |
| **Tous les membres** | Voir réunion festive, RSVP, consulter minutes si publiques |

### États/Workflows

```
Meeting:
  - PLANNING → DATE_VOTING → SCHEDULED → IN_PROGRESS → COMPLETED
  
DateProposal:
  - OPEN → VOTED → SELECTED or ABANDONED
```

---

## 4. Subscriptions/Memberships (Adhésions & Paiements)

**Objectif**: Gérer les adhésions au club, les paiements, et les affiliation status.

### Tables impliquées
- `subscriptions`, `subscription_training_pack`
- `payments`, `users`, `contacts`

### Features principales

#### Affiliation (Adhésion annuelle)
- Créer demande d'affiliation (depuis le site public)
- Secrétaire valide les données et les infos de paiement
- Trésorier vérifie/confirme le paiement
- Status: PENDING → APPROVED → PAID / REJECTED

#### Subscriptions (Abonnements aux packs)
- Chaque saison, créer subscription au club
- Ajouter packs de training que le membre veut suivre
- Calculer le prix (avec réductions si applicable)
- Gérer paiements (intégral ou partiel)
- Tracker la deadlines de confirmation
- Support: listes d'attente

#### Paiements
- Lier à subscription ou affiliation
- Status: UNPAID → PAID / PARTIALLY_PAID / REFUNDED / CANCELLED
- Tracker remboursements
- Gérer relances (email rappels)

### Personas & usage

| Persona | Actions |
|---------|---------|
| **Secrétaire** | Onboard nouveaux membres (création contact → affiliation → subscription) |
| **Trésorier** | Valider paiements, tracker statuts, gérer remboursements |
| **Membre** | Voir affiliation status, packs disponibles, paiements en attente |

### États/Workflows

```
Subscription:
  - PENDING → CONFIRMED → PAID / PARTIALLY_PAID → [REFUNDED]
  
Payment:
  - UNPAID → PAID or PARTIALLY_PAID → [REFUNDED or CANCELLED]
```

---

## 5. Communication (Articles & News)

**Objectif**: Partager la vie du club via des articles, mettre en avant les résultats, et alimenter la vitrine publique.

### Tables impliquées
- `news_posts`, `event_posts` (polymorphe)

### Features principales

#### News Articles (Articles humains)
- Rédiger des articles sur la vie du club
- Exemples: "Résumé du tournoi X", "Portrait des entraîneurs", "Merci aux bénévoles"
- Publier sur le site public
- Catégoriser (résultats, témoignages, news)
- Images/featured image

#### Event Posts (Posts liés aux événements)
- Annoncer les événements importants (AG, tournoi public, stage, événement festif)
- Lier à l'événement réel (Tournament, Training, Meeting, Interclub)
- Décrire l'offre (date, prix, max participants, points forts)
- Status: DRAFT → PUBLISHED → ARCHIVED

### Personas & usage

| Persona | Actions |
|---------|---------|
| **Président/Secrétaire** | Rédiger et publier articles, annoncer événements |
| **Public** | Lire articles, découvrir l'offre, voir que le club est vivant |

### États/Workflows

```
NewsPost:
  - DRAFT → PUBLISHED → [DELETED]
  
EventPost:
  - DRAFT → PUBLISHED → ARCHIVED / DELETED
```

---

## 6. Resources (Ressources partagées)

**Objectif**: Gérer l'infrastructure du club (salles d'entraînement et tables de ping-pong).

### Tables impliquées
- `rooms`, `tables`, `club_room`, `room_tournament`

### Features principales

#### Rooms (Salles)
- Créer/gérer les salles d'entraînement
- Définir la capacité (entraînements vs interclubs)
- Adresse, accès, équipements
- Lier au club

#### Tables (Tables de ping-pong)
- Tracker chaque table dans chaque salle
- État (neuve, bon état, à réparer, hors service)
- Utilisation (entraînement, interclub, tournoi)

### Personas & usage

| Persona | Actions |
|---------|---------|
| **Secrétaire** | Ajouter/modifier salles et tables, tracker état |
| **Entraîneur/Sélectionneur** | Réserver salle pour entraînement ou interclub |

---

## Concept transversal: Events (Polymorphe)

Tous les **événements** auxquels les membres peuvent participer:
- **Tournaments** (public + internal)
- **Trainings** (pack sessions)
- **Meetings** (AG, comité, festive)
- **Interclubs** (matches de ligue)

Chaque événement:
- A un **status** (planning, open, ongoing, completed)
- Peut générer une **news article** (humain décide d'écrire après)
- Peut avoir des **résultats** (score, participants, feedback)
- Peut avoir des **paiements** associés (inscription payante)

---

## Familia (Concept en cours)

Support pour les familles:
- Lier les membres par family_id
- Réductions familiales sur les packs
- Contact responsable pour mineurs (< 18 ans)

**Status**: Partiellement implémenté, à affiner.

---

## Résumé des rôles par domaine

| Rôle | Competitions | Trainings | Meetings | Subscriptions | Communication | Resources |
|------|-------------|-----------|----------|---------------|---------------|-----------|
| Entraîneur | — | ✅ | — | — | — | ✅ |
| Secrétaire | — | ✅ | — | ✅ | ✅ | ✅ |
| Trésorier | — | — | — | ✅ | — | — |
| Président | — | — | ✅ | — | ✅ | — |
| Sélectionneur | ✅ | — | — | — | — | — |
| Capitaine | ✅ | — | — | — | — | — |
| Compétiteur | ✅ | ✅ | — | ✅ | — | — |

