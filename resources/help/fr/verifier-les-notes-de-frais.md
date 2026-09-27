---
title: Vérifier les notes de frais
summary: Pour les vérificateurs aux comptes et le comité — lire les statuts, retrouver qui a décidé quoi, recouper avec la banque et exporter un exercice.
audience: auditor
order: 25
---

Cette page s'adresse à ceux qui **lisent** les notes de frais sans les traiter : les **vérificateurs aux comptes** élus par l'assemblée générale et les membres du **comité**. Tout se passe dans **Trésorerie → Notes de frais**, en lecture seule.

## L'accès des vérificateurs

Les vérificateurs sont, par principe, **extérieurs au comité** : on ne vérifie pas ses propres comptes. Le comité leur attribue la délégation **Vérification des comptes** avant la révision et la leur retire après l'AG. Elle ouvre **toute la trésorerie en lecture** (paiements, transactions bancaires, amendes, caisse, notes de frais) ainsi que les **affiliations**, pour recouper une cotisation. Elle ne permet de **rien modifier**, et les numéros de compte (IBAN) restent masqués : seuls leurs derniers chiffres s'affichent.

## Lire une note

Chaque note porte l'un de ces statuts :

| Statut | Ce qu'il veut dire |
|---|---|
| **En cours** | déclarée, pas encore traitée |
| **Acceptée** | un remboursement a été ouvert, l'argent n'est pas encore sorti |
| **Payée** | le virement a été retrouvé sur l'extrait bancaire du club |
| **Rejetée** | refusée, avec un motif envoyé au membre |
| **Retirée** | retirée par le membre lui-même |

« Payée » n'est jamais une case cochée à la main : ce statut vient du **rapprochement** entre le remboursement et la ligne de débit de l'extrait.

En cliquant sur une note, vous voyez :

- ce que le membre a **déclaré** (nature, description, date, montant) et ses **justificatifs** ;
- le **montant accepté**, s'il diffère, avec le **motif** envoyé au membre ;
- **qui a décidé et quand** (rubrique *Décidée par*) ;
- la **référence du remboursement** et la **date de paiement**.

Deux règles se vérifient d'un coup d'œil :

- **Personne ne traite sa propre note** : *Décidée par* n'est jamais l'auteur de la note. L'application l'interdit.
- **Jamais plus que déclaré** : le montant accepté est toujours inférieur ou égal au montant déclaré, et un montant inférieur a toujours un motif.

## Recouper avec la banque

La **référence du remboursement** est la communication du virement. Pour une note payée, retrouvez-la dans **Trésorerie → Paiements → Remboursé** ou dans **Transactions bancaires** : la date de paiement affichée sur la note est celle de la ligne de débit rapprochée.

## Un exercice à la fois

L'exercice du club va du **1er janvier au 31 décembre**. Une note compte dans l'exercice où **l'argent est sorti**, pas dans celui du ticket : un achat du 28 décembre remboursé le 10 janvier appartient à l'année suivante.

Dans le tiroir **Filtres**, choisissez l'**Exercice**. Seules les notes payées cette année-là restent affichées. Les cartes en haut de page donnent le total payé de l'année en cours.

## Exporter

**Exporter** reprend exactement ce que l'écran affiche (onglet, recherche et filtres) :

- **PDF imprimable** : un récapitulatif avec les totaux par nature, puis une page par note avec ses justificatifs imprimés.
- **Archive ZIP** : le même récapitulatif, un tableur **CSV** (séparateur point-virgule, pour Excel) et un dossier par note contenant les **justificatifs originaux**, intacts.

L'export se prépare en arrière-plan : la **cloche** sonne quand il est prêt. Le lien n'est valable que **7 jours** et seulement pour vous ; ensuite, relancez l'export.

Si un justificatif ne peut pas être imprimé dans le PDF (image abîmée, PDF protégé), la page le signale : l'original est dans le ZIP.
