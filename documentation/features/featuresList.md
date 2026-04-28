# 🚀 Feature Tracker & Roadmap

> [!TIP]
> Ce document centralise le suivi des fonctionnalités et l'historique des versions du projet.

---

## 📊 État Actuel
| Version | Statut | Branche | Dernière Mise à Jour |
| :--- | :--- | :--- | :--- |
| **v0.9.1** | 🟢 Stable | `main` | 19/04/2026 |

---

## 🗺️ Roadmap

### 🗓️ Court Terme (v1.0)
- [x] **Refonte du UI/UX** : Refonte du backend existant sur un UX plus homogène, basé sur les stack Livewire/Mary-UI.
    - [x] **Gestion des infos du club**  : définir les info du club (contact, localisation, numéro de compte)
    - [x] **Gestion du comité** : ajouter ou supprimer un membre dans le comité et lui assigner un rôle
    - [x] **Gestion des tables**  : ajouter des tables, les lier à une salle, décrire leur état général
    - [x] **Gestion des salles du club**  : ajouter des salles et définir leur adresse et leur capacité, afficher les évènements à venir
    - [ ] **Gestion des membres club**  : Afficher tous les membres, les cherches, afficheur leurs infos principales
    - [ ] **Gestion du profil**  : Permettre aux membres d'adapter certaines informations (avatars, info de contacts,...)
    - [ ] **Gestion des préférences**  : Permettre aux membres de configurer leurs préférences 
- [ ] **Stabilisation des features existantes** : Débugage, optimization et création de test pour les features. Refonte architecturale. Suppression du code obsolète.

### 🗓️ Moyen Terme (v1.x)

- [ ] **Publication des résultats** : Remplacer les résultats hardcodés par un outil UI afin de les publier plus facilement/sans code change
- [ ] **Gestion de saisons** : Permettre au comité d'ouvrir/fermer une saison et gérer les inscriptions
- [ ] **Gestion de entraînements** : Permettre au comité d'ouvrir/fermer des sessions d'entraînements et de gérer les inscriptions/présences
- [ ] **Assistant de composition d'équipes** : Aider les capitaines et les joueurs à documenter leurs disponbilités et à avoir une vue sur les sélections en cours et à venir
- [ ] **Éditeur d'événement et d'articles** : Améliorer les fonctionnalité pour publier un article ou un évènement (images, mise en forme du texte...)
- [ ] **Gestion de tournoi** : Permettre au comité de gérer de bout en bout un tournoi amateur
- [ ] **Récupération des résultats depuis l'API de la fédé** : Plutôt que d'encoder manuellement les résultats et les calendriers des matches à venir, les récupérer depuis la source pour réduire l'administration




---

## 🛠️ Changelog


### [v0.9.1] - 2026-03-23
> **Focus** : Réaction à l'attaque en ligne

#### ✨ Nouvelles Fonctionnalités
- Implémentation d'un CAPTCHA

#### 🪳 Correction de bug
Non documenté

#### 🔒 Correction de faille de sécurité
- Suppression de création de compte par les visiteurs
- hardening du site web (blocage d'exécution sur les dossier public, non lié au code)

---

### [v0.9] — 2026-02-15
> **Focus** : Gestion des paiements

#### ✨ Nouvelles Fonctionnalités
- Génération d'information de paiments (QR code, communication structurée...)
- Gestion des affiliations
- Gestion des paiements

#### 🪳 Correction de bug
Non documenté

---

### [v0.8] — 2025-12-15
> **Focus** : Gestion des tournois

#### ✨ Nouvelles Fonctionnalités
- Création d'un tournoi
- Gestion des inscriptions à un tournoi
- Gestion des poules et des phases éliminatoires

#### 🪳 Correction de bug
Non documenté

---

### [v0.7] — 2025-11-15
> **Focus** : Gestion des entraînements

#### ✨ Nouvelles Fonctionnalités
- Création et gestion des entraînements

#### 🪳 Correction de bug
Non documenté

---

### [v0.6] — 2025-10-15
> **Focus** : Gestion de l'infrastruture du club

#### ✨ Nouvelles Fonctionnalités
- Création et gestion des salles
- Création et gestion des tables

#### 🪳 Correction de bug
Non documenté

---

### [v0.5] — 2025-09-25

> **Focus** : Filtre antispam

#### ✨ Nouvelles Fonctionnalités
- Mise en place d'un honey pot pour détecter le spam
- Consignation du spam dans les logs et dans la base de données pour audit.

#### 🪳 Correction de bug
Non documenté

---

### [v0.4] — 2025-09-15
> **Focus** : Gestion des équipes et des matches

#### ✨ Nouvelles Fonctionnalités
- Création d'équipes et compositions avec leurs membres
- Encodage des matches

#### 🪳 Correction de bug
Non documenté

---

### [v0.3] — 2025-09-05
> **Focus** : Évènements et articles

#### ✨ Nouvelles Fonctionnalités
- Rédaction et publication d'articles ou d'évènements sur le site web

#### 🪳 Correction de bug
Non documenté

---

### [v0.2] — 2025-08-30
> **Focus** : Formulaire de contact

#### ✨ Nouvelles Fonctionnalités
- Ajout d'un formulaire de ccontact pour les visiteurs
- Chaque formulaire génère un mail dans la mailbox du comité

#### 🪳 Correction de bug
Non documenté

---

### [v0.1] — 2025-08-15
> **Focus** : délivrer un site de base

#### ✨ Nouvelles Fonctionnalités
- MVP1 : site public pour assurer une présence en ligne du club.
- Création du Dashboard principal.
- Système de CRUD pour les entités de base.
- Mode sombre (Dark Mode) natif.

---

## 📝 Spécifications des Features (En cours)

### `[FEAT-003]` Système de Cache Redis
- **Priorité** : 🟠 Moyenne
- **Issue liée** : #42
- **Description** : Mise en place d'une couche de cache pour les requêtes de recherche.
- **Liste de contrôle** :
  - [x] Installation de l'instance Redis.
  - [ ] Configuration de la connexion dans `.env`.
  - [ ] Implémentation de la logique d'invalidation du cache.

---

## 💡 Idées & Backlog
- [ ] Intégration d'un mode hors-ligne (PWA).
- [ ] Support multilingue (i18n).
- [ ] Export des données en format CSV/PDF.

---

## 📈 Statistiques du Projet
![Version](https://img.shields.io/badge/version-1.2.0-blue.svg)
![Build](https://img.shields.io/badge/build-passing-brightgreen.svg)
![License](https://img.shields.io/badge/license-MIT-lightgrey.svg)

---
*Dernière synchronisation du document : 19 avril 2026*