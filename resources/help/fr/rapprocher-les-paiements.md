---
title: Rapprocher les paiements avec la banque
summary: Importer l'extrait, laisser l'application apparier ce qu'elle peut, traiter le reste à la main.
audience: treasurer, committee
order: 6
---

Le travail se fait en trois temps : **importer l'extrait bancaire**, **lancer le rapprochement automatique**, puis **traiter à la main ce qui reste**. Ce qui reste est toujours la partie intéressante.

## 1. Importer l'extrait

L'import se fait depuis **Trésorerie → Transactions**. Formats acceptés : `.csv`, `.ods`, `.xlsx`, `.xls`, `.txt`.

Le fichier doit avoir une **ligne d'en-tête**, et les colonnes sont reconnues **par leur nom** — celui des exports bancaires belges francophones :

`Date` · `Description` · `Montant` · `Nom contrepartie` · `Numéro de compte contrepartie` · `Communication structurée` · `Communication libre`

La casse et les accents n'ont pas d'importance. Le nom, si.

Le rapport vous dit ce qui s'est passé : les nouvelles, les doublons écartés, et les erreurs éventuelles — celles-là sont détaillées dans l'historique d'import, ligne par ligne, avec le motif du rejet. **Vous pouvez réimporter le même extrait sans crainte** : les lignes déjà connues sont reconnues et ignorées.

Un import qui annonce **0 nouvelle et 0 doublon** est presque toujours un problème d'en-têtes : mauvaise langue d'export, ou colonnes renommées.

## 2. Le rapprochement automatique

Lancez-le, il vous propose une liste, vous validez en bloc.

**Il n'apparie que les cas parfaits**, et « parfait » veut dire les deux à la fois :

- la **communication structurée** correspond exactement à celle du paiement attendu ;
- le **montant correspond au centime près**.

Tout le reste lui échappe, et c'est voulu. En pratique, ça laisse dehors :

- le membre qui a payé **le bon montant sans la communication** (ou avec une communication libre) ;
- le membre qui a mis **la bonne communication mais pas le bon montant** — paiement partiel, ancien tarif, arrondi ;
- les paiements en plusieurs fois.

Si l'application annonce **« Aucune correspondance parfaite »**, ça ne veut pas dire que personne n'a payé. Ça veut dire qu'aucun paiement ne remplit les deux conditions — passez à la main.

## 3. Rapprocher à la main

Sur un paiement en attente, ouvrez le rapprochement : vous choisissez vous-même la transaction bancaire qui lui correspond. C'est là que vous traitez les cas que l'automatique a laissés.

Une transaction déjà rapprochée à un paiement ne sera plus proposée ailleurs — pas de risque de la compter deux fois.

## Relancer les impayés

Depuis la liste, sélectionnez les paiements concernés et envoyez le rappel groupé.

Si aucun e-mail ne semble partir, ne cherchez pas du côté des adresses : regardez le tableau de bord. Une alerte **« File d'attente bloquée »** signifie que **rien** ne sort de l'application, rappels compris. C'est une panne technique, pas un problème de trésorerie.

## Une chose à ne pas confondre

Un paiement n'existe que si l'inscription a été **approuvée** — c'est l'approbation qui calcule le montant et génère la communication structurée. Un membre qui vous dit « je n'ai rien à payer » a peut-être simplement une inscription encore en attente. Voyez [Affilier un membre pour la saison](affilier-un-membre).
