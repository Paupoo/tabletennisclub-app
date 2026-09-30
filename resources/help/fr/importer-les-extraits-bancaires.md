---
title: Importer et gérer les extraits bancaires
summary: Alimenter la liste des transactions depuis votre banque, sans doublons et sans perte.
audience: treasurer, committee
order: 14
---

C'est la matière première du rapprochement : sans transactions, rien à apparier. **Trésorerie → Transactions.**

## Importer

Exportez l'extrait depuis votre banque, puis déposez le fichier. Formats acceptés : `.csv`, `.ods`, `.xlsx`, `.xls`, `.txt`.

Le fichier doit avoir une **ligne d'en-tête**. Les colonnes sont reconnues **par leur nom**, celui des exports belges francophones :

`Date` · `Description` · `Montant` · `Nom contrepartie` · `Numéro de compte contrepartie` · `Communication structurée` · `Communication libre`

Quand elles sont là, l'import lit aussi le **numéro de compte** du club, le **solde** après chaque opération et le **numéro de l'extrait**. Les deux exports de CBC les contiennent.

Accents, majuscules et espaces sont ignorés. Les deux exports de CBC — l'export **rapide** (`export_BE…_date.csv`) et l'export **par période** — sont acceptés tels quels : encodage, séparateur (`;` ou `,`) et fins de ligne sont détectés.

**Un fichier incomplet est refusé en entier**, en rouge, sans rien importer. Il lui faut au minimum `Date`, `Montant`, `Numéro de compte contrepartie` (même vide sur certaines lignes) et l'une des deux communications. Le message nomme la colonne manquante **et** liste les colonnes qu'il a lues : c'est ce qu'il faut transmettre si la banque change encore son export.

## Les comptes du club

Le club peut avoir **plusieurs comptes** : le compte courant, un compte d'épargne… Chaque ligne importée est rangée sur le compte qui figure dans le fichier.

Le compte renseigné dans **Paramètres du club → Informations** est reconnu d'office comme **compte courant**. Pour tout autre numéro, **l'application vous pose la question avant d'importer quoi que ce soit** : « Ce relevé est celui du compte BE…, que le club n'a pas encore enregistré. »

- S'il s'agit bien d'un compte du club, donnez-lui un **nom**, choisissez **courant** ou **épargne**, puis **Enregistrer et importer**.
- Sinon — l'extrait de votre compte personnel, rangé dans le même dossier de téléchargements — **annulez** : rien n'a été importé.

Le filtre **Compte bancaire** permet ensuite de n'afficher qu'un compte à la fois. Le rapprochement automatique avec les paiements du site ne s'applique qu'aux **comptes courants** : personne ne paie sa cotisation sur le compte d'épargne.

## Les virements entre comptes du club

Un virement du compte courant vers le compte d'épargne n'est ni une dépense ni une recette : c'est le même argent qui change de poche. L'import le reconnaît tout seul — le compte de contrepartie est un compte du club — et marque la ligne **Mouvement interne**. Elle est clôturée d'office, sans pièce à fournir, et n'entre dans aucun total de recettes ou de dépenses.

Pour un versement entre la **caisse** et la banque, c'est la trésorerie qui relie les deux mouvements, depuis l'écran **Caisse**. Voir [Classer les pièces justificatives](classer-les-pieces-justificatives).

## Le solde des comptes

Le solde imprimé par la banque est gardé ligne par ligne. C'est lui qui donne la **trésorerie** du [rapport financier](lire-le-rapport-financier) : le solde d'un compte à une date est celui de la dernière ligne importée ce jour-là ou avant. Pensez donc à importer aussi le compte d'épargne, même s'il bouge peu.

## Réimporter sans risque

**Chaque ligne reçoit une empreinte.** Si vous réimportez un extrait qui recouvre une période déjà chargée, les lignes connues sont **reconnues et écartées** — pas de doublons. Si une ligne connue avait été importée sans son solde ou son numéro d'extrait, la réimportation les **complète**, sans rien créer.

C'est fait exprès : exportez large, importez souvent, sans faire de calculs de dates. Le rapport vous dira exactement ce qui s'est passé :

> *« 23 nouvelle(s) transaction(s) importée(s). 15 doublon(s) ignoré(s). »*

## Les doublons probables

Les deux exports de CBC n'écrivent pas le libellé d'un virement de la même façon : un même virement, importé une fois par chaque export, n'aurait pas la même empreinte.

L'import le repère : une ligne qui a **la même date, le même montant et le même compte de contrepartie** qu'une transaction déjà en base est **mise de côté**, pas importée. Le rapport les compte à part (*« 2 doublon(s) probable(s) à vérifier »*), et l'**historique d'import** les montre en orange, à côté de la transaction qu'elles recoupent.

À vous de trancher, ligne par ligne :

- **C'est un doublon** : la ligne est écartée ;
- **Importer quand même** : ce sont deux vrais virements — deux commandes du bar le même jour par le même membre, par exemple.

Deux lignes semblables **dans le même fichier** ne sont jamais mises de côté : un extrait ne se répète pas.

## Quand il y a des erreurs

Le message passe en **avertissement** et annonce le nombre de lignes rejetées. **L'historique d'import garde chacune d'elles** : le numéro de ligne, son contenu, et le motif du rejet.

Allez le lire. Une ligne rejetée est une opération bancaire absente de votre comptabilité — c'est le genre de trou qu'on ne retrouve pas six mois plus tard.

Un extrait valide mais **sans aucun mouvement** est signalé en avertissement : vérifiez la période exportée.

## Retrouver une opération

La recherche couvre le **nom de la contrepartie**, les **communications** (structurée et libre) et la **description**. Vous pouvez filtrer par compte, par période, par sens (entrées / sorties) et par état.

Une ligne est **soldée** de l'une de ces façons :

- elle est **affectée** à des paiements du site (une cotisation, un remboursement) ;
- elle est **justifiée par une pièce** (une facture, un subside) — voir [Classer les pièces justificatives](classer-les-pieces-justificatives) ;
- son **solde a été abandonné** (un membre qui a arrondi au-dessus, par exemple) ;
- c'est un **mouvement interne**.

Tout le reste est **À traiter** : c'est le filtre à ouvrir pour savoir ce qu'il reste à faire, en entrée comme en sortie. Le [rapport financier](lire-le-rapport-financier) y mène directement, pour l'exercice affiché.

## Supprimer des transactions

Possible, en lot. **Et une transaction déjà rapprochée peut être supprimée** — l'application vous prévient qu'il y en a dans votre sélection, mais elle ne vous en empêche pas.

Si vous le faites, **le paiement correspondant redevient non rapproché**, sans que personne ne vous le signale. Réservez la suppression aux lignes importées par erreur, et vérifiez toujours l'avertissement de sélection avant de confirmer.
