---
title: Créer et gérer un pack d'entraînement
summary: L'assistant en trois étapes, les séances générées automatiquement, et quoi faire quand l'horaire change en cours de saison.
audience: committee
order: 15
---

Un **pack** est l'entraînement tel qu'on le vend : « Jeunes du mardi », un niveau, un coach, un prix. Les **séances** sont les dates concrètes que l'application en déduit. Vous ne créez jamais une séance à la main — vous décrivez le pack, l'application génère le calendrier.

Tout se passe dans **Entraînements**, réservé au comité.

## Créer un pack

Bouton **Créer**, puis trois étapes. L'application vous empêche de passer à la suivante tant que la précédente est incomplète.

### 1. Le pack

Saison, nom, niveau, type, salle — et **le coach**, obligatoire sauf pour un entraînement de type **Libre**. Un pack libre n'a pas d'encadrant, c'est ce qui le définit.

### 2. Le planning

Choisissez **un jour qui se répète chaque semaine**, ou **plusieurs jours** si le pack tourne deux fois par semaine.

Par défaut le pack couvre toute la saison. Les champs **De / À** permettent de le restreindre — un stage de six semaines, par exemple.

L'application affiche alors **la liste des dates générées**. Relisez-la : c'est le moment de **décocher les dates à exclure** (congés scolaires, week-end de tournoi). Une date décochée ne produira pas de séance.

**La capacité** est le point qu'on oublie. Laissez le champ vide et le plafond devient **la capacité d'entraînement de la salle** — le champ vous l'affiche en gris pour que vous sachiez sur quel nombre vous partez. Remplissez-le pour fixer un plafond propre au pack, plus bas que la salle.

La case **inscriptions illimitées** n'apparaît que sur les packs de type **Libre**. Sur un entraînement dirigé, elle supprimerait à la fois le plafond et la liste d'attente : le coach découvrirait la surcharge le soir même. L'application la refuse donc ailleurs, même si vous forcez.

### 3. Le prix

Le prix par membre, en euros, décimales comprises. Et la case **remise autorisée** : elle décide si ce pack entre dans les réductions de 10 € (multi-entraînements, multi-membres d'une famille). Décochez-la pour un pack à prix ferme.

À l'enregistrement, l'application génère les séances et vous annonce combien.

## Modifier un pack

C'est là qu'il faut être attentif : **tous les changements ne se valent pas**.

**Changer le nom, la description, le prix, la remise, le coach** → enregistré directement. Le changement de coach est répercuté sur toutes les séances. Personne n'est prévenu, et c'est voulu : rien n'a bougé pour les membres.

**Changer le jour, l'heure, la durée, la salle, les dates ou les exclusions** → l'application vous arrête et vous demande confirmation, en annonçant **combien de séances vont être supprimées et recréées**.

Ce que la reconstruction touche, et ce qu'elle épargne :

- **Les séances à venir sont supprimées et régénérées** sur le nouvel horaire.
- **Les séances passées ne bougent pas.** Elles portent les présences encodées par le coach ; les effacer fausserait le taux de présence de chaque membre.
- **Les séances déjà annulées ne bougent pas non plus.** Une annulation a été annoncée par e-mail avec son motif : la ressusciter contredirait ce que les membres ont lu.

La fenêtre propose aussi de **prévenir par e-mail les membres inscrits**. Elle est **cochée par défaut** — oublier de prévenir coûte plus cher qu'un e-mail de trop. Décochez-la pour la correction faite trente secondes après la faute de frappe.

## Annuler une séance isolée

Depuis **Séances** sur la ligne du pack, puis l'icône d'annulation. Choisissez le bon type, les membres reçoivent l'un ou l'autre message :

- **Libre** — pas de coach, mais la salle reste ouverte pour jouer.
- **Fermée** — la salle est inaccessible, inutile de venir.

Le motif que vous saisissez part dans l'e-mail. Écrivez-le pour un membre, pas pour vous.

## Retirer un pack, ou l'arrêter

Deux boutons distincts, et **ils n'ont pas du tout les mêmes conséquences**.

Les deux se trouvent **en bas de la carte du pack**, à droite de la ligne de boutons qui commence par *Séances* — après l'icône crayon et l'icône 🎯 du site public.

**Retirer de l'offre** (icône œil barré) ferme les inscriptions. **Les séances continuent**, les inscrits gardent leur place et **ne sont pas prévenus** — rien n'a changé pour eux. C'est le geste pour un pack qui ne se remplit pas, ou au contraire déjà complet.

C'est **réversible**. Un pack retiré disparaît de la liste par défaut : ouvrez **Filtres**, cochez **Afficher les packs retirés**, et il réapparaît avec l'étiquette *Retiré*. Le bouton vert en forme de flèche le remet dans l'offre.

**Arrêter le pack** (icône croix rouge) est autre chose. L'application :

- annule **toutes les séances à venir** ;
- prévient **les inscrits** que l'entraînement n'aura plus lieu ;
- prévient **les personnes en liste d'attente** qu'aucune place ne se libérera ;
- envoie **les remboursements au trésorier**, calculés membre par membre.

> ⚠️ **C'est irréversible.** Les e-mails sont partis, les remboursements sont entrés dans le circuit de la trésorerie. La fenêtre de confirmation vous annonce combien de séances et combien de membres sont concernés — lisez ces chiffres avant de cliquer.

Elle ne vous annonce **pas** de montant total, et c'est délibéré : ce que chaque membre récupère dépend de la remise multi-packs qu'il perd au passage. Un total affiché avant coup serait une estimation que les remboursements réels contrediraient. Le montant exact vous est donné une fois l'opération faite.

Voir aussi [Traiter les demandes d'entraînement](traiter-les-demandes-d-entrainement) pour l'approbation des demandes et les remboursements individuels.
