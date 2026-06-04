# Plan de développement — Gestion des événements du club de tennis de table

## Objectif

Mettre en place une architecture Laravel robuste, maintenable et extensible pour gérer tous les événements du club :

- réunions
- entraînements
- matches d'interclubs
- tournois
- fêtes / repas
- autres événements futurs

Le système doit permettre :

- la création d’événements génériques
- la gestion des accès et inscriptions
- la gestion de la plublication sur le site web
- la spécialisation métier selon le type d’événement
- les notifications et automatisations futures
- le reporting administratif et sportif

---

# Phase 0 — Analyse métier

## Objectif

Définir précisément les besoins avant toute implémentation technique.

## À faire

### Identifier les types d’événements

- [ ] réunions
- [ ] entraînements
- [ ] matches d'interclubs
- [ ] tournois
- [ ] fêtes / repas
- [ ] autres besoins futurs

### Définir les champs communs

- [ ] titre
- [ ] description
- [ ] date de début
- [ ] date de fin
- [ ] visibilité
- [ ] accès membres
- [ ] prix
- [ ] publication
- [ ] statut
- [ ] organisateur
- [ ] localisation

### Définir les spécificités métier

- [ ] training
- [ ] meeting
- [ ] interclub match
- [ ] tournament
- [ ] social event

### Définir les règles métier

- [ ] qui peut créer
- [ ] qui peut modifier
- [ ] qui peut voir
- [ ] qui peut s’inscrire
- [ ] qui peut annuler
- [ ] règles de paiement
- [ ] gestion des quotas

---

# Phase 1 — Fondation technique

## Objectif

Créer la base commune de tous les événements.

---

## Table `events`

### À prévoir

- [ ] migration
- [ ] model
- [ ] factory
- [ ] seeder
- [ ] policy
- [ ] validation rules

### Champs principaux

- [ ] title
- [ ] description
- [ ] starts_at
- [ ] ends_at
- [ ] visibility
- [ ] access_type
- [ ] price_type
- [ ] price_amount
- [ ] is_online
- [ ] online_meeting_url
- [ ] created_by
- [ ] published_at
- [ ] status
- [ ] event_type

---

## Gestion des enums métier

### À créer

- [ ] EventTypeEnum
- [ ] EventStatusEnum
- [ ] EventVisibilityEnum
- [ ] EventAccessTypeEnum
- [ ] EventPriceTypeEnum

---

## Localisations

## Table `locations`

### À prévoir

- [ ] migration
- [ ] model
- [ ] factory
- [ ] seeder

### Champs

- [ ] name
- [ ] type
- [ ] address
- [ ] capacity

---

## Pivot `event_location`

### À prévoir

- [ ] migration
- [ ] relations many-to-many

---

# Phase 2 — Gestion des accès et inscriptions

## Objectif

Définir qui peut voir et participer aux événements.

---

## Groupes de membres

## Table `member_groups`

### À prévoir

- [ ] comité
- [ ] jeunes
- [ ] vétérans
- [ ] interclubs
- [ ] bénévoles
- [ ] autres groupes

---

## Pivot `event_group_access`

### À prévoir

- [ ] migration
- [ ] relations

---

## Inscriptions

## Table `event_registrations`

### À prévoir

- [ ] migration
- [ ] model
- [ ] policy
- [ ] validations

### Champs

- [ ] event_id
- [ ] member_id
- [ ] status
- [ ] paid_at
- [ ] amount_paid
- [ ] notes

---

## États d’inscription

- [ ] registered
- [ ] waiting_list
- [ ] cancelled
- [ ] confirmed

---

# Phase 3 — Événements spécialisés

## Objectif

Ajouter les modèles métier spécifiques.

---

# Training Events

## Table `training_events`

### Champs

- [ ] event_id
- [ ] coach_id
- [ ] level
- [ ] max_players
- [ ] required_material

---

# Meeting Events

## Table `meeting_events`

### Champs

- [ ] event_id
- [ ] agenda
- [ ] minutes
- [ ] mandatory_attendance

---

# Interclub Matches

## Table `interclub_matches`

### Champs

- [ ] event_id
- [ ] team_id
- [ ] opponent
- [ ] home_or_away
- [ ] score
- [ ] match_sheet

---

# Tournaments

## Table `tournaments`

### Champs

- [ ] event_id
- [ ] format
- [ ] category
- [ ] bracket_type
- [ ] external_registrations

---

# Social Events

## Table `social_events`

### Champs

- [ ] event_id
- [ ] menu
- [ ] max_guests
- [ ] provider

---

# Phase 4 — Interface d’administration

## Objectif

Permettre une gestion simple depuis le back-office.

---

## CRUD administration

### À prévoir

- [ ] création
- [ ] édition
- [ ] publication
- [ ] annulation
- [ ] archivage

---

## Composants Blade réutilisables

- [ ] tableaux triables
- [ ] filtres
- [ ] recherche
- [ ] cartes d’information
- [ ] actions rapides
- [ ] badges de statut

---

## Gestion des permissions

- [ ] admin
- [ ] comité
- [ ] entraîneurs
- [ ] responsables interclubs

---

# Phase 5 — Automatisation

## Objectif

Réduire les tâches manuelles.

---

## Notifications

- [ ] confirmation inscription
- [ ] rappel événement
- [ ] annulation
- [ ] changement d’horaire
- [ ] changement de salle

---

## Synchronisation calendrier

- [ ] export iCal
- [ ] Google Calendar
- [ ] Outlook

---

## Documents

- [ ] PDF réunion
- [ ] feuilles de match
- [ ] listes de présence
- [ ] reçus de paiement

---

# Phase 6 — Reporting

## Objectif

Mesurer l’activité du club.

---

## Statistiques

- [ ] fréquentation entraînements
- [ ] présence réunions
- [ ] participation tournois
- [ ] occupation des salles
- [ ] revenus événements
- [ ] dépenses organisation

---

# Architecture Laravel

## À respecter

- [ ] Form Requests
- [ ] Policies
- [ ] Actions
- [ ] DTO si nécessaire
- [ ] Services métier si utile
- [ ] Events / Listeners si pertinent

---

## À éviter

- [ ] Fat Controllers
- [ ] Fat Models
- [ ] logique métier dans les vues
- [ ] duplication de règles métier

---

# Priorité recommandée

## Commencer par

- [ ] events
- [ ] locations
- [ ] event_registrations
- [ ] training_events
- [ ] interclub_matches

## Ensuite

- [ ] meeting_events
- [ ] tournaments
- [ ] social_events

## Enfin

- [ ] automatisations
- [ ] reporting
- [ ] synchronisations externes

---

# Notes projet

## Décisions techniques

### À documenter ici

---

## Questions ouvertes

### À documenter ici

---

## Arbitrages métier

### À documenter ici

---

# Règle principale

Ne pas chercher la perfection initiale.

Objectif :

## stable + extensible

et non :

## parfait + bloquant