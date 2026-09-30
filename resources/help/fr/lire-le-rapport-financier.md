---
title: Lire le rapport financier
summary: Les recettes, les dépenses et la trésorerie d'un exercice sur un seul écran, comparées à l'année d'avant, et l'export complet pour l'assemblée générale et les vérificateurs.
audience: financial_report
order: 28
---

Le rapport financier rassemble tout l'argent du club sur un exercice : ce que le site a encaissé (cotisations, entraînements, tournois, bar) et ce que la trésorerie a justifié par des pièces. C'est l'écran à ouvrir avant une réunion de comité, et celui qui prépare l'assemblée générale.

**Trésorerie → Rapport financier**, en tête du menu. Il est ouvert au **comité**, aux **vérificateurs aux comptes** et à la **trésorerie**, en lecture seule. La délégation **Notes de frais** seule n'y donne pas accès.

## L'exercice

En haut de l'écran, choisissez l'**exercice**. Il commence le mois fixé dans **Paramètres du club → Informations → Début de l'exercice comptable** — **janvier** si personne n'y a touché. Un exercice de janvier à décembre s'appelle « 2026 », un exercice de septembre à août « 2025-2026 ».

**L'argent compte le jour où il bouge**, jamais à la date de la facture : une facture du 28 décembre payée le 10 janvier appartient à l'exercice suivant. Une pièce **à régler** n'entre dans aucun total de recettes ou de dépenses : elle apparaît dans les dettes et les créances ouvertes.

## Les huit cartes

| Carte | Ce qu'elle dit |
|---|---|
| **Recettes** | tout l'argent entré, banque et caisses, sans les mouvements internes |
| **Dépenses** | tout l'argent sorti, de la même façon |
| **Résultat** | recettes moins dépenses |
| **Argent justifié** | la part des mouvements de l'exercice qui sont clôturés, en nombre et en montant |
| **Membres qui doivent encore de l'argent** | parmi les membres actifs, ceux qui ont au moins un paiement en attente |
| **Argent encore attendu** | les paiements en attente et les recettes à régler (un subside promis) |
| **Ce que le club doit encore** | les remboursements à faire (notes de frais acceptées comprises) et les factures à régler |
| **Trésorerie** | l'argent détenu : chaque compte bancaire et chaque caisse |

Les trois premières se comparent à l'exercice précédent : la flèche donne le sens, le pourcentage l'écart. **La couleur dit si c'est bon pour le club**, pas si le chiffre monte : des dépenses qui baissent sont en vert.

Un mouvement est **justifié** quand il est rapproché d'un paiement du site, couvert par une pièce justificative, que son solde a été abandonné, ou qu'il s'agit d'un mouvement interne. Le reste est **à traiter** : un lien mène directement à ces lignes dans **Transactions bancaires**.

### La trésorerie

Le solde d'un compte est celui que la banque a imprimé sur la **dernière ligne importée**. La carte dit de combien la trésorerie a bougé depuis le début de l'exercice. Si le dernier solde connu d'un compte a plus d'un mois, la carte le signale (« dernier solde connu au … ») : importez un extrait récent avant de présenter les chiffres.

## Les graphiques

- **Trésorerie au fil de l'exercice** : l'argent détenu à chaque fin de mois, compte par compte, les caisses au-dessus.
- **Recettes et dépenses par mois**, avec une ligne pour le résultat qui se construit au fil de l'année.
- **Dépenses par poste** et **Recettes par poste** : l'exercice en couleur, le précédent en gris, du plus grand au plus petit.
- **Clôture des mouvements** : comment les mouvements ont été clôturés, en nombre et en montant.

Passez la souris (ou le doigt) sur une barre pour lire son montant. **Voir les chiffres** ouvre le tableau derrière le graphique.

Sous les graphiques, les tableaux par poste donnent le détail. Deux d'entre eux méritent un mot :

- **Entraînements** met côte à côte ce que les entraînements rapportent (la part entraînement des affiliations) et ce qu'ils coûtent (le poste *Entraînement & formation*), avec le solde ;
- **Cotisations par type de licence** sépare récréatifs et compétiteurs.

Enfin, **Mouvements internes** liste l'argent passé d'un compte du club à un autre, ou entre la caisse et la banque : ni recette, ni dépense, mais visible.

**Imprimer** donne une version papier de l'onglet.

## Pièces & exports

Le second onglet liste tout ce qui fonde les chiffres de l'exercice : les **pièces justificatives**, les **notes de frais** payées et les **paiements du site** rapprochés.

C'est aussi là que l'on **exporte** l'exercice :

- **PDF imprimable** : le journal de tous les mouvements (banque et caisses), puis chaque pièce et chaque note de frais avec ses justificatifs imprimés. C'est le document à remettre aux vérificateurs.
- **Archive ZIP (originaux)** : le journal en tableur (CSV, pour Excel) et les **fichiers originaux** de chaque pièce et de chaque note de frais, intacts.

Le rapport lui-même (tuiles, graphiques, tableaux par poste) est déjà dans l'onglet **Vue d'ensemble** : il n'entre dans l'export que si vous cochez **Inclure le rapport financier**. Il ouvre alors le PDF, et rejoint le ZIP sous le nom `rapport-financier.pdf`. Pour l'assemblée générale, cochez-le.

Avant d'exporter, vous pouvez aussi limiter l'export à un **poste**, ou aux seules pièces ou seules notes de frais.

L'export se prépare en arrière-plan. Quand il est prêt, vous recevez un **e-mail** avec le lien, la **cloche** le signale, et **Mes exports** affiche un bouton **Télécharger**. Le lien n'est valable que **7 jours** et seulement pour vous ; ensuite, relancez l'export.

### L'archivage des notes de frais

Les justificatifs des notes de frais ne vivent que sur le serveur de l'application. **Télécharger le ZIP d'un exercice les archive** : les notes payées de cet exercice sont marquées comme rangées dans les archives du club.

Ce téléchargement n'archive que s'il est fait par **la trésorerie** — ou par un membre du comité qui traite aussi les notes de frais. Un vérificateur ou un autre membre du comité télécharge le même ZIP sans rien archiver. Le relais du trésorier (la délégation **Notes de frais**, sans être au comité) décide des notes, mais ne les exporte ni ne les archive.

Tant que des notes payées attendent leur archivage, l'onglet l'indique, exercice par exercice. Un rappel est aussi envoyé à chaque trimestre de l'exercice, et le 5 du mois qui suit sa clôture.
