---
title: Importer et gérer les extraits bancaires
summary: Alimenter la liste des transactions depuis votre banque, sans doublons et sans perte.
audience: treasurer, committee
order: 14
---

C'est la matière première du rapprochement : sans transactions, rien à apparier. **Trésorerie → Transactions.**

## Importer

Exportez l'extrait depuis votre banque, puis déposez le fichier. Formats acceptés : `.csv`, `.ods`, `.xlsx`, `.xls`, `.txt`.

Le fichier doit avoir une **ligne d'en-tête**. Les colonnes sont reconnues **par leur nom**, celui des exports belges francophones :

`Date` · `Description` · `Montant` · `Nom contrepartie` · `Numéro de compte contrepartie` · `Communication structurée` · `Communication libre`

Accents, majuscules et espaces sont ignorés. Les deux exports de CBC — l'export **rapide** (`export_BE…_date.csv`) et l'export **par période** — sont acceptés tels quels : encodage, séparateur (`;` ou `,`) et fins de ligne sont détectés.

**Un fichier incomplet est refusé en entier**, en rouge, sans rien importer. Il lui faut au minimum `Date`, `Montant`, `Numéro de compte contrepartie` (même vide sur certaines lignes) et l'une des deux communications. Le message nomme la colonne manquante **et** liste les colonnes qu'il a lues : c'est ce qu'il faut transmettre si la banque change encore son export.

**Le relevé d'un autre compte est refusé**, lui aussi : le numéro de compte du fichier doit être celui du club, renseigné dans **Paramètres du club → Informations**. Si ce numéro n'est pas renseigné, l'import passe, avec un avertissement qui vous invite à le compléter.

## Réimporter sans risque

**Chaque ligne reçoit une empreinte.** Si vous réimportez un extrait qui recouvre une période déjà chargée, les lignes connues sont **reconnues et écartées** — pas de doublons.

C'est fait exprès : exportez large, importez souvent, sans faire de calculs de dates. Le rapport vous dira exactement ce qui s'est passé :

> *« 23 nouvelle(s) transaction(s) importée(s). 15 doublon(s) ignoré(s). »*

## Les doublons probables

Les deux exports de CBC n'écrivent pas le libellé d'un virement de la même façon : un même virement, importé une fois par chaque export, n'aurait pas la même empreinte.

L'import le repère : une ligne qui a **la même date, le même montant et le même compte de contrepartie** qu'une transaction déjà en base est **mise de côté**, pas importée. Le rapport les compte à part (*« 2 doublon(s) probable(s) à vérifier »*), et l'**historique d'import** les montre en orange, à côté de la transaction qu'elles recoupent.

À vous de trancher, ligne par ligne :

- **C'est un doublon** : la ligne est écartée ;
- **Importer quand même** : ce sont deux vrais virements — deux commandes du bar le même jour par le même membre, par exemple.

Deux lignes semblables **dans le même fichier** ne sont jamais mises de côté : un extrait ne se répète pas.

## Quand il y a des erreurs

Le message passe en **avertissement** et annonce le nombre de lignes rejetées. **L'historique d'import garde chacune d'elles** : le numéro de ligne, son contenu, et le motif du rejet.

Allez le lire. Une ligne rejetée est une opération bancaire absente de votre comptabilité — c'est le genre de trou qu'on ne retrouve pas six mois plus tard.

Un extrait valide mais **sans aucun mouvement** est signalé en avertissement : vérifiez la période exportée.

## Retrouver une opération

La recherche couvre le **nom de la contrepartie**, les **communications** (structurée et libre) et la **description**. Vous pouvez filtrer par période, par sens (entrées / sorties) et par état de rapprochement.

Le compteur **« à rapprocher »** et le filtre **« non rapprochées »** comptent la même chose : les **entrées d'argent** encore sans paiement associé — les seules qui peuvent correspondre à une cotisation. Les sorties (remboursements, frais) ne sont jamais concernées, ni par l'un ni par l'autre.

## Supprimer des transactions

Possible, en lot. **Et une transaction déjà rapprochée peut être supprimée** — l'application vous prévient qu'il y en a dans votre sélection, mais elle ne vous en empêche pas.

Si vous le faites, **le paiement correspondant redevient non rapproché**, sans que personne ne vous le signale. Réservez la suppression aux lignes importées par erreur, et vérifiez toujours l'avertissement de sélection avant de confirmer.
