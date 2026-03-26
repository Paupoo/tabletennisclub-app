# Outil d’Organisation de Tournois de Tennis de Table

## Objectif

Concevoir un système capable d’aider un administrateur à configurer un tournoi **réalisable dans un temps donné**, en tenant compte des contraintes physiques et sportives.

Le système doit agir comme un **assistant de faisabilité**, et non comme un simple formulaire de configuration.

---

## Principe Fondamental

Un tournoi est un problème d’**ordonnancement contraint**.

La question centrale :

> Combien de matchs peuvent réellement être joués dans le temps disponible ?

Formule conceptuelle :

```
Temps disponible × Nombre de tables = Capacité totale de matchs
```

Toutes les décisions sportives doivent découler de cette capacité.

---

## 1. Ressources (Contraintes Fixes)

Éléments physiques du tournoi :

* Date
* Lieu
* Salles
* Nombre de tables par salle
* Durée totale du tournoi
* Temps logistique entre matchs

Ces paramètres définissent la **capacité maximale**.

---

## 2. Paramètres Sportifs (Variables)

Choix configurables par l’administrateur :

* Simple / Double
* Nombre de sets gagnants
* Taille des poules
* Nombre de poules
* Nombre de qualifiés
* Type de phase finale

Ces paramètres impactent directement la durée totale.

---

## 3. Durée Moyenne d’un Match

Concept critique.

Estimation :

```
Durée_match =
    durée_set_moyenne
  × nombre_sets_moyen
  + buffer_logistique
```

Exemples indicatifs :

| Format | Durée moyenne |
| ------ | ------------- |
| BO3    | 10–12 min     |
| BO5    | 18–22 min     |
| Double | +20%          |

Le système repose sur des **estimations statistiques**.

---

## 4. Capacité Maximale du Tournoi

Calcul :

```
matchs_par_table = durée_totale / durée_match
matchs_totaux = matchs_par_table × nombre_tables
```

Résultat :

➡️ Budget total de matchs disponibles.

---

## 5. Phase de Poules

Nombre de matchs dans une poule :

```
n joueurs → n(n−1)/2 matchs
```

Exemples :

| Joueurs | Matchs |
| ------- | ------ |
| 3       | 3      |
| 4       | 6      |
| 5       | 10     |

Attention : croissance rapide du nombre de matchs.

---

## 6. Phase Finale (Élimination Directe)

Formule :

```
N joueurs → N − 1 matchs
```

Structure prévisible et facile à estimer.

---

## 7. Assistant de Configuration

Le workflow recommandé :

### Étape 1 — Capacité

* Durée du tournoi
* Tables disponibles

➡️ Calcul automatique de la capacité maximale.

### Étape 2 — Objectif

Intentions possibles :

* Maximiser le nombre de joueurs
* Minimiser la durée
* Maximiser le nombre de matchs par joueur
* Format loisir
* Format compétitif

### Étape 3 — Simulation Dynamique

Modification en temps réel :

* Nombre de joueurs
* Taille des poules
* Sets
* Qualifiés

Indicateurs affichés :

* Durée estimée
* Occupation des tables
* Temps d’attente moyen
* Dépassement potentiel
* Marge de sécurité

---

## 8. Réalisme Opérationnel

Un tournoi réel n’est jamais parfaitement parallèle.

Facteurs :

* Joueurs rejouant plusieurs matchs
* Retards cumulés
* Arbitrage
* Organisation

Introduire un coefficient :

```
coefficient_congestion ≈ 1.2 → 1.4
```

---

## 9. Moteur Central : Tournament Simulator

Le simulateur ne génère pas le tournoi.

Il valide la faisabilité.

### Entrées

* Contraintes physiques
* Format sportif

### Sorties

* Durée estimée
* Utilisation des tables
* Temps d’attente moyen
* Faisabilité (bool)
* Niveau de risque

---

## 10. Architecture Conceptuelle

Séparation stricte :

1. Infrastructure

   * Salles
   * Tables
   * Durée

2. Modèle Sportif

   * Poules
   * Brackets

3. Simulation Temporelle ⭐

4. Génération Réelle du Tournoi

   * Création des matchs
   * Planning

---

## 11. Vision Produit

L’utilisateur ne configure plus directement un tournoi.

Il explore un **espace de configurations réalisables**.

Le système peut proposer automatiquement :

> Configuration optimale pour un temps et un nombre de tables donnés.

---

## Prochaines Étapes

* Définir un modèle abstrait minimal du tournoi
* Formaliser les objets métier
* Concevoir le moteur de simulation
* Définir les métriques UX à afficher
