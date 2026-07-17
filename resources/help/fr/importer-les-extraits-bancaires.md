---
title: Importer et gérer les extraits bancaires
summary: Alimenter la liste des transactions depuis votre banque, sans doublons et sans perte.
audience: treasurer, committee
order: 12
---

C'est la matière première du rapprochement : sans transactions, rien à apparier. **Trésorerie → Transactions.**

## Importer

Exportez l'extrait depuis votre banque, puis déposez le fichier. Formats acceptés : `.csv`, `.ods`, `.xlsx`, `.xls`, `.txt`.

Le fichier doit avoir une **ligne d'en-tête**. Les colonnes sont reconnues **par leur nom**, celui des exports belges francophones :

`Date` · `Description` · `Montant` · `Nom contrepartie` · `Numéro de compte contrepartie` · `Communication structurée` · `Communication libre`

Accents et majuscules sont ignorés. Le libellé, non : une colonne renommée ou exportée dans une autre langue ne sera pas vue.

## Réimporter sans risque

**Chaque ligne reçoit une empreinte.** Si vous réimportez un extrait qui recouvre une période déjà chargée, les lignes connues sont **reconnues et écartées** — pas de doublons.

C'est fait exprès : exportez large, importez souvent, sans faire de calculs de dates. Le rapport vous dira exactement ce qui s'est passé :

> *« 23 nouvelle(s) transaction(s) importée(s). 15 doublon(s) ignoré(s). »*

## Quand il y a des erreurs

Le message passe en **avertissement** et annonce le nombre de lignes rejetées. **L'historique d'import garde chacune d'elles** : le numéro de ligne, son contenu, et le motif du rejet.

Allez le lire. Une ligne rejetée est une opération bancaire absente de votre comptabilité — c'est le genre de trou qu'on ne retrouve pas six mois plus tard.

Un import qui annonce **0 nouvelle et 0 doublon** signifie que rien n'a été reconnu : neuf fois sur dix, ce sont les en-têtes.

> ⚠️ **N'utilisez pas l'import proposé sur la page Paiements.** C'est un ancien double de celui-ci : il ne détecte pas les doublons et perd les lignes illisibles sans le dire. Toujours passer par **Transactions**. Les deux alimentent la même liste.

## Retrouver une opération

La recherche couvre le **nom de la contrepartie**, les **communications** (structurée et libre) et la **description**. Vous pouvez filtrer par période, par sens (entrées / sorties) et par état de rapprochement.

> **Le compteur « à rapprocher » et le filtre « non rapprochées » ne disent pas la même chose.** Le compteur ne retient que les **entrées d'argent** — les seules qui peuvent correspondre à une cotisation. Le filtre, lui, affiche aussi toutes les **sorties**, qui ne seront jamais rapprochées à quoi que ce soit. Un écart entre les deux chiffres est normal, ce n'est pas un compte faux.

## Supprimer des transactions

Possible, en lot. **Et une transaction déjà rapprochée peut être supprimée** — l'application vous prévient qu'il y en a dans votre sélection, mais elle ne vous en empêche pas.

Si vous le faites, **le paiement correspondant redevient non rapproché**, sans que personne ne vous le signale. Réservez la suppression aux lignes importées par erreur, et vérifiez toujours l'avertissement de sélection avant de confirmer.
