# Ce qui change dans l'application du club — saison 2026-2027

**Période :** 26 mai → 30 juillet 2026

Voici un résumé de tout ce qui a été ajouté ou corrigé dans l'application depuis la fin de la saison dernière. C'est la mise à jour la plus importante depuis le lancement : rôles du comité, espace membre, gestion des entraînements, réunions, trésorerie et import des affiliations fédérales.

---

## 🔑 Rôles et délégations du comité

**Le changement le plus structurant de la saison.** Jusqu'ici, les accès reposaient sur quelques cases à cocher (« est admin », « est membre du comité »). Désormais, chacun reçoit un **rôle** (président, vice-président, secrétaire, trésorier, capitaine, sélectionneur…) et une ou plusieurs **délégations** (membres, trésorerie, interclubs, entraînements, bar, site web, réunions).

Concrètement :

- Ce que vous voyez dans le menu, ce à quoi vous accédez et ce que vous pouvez modifier découlent de vos délégations — plus de votre titre.
- Un écran permet d'attribuer et de visualiser les délégations de chaque membre du comité.
- Les capitaines ne peuvent sélectionner que dans **leurs propres équipes**.
- Les pages du back-office sont verrouillées à la racine : une adresse tapée à la main ne donne plus accès à un écran interdit.
- Un domaine entier (le bar, par exemple) peut être désactivé selon l'environnement.

## 👤 « Mon espace » — l'espace membre

Un véritable espace personnel pour chaque membre, séparé du back-office :

- **Accueil personnalisé** avec bandeau d'identité et onglets persistants.
- **Mes paiements** : un hub qui regroupe cotisation, entraînements, tournois et amendes.
- **Mon affiliation** présentée par étapes, avec le type de licence choisi visible sur une inscription soumise.
- **Annuaire des membres**, avec possibilité pour chacun de choisir ce qu'il partage (téléphone, e-mail, adresse) — rien n'est visible par défaut sans accord.
- **Mes disponibilités** et vue d'ensemble pour l'équipe.
- **Flux ICS** : vos matchs et entraînements s'ajoutent automatiquement à votre agenda (Google Agenda, Apple, Outlook).
- **Préférences de notification** : vous choisissez ce que vous recevez.
- **Règlement AFTTB** consultable dans l'application.
- Profil entièrement modifiable, avec photo (recadrage depuis le téléphone) et zone de suppression de compte déplacée dans les réglages.

## 📨 Arrivée des nouveaux membres

- **Assistant d'accueil** : à sa première connexion, le nouveau membre complète son profil guidé étape par étape. Le back-office reste fermé tant que le profil est incomplet.
- **Invitations valables 7 jours** (au lieu de 48 h), avec une page dédiée en cas de lien expiré et un renvoi en autonomie — plus besoin d'écrire au secrétaire.
- **Représentant légal obligatoire pour les mineurs**, avec un formulaire lisible et vérifié.
- **Groupes familiaux** : le rattachement d'un membre à une famille se fait désormais côté administration, et il est possible de rejoindre une famille existante au lieu d'être bloqué.
- **Identité de connexion séparée de l'adresse de contact** : un enfant peut avoir un compte sans adresse e-mail propre, le contact passant par le tuteur.
- Politique de mot de passe renforcée, e-mail d'invitation traduit en français et en néerlandais.

## 📥 Import des affiliations fédérales

Nouveau circuit complet pour la reprise de la liste AFTT en début de saison :

1. On dépose le listing de la fédération.
2. L'application **rapproche automatiquement** chaque affilié du fichier des membres du club.
3. On **relit et valide** les correspondances **avant** que quoi que ce soit ne touche au fichier des membres.
4. L'import est appliqué, puis les nouveaux membres peuvent être **invités en masse**.
5. Un écran signale les membres à qui aucun accès ne peut être remis (adresse manquante, par exemple).

> ⚠️ Cette partie est encore en cours de finalisation au moment de la rédaction.

## 🏓 Entraînements et planning

- **Tableau de planification de la saison** : répartition des groupes sur les créneaux, filtres par pool, optimiseur de mise en page, utilisable sur mobile.
- Un pack d'entraînement **déclare toujours sa période** (début et fin).
- **Facturation au prorata** : un pack est facturé pour les mois réellement dispensés.
- **Plafond d'inscrits** défini directement dans l'assistant de création du pack.
- **Liste d'attente** : le membre est informé de la date d'expiration de sa place proposée.
- Distinction claire entre **se retirer** d'un pack et **l'arrêter**.
- Les séances sont reconstruites automatiquement si le pack change de créneau.
- Remboursements corrigés (montant réellement trop-perçu, centimes préservés).
- L'annulation d'une affiliation annule les packs d'entraînement associés.

## 🏆 Interclubs et sélections

- **Listes de force par catégorie** (générale, dames, vétérans).
- Les membres non compétiteurs n'apparaissent plus dans les sélecteurs de joueurs.
- **Recherche de remplaçant** : quand aucun résultat ne sort, l'application explique pourquoi.
- Les mises à jour de composition ne notifient plus que les joueurs **ajoutés ou retirés**.
- La liste de force apparaît à côté des joueurs sélectionnés dans le mail de sélection.
- Détection des doubles réservations, gestion des remplaçants, tiroir de filtres sur l'écran de sélection.
- « Mes matchs » déplacé dans le menu de l'espace membre.

## 🗓️ Réunions du comité

- L'écran d'une réunion devient un **hub piloté par le statut** (à venir, en cours, clôturée).
- **Création rapide** et édition directement sur la carte — l'ancien assistant en plusieurs étapes a disparu.
- **Page de procès-verbal dédiée avec sauvegarde automatique** et **prise de notes en direct**, protégée par un verrou souple pour éviter que deux personnes écrivent en même temps.
- Suivi des sondages de disponibilité envoyés, mise en avant de la date qui se dégage.
- Publication d'une réunion sur le site pré-remplie depuis ses informations.
- Archivage et suppression des réunions.

## 💶 Trésorerie et paiements

- **Import des extraits bancaires** robuste : détection des doublons, journalisation, gestion des fichiers encodés en ISO-8859-1.
- **Actions groupées et tiroir de filtres** sur les paiements et les transactions, avec filtres par type et par nom d'événement.
- **Amendes** : les amendes fédérales peuvent être répercutées sur le membre concerné, et le trésorier peut annuler une amende émise par erreur.
- **Annulation d'inscription avec remboursement optionnel**.
- Communications structurées corrigées (clé de contrôle sur deux chiffres).
- IBAN normalisés et formatés partout (membres, tuteurs, club) ; BIC et IBAN ne sont plus codés en dur.
- E-mails de rappel unifiés, avec le bon libellé et la bonne mention.
- Le délai de paiement réel des tournois est affiché, et le membre est prévenu quand son inscription impayée est annulée.

## 🍺 Bar

- Gestion du panier fiabilisée (validation, session).
- Gestion du stock revue, traitement des commandes amélioré.
- Feuille de caisse retravaillée, envoi par e-mail, méthodes de paiement, affichage du créateur de la commande.
- Réglages du bar configurables.
- Suivi des clés du club et attribution du détenteur de la caisse.

## 📋 Journal d'activité

Un **journal d'activité** enregistre les créations, modifications et suppressions sur l'ensemble des domaines du club (membres, paiements, inscriptions, compétitions, réunions, bar, réglages).

- Consultable sur `/admin/club-admin/audit`, filtrable par type d'élément, auteur, action et période.
- Les modifications affichent le détail avant → après.
- Recherche par membre et par type d'élément.
- Accessible aux administrateurs, au président, au vice-président, au secrétaire et au trésorier.

## 🌐 Site public

- **Pages Événements et Résultats** publiques, avec barre de filtres.
- Les articles affichent un **temps de lecture** et une description pour les moteurs de recherche.
- Le calendrier des interclubs de la page d'accueil est configurable, avec une chaîne de priorité par saison.
- Un article ou une réunion non publiée n'est plus servie sur le site public.
- Favicon, états vides soignés, pied de page consolidé.

## 🏠 Salles et tables

- La liste des tables est intégrée dans une **page de détail par salle**.
- Vocabulaire des états de table unifié (trois vocabulaires concurrents remplacés par un seul).
- Les tables non assignées sont sorties de la grille de salle.

## ✨ Ergonomie et présentation

- **Tiroir de filtres standardisé** sur tous les écrans de liste, avec puces de filtres actifs.
- **Actions groupées** (sélection multiple) généralisées.
- Sur mobile : en-têtes allégés et **feuille d'actions en bas d'écran** sur les 11 pages de liste.
- Actions secondaires regroupées dans un menu « Plus d'actions ».
- **Calendrier en grille adaptative** avec sélection instantanée du jour et liens directs.
- Notifications : feuille de bas d'écran sur mobile, retour visuel à chaque appui sur la cloche.
- Badges de statut unifiés, échelle typographique et couleurs cohérentes, mode sombre corrigé.
- Nombreuses corrections d'affichage sur mobile.

## 🔒 Sécurité et confidentialité

- Pages du back-office verrouillées au niveau des routes.
- **Documents des membres stockés sur un disque privé**, plus accessibles par URL directe.
- Les pages de l'espace membre sont strictement réservées à leur propriétaire.
- Lien d'invitation signé et limité en fréquence.
- Politique de mot de passe globale renforcée.
- **RGPD** : les demandes d'effacement notifient les administrateurs et le secrétaire, et signalent les paiements encore en attente.
- Mises à jour de sécurité des dépendances.

## 🌍 Langues

- **Traduction néerlandaise complète** (nl_BE), y compris les clés manquantes rattrapées en cours de route.
- Messages de validation en français clair.
- Vocabulaire de l'affiliation unifié dans toutes les langues.
- E-mails d'invitation et de vérification traduits.

## 📖 Aide et documentation

- **Centre d'aide intégré** à l'application sur `/admin/aide`.
- Manuels dédiés pour les **capitaines**, les **sélectionneurs** et les **responsables du matériel**, en français.
- Documentation des packs et séances d'entraînement.

---

## 🐛 Corrections notables

- Les membres archivés ne font plus planter le tableau de planning.
- Plus de doublon de compte quand on accueille un contact déjà connu.
- Le type de licence est verrouillé tant que le membre n'a pas d'abonnement pour la saison en cours.
- Les effectifs ne comptent que les membres actifs.
- Le tri de la colonne « Nom » suit prénom puis nom.
- Les filtres de la liste des membres sont conservés dans l'adresse de la page (partageable, rechargeable).
- Le logo des e-mails s'affiche correctement dans tous les clients de messagerie.
- Une date d'achat de table laissée vide n'entraîne plus d'erreur.
- Boutons de réinitialisation sans effet retirés du profil, bouton « Donner une amende » retiré de la vue mobile.
- Correction de la coquille « Admin Pannel » dans le fil d'Ariane.

---

*Document généré à partir des 270 modifications apportées à l'application entre le 26 mai et le 30 juillet 2026.*
