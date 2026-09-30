---
title: Classer les pièces justificatives
summary: La facture de la salle, les balles, un subside, les frais de banque — tout l'argent que le site ne voit pas se prouve par une pièce, classée et liée au mouvement qui l'a payé.
audience: supporting_documents
order: 27
---

Le site compte tout seul l'argent qui passe par lui : cotisations, entraînements, inscriptions aux tournois, bar. Pour tout le reste — la location de la salle, l'affiliation à l'AFTT, les balles, l'assurance, un subside de la commune, un sponsor, les frais de banque — le club doit pouvoir **montrer un papier**. Ce papier, c'est la **pièce justificative** : une facture, un ticket, un courrier, une capture d'écran de l'extrait.

Tout se passe dans **Trésorerie → Pièces justificatives**.

**Qui fait quoi :**

- la délégation **Trésorerie** classe les pièces, les modifie et les lie aux mouvements ;
- la délégation **Caisse** classe les tickets payés avec la caisse, depuis l'écran **Caisse** ;
- le **comité** et les **vérificateurs aux comptes** consultent tout, sans rien modifier.

## Ce qu'une pièce contient

- **La date** imprimée sur la pièce (celle de la facture ou du ticket).
- **Le montant**, toujours positif, tel qu'il figure sur la pièce. Pas de TVA à séparer.
- **La catégorie**. C'est elle qui dit si la pièce est une **dépense** ou une **recette** : il n'y a pas d'autre case à cocher.
- **Le tiers** : « AFTT », « Colruyt », « Commune d'Ottignies ».
- **Un libellé** court : « 6 boîtes de balles pour l'école de jeunes ».
- **De 1 à 5 fichiers**, en PDF, JPG, PNG ou WebP, 10 Mo maximum chacun. Une pièce sans fichier n'existe pas.

L'application lui donne une **référence**, par exemple **P-2026-0042** : c'est elle que vous retrouverez sur le journal et dans l'export.

### Les catégories

**Dépenses** — les mêmes que celles des notes de frais : Fédération & compétitions, Salle, Matériel sportif, Entraînement & formation, Événements & tournois, Bar, Fonctionnement, Déplacements, Autres.

**Recettes** : Événements & tournois, Bar, Subsides, Sponsoring & dons, Autres recettes.

**Cotisations** et **Entraînements** ne sont pas proposées : cet argent-là, le site le compte déjà. Une pièce classée dessus le compterait deux fois.

## À régler ou réglée

Une pièce qui n'est liée à **aucun mouvement** est **À régler** :

- une **dépense** à régler est une **dette** du club — la facture est arrivée, elle n'est pas encore payée ;
- une **recette** à régler est une **créance** — le subside est promis, il n'est pas encore versé.

Dès qu'elle est liée à un mouvement de banque ou de caisse, elle est **Réglée**. Les cartes en haut de l'écran donnent le total des dettes et des créances ouvertes.

## Justifier une ligne de l'extrait

C'est le cas le plus courant. Dans **Trésorerie → Transactions bancaires**, sur la ligne à justifier, cliquez sur **Justifier**. Le panneau propose d'abord des **suggestions** : les pièces **à régler**, dans le même sens, **du même montant au centime près**, datées à **45 jours** au plus de la ligne. Celles dont le tiers apparaît sur la ligne passent en premier.

- Si la bonne pièce est là, cliquez sur **Lier**.
- Sinon, cherchez-la plus bas.
- Si elle n'est pas encore classée, **Ou classer une nouvelle pièce** : la date, le montant et le tiers sont repris de la ligne. Choisissez la catégorie, écrivez le libellé, joignez le fichier, puis **Classer et lier**.

**Rien ne se lie jamais tout seul** : c'est toujours vous qui cliquez.

La ligne devient **Justifiée** et sort des lignes **À traiter**. Si vous la **dissociez** de sa dernière pièce, elle y revient.

Vous pouvez aussi partir de la pièce : ouvrez-la dans **Pièces justificatives**, puis **Lier des transactions**.

### Plusieurs pièces, plusieurs lignes

Une ligne peut payer plusieurs factures, et une facture peut être payée en plusieurs fois. Liez simplement tout ce qui va ensemble. Si les montants ne se recoupent pas, la pièce affiche un **avertissement** (« Les mouvements liés totalisent… ») : vérifiez qu'il ne manque rien. Ce n'est jamais un blocage.

Si une ligne est liée à des pièces de catégories différentes (des balles et un filet sur la même facture de magasin, par exemple), son montant est réparti entre les catégories **au prorata** des pièces.

## Payer en espèces

Une facture réglée en liquide se lie à un **mouvement de caisse**. Deux façons :

- depuis la pièce : choisissez la **caisse**, puis **Enregistrer un paiement en espèces de … €**. Le mouvement de caisse est créé pour vous, du bon montant et dans le bon sens ;
- depuis **Trésorerie → Caisse** : sur un mouvement, **Justifier**, puis liez une pièce existante ou classez-en une nouvelle.

C'est ainsi que la personne qui tient la caisse range elle-même les tickets de ce qu'elle a payé.

## Ce qui ne reçoit pas de pièce

- Une **transaction rapprochée d'un paiement du site** (une cotisation, un remboursement de note de frais) : elle est déjà justifiée par le site. On ne mélange jamais, sur une même ligne, l'argent du site et l'argent hors site.
- Un **mouvement de caisse** qui est un paiement du site (une commande du bar, par exemple).
- Un **mouvement interne** : un virement entre deux comptes du club, ou un versement entre la caisse et la banque. Ce n'est ni une recette ni une dépense (voir plus bas).

À l'inverse, **les frais de banque et les intérêts ont besoin d'une pièce**, comme le reste. Une capture d'écran de l'extrait qui les montre suffit.

## La caisse et la banque

Quand la caisse est versée à la banque, ou qu'on retire un fonds de caisse, le même argent apparaît deux fois : une sortie d'un côté, une entrée de l'autre. Pour que le club ne le compte pas comme une recette et une dépense, la trésorerie relie les deux : dans **Trésorerie → Caisse**, sur le mouvement, **C'est un versement de/vers la banque**, puis choisissez la ligne correspondante de l'extrait. Les deux mouvements deviennent **internes**.

Les virements entre deux comptes du club (vers le compte d'épargne, par exemple) n'ont rien à faire : l'import les reconnaît tout seul. Voir [Importer et gérer les extraits bancaires](importer-les-extraits-bancaires).

## Modifier, supprimer

Une pièce peut **toujours être modifiée** : chaque changement est gardé dans l'historique. Elle ne peut être **supprimée** que si elle ne justifie plus aucun mouvement — dissociez-la d'abord.

Pour voir ce que tout cela donne à l'échelle de l'année, voyez [Lire le rapport financier](lire-le-rapport-financier).
