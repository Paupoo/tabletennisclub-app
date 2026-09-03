---
title: Créer et gérer un pack d'entraînement
summary: L'assistant en trois étapes, les séances générées automatiquement, ouvrir ou fermer les inscriptions, ajouter un membre à la main, lire les présences et gérer les niveaux.
audience: committee
order: 15
---

Un **pack** est l'entraînement tel qu'on le vend : « Jeunes du mardi », un niveau, un coach, un prix. Les **séances** sont les dates concrètes que l'application en déduit. Vous ne créez jamais une séance à la main — vous décrivez le pack, l'application génère le calendrier.

Tout se passe dans **Entraînements**, réservé au comité.

## Créer un pack

Bouton **Créer**, puis trois étapes. L'application vous empêche de passer à la suivante tant que la précédente est incomplète.

### 1. Le pack

Saison, nom, niveau, type, salle — et **le coach**, obligatoire sauf pour un entraînement de type **Libre**. Un pack libre n'a pas d'encadrant, c'est ce qui le définit.

La liste des **niveaux** se gère depuis le bouton **Niveaux** en haut de l'écran (voir plus bas). Si celui qu'il vous faut n'existe pas, créez-le, ne détournez pas un voisin.

### 2. Le planning

Choisissez **un jour qui se répète chaque semaine**, ou **plusieurs jours** si le pack tourne deux fois par semaine.

Par défaut le pack couvre toute la saison. Les champs **De / À** permettent de le restreindre — un stage de six semaines, par exemple.

L'application affiche alors **la liste des dates générées**. Relisez-la : c'est le moment de **décocher les dates à exclure** (congés scolaires, week-end de tournoi). Une date décochée ne produira pas de séance.

**La capacité** est le point qu'on oublie. Laissez le champ vide et le plafond devient **la capacité d'entraînement de la salle** — le champ vous l'affiche en gris pour que vous sachiez sur quel nombre vous partez. Remplissez-le pour fixer un plafond propre au pack, plus bas que la salle.

La case **sans plafond de participants** n'apparaît que sur les packs de type **Libre**. Sur un entraînement dirigé, elle supprimerait à la fois le plafond et la liste d'attente : le coach découvrirait la surcharge le soir même. L'application la refuse donc ailleurs, même si vous forcez.

Juste en dessous, **ouvert aux inscriptions des membres** est cochée par défaut. Décochée, les membres ne peuvent plus s'inscrire seuls — mais le pack reste affiché et le comité garde la main. C'est le sujet de la section suivante ; ne la confondez pas avec la case du plafond, qui parle d'un tout autre problème.

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

## Ouvrir et fermer les inscriptions

Le menu **⋮** du pack propose **Fermer les inscriptions** / **Rouvrir les inscriptions**. La même bascule existe dans l'assistant, à l'étape 2.

Ce que la fermeture fait, et **surtout ce qu'elle ne fait pas** :

- elle **empêche les membres de s'inscrire eux-mêmes**, et de se mettre en liste d'attente — attendre une place sur un pack fermé serait attendre un tour qui ne vient pas ;
- elle **n'empêche pas de partir**. On ne retient personne ;
- elle **n'annule pas une proposition déjà envoyée** : qui a reçu une place a 48 heures pour la prendre, fermeture ou pas ;
- elle **n'interrompt pas la liste d'attente en cours**. Les gens qui attendaient avant la fermeture gardent leur rang et continuent d'être appelés. Fermer empêche d'entrer, pas d'avancer ;
- **le comité, lui, peut toujours ajouter quelqu'un** (section suivante). C'est tout l'intérêt de la paire.

Côté membre, le pack **reste affiché**, avec *Inscriptions closes* à la place du bouton. Le faire disparaître ferait croire à une suppression.

## Ajouter un membre à la main

Ouvrez **Séances** sur le pack, puis **Ajouter un membre**.

Contrairement à une demande de membre, **la place arrive validée** : rien à approuver ensuite, ce serait valider votre propre décision. Le membre est prévenu par e-mail que le club l'a inscrit, avec le montant désormais dû.

Deux points qui comptent :

**La date d'entrée est modifiable.** Laissez le champ vide et la facturation part d'aujourd'hui. Mettez une date antérieure pour régulariser quelqu'un qui **venait déjà** — c'est le cas typique après une lecture de la grille de présences : le membre est là depuis septembre sans être inscrit, vous l'ajoutez en janvier, et sans cette date vous lui offririez quatre mois. Une date antérieure au début du pack vaut « tout le pack », au plein tarif.

**Le plafond peut être franchi**, avec un avertissement. C'est délibéré : vous l'interdire vous pousserait à gonfler `max_participants`, ce qui casserait durablement la liste d'attente du pack.

Il faut une **affiliation en cours** pour la saison du pack. Sans elle, il n'y a pas de facture à laquelle rattacher l'entraînement : affiliez d'abord.

## Lire les présences

Sous la liste des séances d'un pack, une **grille membres × séances** rassemble tout ce que les coachs ont pointé.

Elle se lit dans les deux sens, et c'est pour cela qu'il n'y a qu'un écran :

- **une colonne creuse** = cette séance-là n'a réuni presque personne. À rapprocher de la date : congé, examens, horaire mal choisi.
- **une ligne creuse** = ce membre paie et ne vient pas. À rapprocher de son affiliation.

Ce que disent les cases :

| | |
|---|---|
| ✓ vert · − ambre · ✗ rouge | présent · excusé · absent |
| carré gris | **séance non pointée** — le coach n'a pas validé. Ce n'est **pas** une absence |
| colonne barrée | séance annulée. Affichée pour ne pas laisser croire à un trou dans le calendrier, mais elle ne compte nulle part |

Les **taux en marge** — en bas par séance, à droite par membre — valent **—** et non 0 % tant que rien n'a été pointé. La nuance est voulue : « on n'en sait rien » n'est pas « personne ne vient ».

Sous la grille, les **venus sans être inscrits** : ceux que les coachs ont vus à la séance sans qu'ils soient au pack. Ils sont tenus à l'écart du taux de la séance — ils n'y étaient pas attendus, les compter gonflerait la participation. C'est de là que part une régularisation par *Ajouter un membre*.

La grille montre les **12 dernières séances** ; **Voir toute la saison** déplie le reste.

## Gérer les niveaux

Bouton **Niveaux**, en haut de la liste des packs. Vous y créez, renommez et recolorez les niveaux, et vous décidez de leur ordre d'affichage.

- **Retirer** un niveau le sort des listes déroulantes **sans toucher aux packs qui le portent**. Une saison passée garde ses mots.
- **Supprimer** n'est possible que si **aucun pack et aucune séance** ne s'en sert. L'application refuse sinon, et vous propose de le retirer à la place.

La **couleur** est celle de la pastille que les membres voient en face du pack dans leur espace. Choisissez-la pour distinguer, pas pour décorer.

## Retirer un pack, ou l'arrêter

Deux boutons distincts, et **ils n'ont pas du tout les mêmes conséquences**.

Les deux sont dans le menu **⋮** en bas à droite de la carte du pack, sous la bascule des inscriptions.

**Retirer de l'offre** sort le pack de la vitrine : il disparaît de **l'horaire du site public** et de l'écran d'inscription des membres. **Les séances continuent**, les inscrits gardent leur place et **ne sont pas prévenus** — rien n'a changé pour eux.

> Ne confondez pas avec **Fermer les inscriptions**. Retirer de l'offre efface le pack du site ; fermer les inscriptions le laisse affiché, complet, avec la porte close. Pour un pack qui affiche complet et que vous voulez garder visible, c'est **fermer les inscriptions** qu'il vous faut, pas le retirer.

C'est **réversible**. Un pack retiré disparaît de la liste par défaut : ouvrez **Filtres**, cochez **Afficher les packs retirés**, et il réapparaît avec l'étiquette *Retiré*. Son menu **⋮** propose alors **Remettre dans l'offre**.

**Arrêter le pack** (icône croix rouge) est autre chose. L'application :

- annule **toutes les séances à venir** ;
- prévient **les inscrits** que l'entraînement n'aura plus lieu ;
- prévient **les personnes en liste d'attente** qu'aucune place ne se libérera ;
- envoie **les remboursements au trésorier**, calculés membre par membre.

> ⚠️ **C'est irréversible.** Les e-mails sont partis, les remboursements sont entrés dans le circuit de la trésorerie. La fenêtre de confirmation vous annonce combien de séances et combien de membres sont concernés — lisez ces chiffres avant de cliquer.

Elle ne vous annonce **pas** de montant total, et c'est délibéré : ce que chaque membre récupère dépend de la remise multi-packs qu'il perd au passage. Un total affiché avant coup serait une estimation que les remboursements réels contrediraient. Le montant exact vous est donné une fois l'opération faite.

Voir aussi [Traiter les demandes d'entraînement](traiter-les-demandes-d-entrainement) pour l'approbation des demandes et les remboursements individuels, et [Animer mes séances](animer-mes-seances) pour ce que les coachs voient de leur côté.
