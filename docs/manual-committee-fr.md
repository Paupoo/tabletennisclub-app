# Manuel utilisateur — Membre du comité

Ce manuel couvre tout ce qu'un membre du comité (et un administrateur) peut faire dans l'application, en plus des fonctionnalités accessibles aux membres ordinaires. Pour les fonctionnalités de niveau membre (profil, calendrier, inscriptions, tournois), référez-vous au [Manuel membre](manual-member-fr.md).

---

## Résumé des permissions

| Action | Comité | Admin |
|---|---|---|
| Créer des membres | ✅ | ✅ |
| Modifier des membres | ✅ | ✅ |
| Activer/désactiver | ✅ | ✅ |
| Promouvoir/rétrograder membre du comité | ✅ | ✅ |
| Supprimer (archiver) des membres | ❌ | ✅ |
| Restaurer des membres archivés | ❌ | ✅ |
| Promouvoir/rétrograder administrateur | ❌ | ✅ |
| Anonymiser un membre (RGPD) | ❌ | ✅ |
| Gérer les tournois | ✅ | ✅ |
| Gérer les interclubs | ✅ | ✅ |
| Gérer les réunions | ✅ | ✅ |
| Gérer les packs d'entraînement | ✅ | ✅ |
| Gérer le contenu du site web | ✅ | ✅ |
| Gérer la trésorerie | ✅ | ✅ |

> **Groupe gestionnaire** : certaines fonctions récentes — gérer les contacts (qualifier, répondre, intégrer) et les modèles d'email, **éditer** le roster de saison, **composer/importer** dans le tableau de planification — sont réservées aux administrateurs et aux membres du comité ayant le rôle **secrétaire, président ou vice-président**. Les autres membres du comité conservent l'accès en **consultation** (voir et exporter).

---

## 1. Gestion des membres

### Accéder à la liste des membres

Allez dans **Administration club → Membres**. La liste affiche par défaut tous les membres actifs.

### Fonctionnalités de la liste

- **Recherche** : saisissez un nom ou un email pour filtrer en temps réel
- **Filtres** : filtrer par type de licence (compétitif/récréatif), genre, statut actif, équipe
- **Badge d'invitation** : chaque ligne affiche le statut d'invitation du membre :
  - 🟢 **Actif** — le membre a défini son mot de passe et s'est connecté
  - 🟡 **En attente** — invitation envoyée, pas encore acceptée (dans les 48h)
  - 🔴 **Expiré** — lien d'invitation expiré, à renvoyer
  - ⚪ **Non invité** — aucune invitation envoyée
- **Badge de paiement** : affiche le statut payé/non payé pour la saison en cours ; cliquez pour voir les détails d'affiliation et de paiement
- **Tri** : cliquez sur les en-têtes de colonne pour trier

### Créer un nouveau membre (inviter)

Cliquez sur **Nouveau membre** (en haut à droite). Remplissez le formulaire organisé en sections :

- **Identité** : prénom, nom, genre, date de naissance, photo
- **Contact** : email (obligatoire), téléphone, adresse
- **Statut club** : interrupteur actif, compétitif/récréatif, numéro de licence, classement
- **Rôles** : membre du comité, rôle au sein du comité, entraîneur, administrateur (admin uniquement)
- **Documents** : certificat médical, consentement parental
- **Tuteur/Dépendants** : rechercher et rattacher un tuteur si le membre est mineur (moins de 18 ans)
- **Sécurité** : optionnel — vous pouvez déclencher une réinitialisation de mot de passe ou renvoyer une invitation depuis ici

Cliquez sur **Enregistrer**. Le membre est créé et un email d'invitation est envoyé automatiquement à son adresse email.

### Modifier un membre

Cliquez sur le bouton **Modifier** (icône crayon) sur la ligne du membre ou depuis son profil. Même formulaire que lors de la création. Les modifications sont sauvegardées immédiatement.

### Renvoyer une invitation

Sur la liste des membres, cliquez sur **Renvoyer l'invitation** (icône enveloppe) sur n'importe quelle ligne avec le statut **En attente** ou **Expiré**. Un nouveau lien valable 48 heures est envoyé. Le timestamp `last_invited_at` est mis à jour, réinitialisant le délai d'expiration.

### Activer/désactiver un membre

Sur la liste des membres ou dans le formulaire de modification, basculez l'interrupteur **Actif**. Les membres inactifs ne peuvent pas se connecter. Ils apparaissent toujours dans la liste (utilisez le filtre actif pour les masquer).

### Archiver un membre (admin uniquement)

Cliquez sur le bouton **Supprimer** sur la ligne d'un membre. Une barre de confirmation apparaît en bas : confirmez pour archiver. Les membres archivés sont **supprimés de façon réversible** — ils disparaissent de la liste par défaut mais leurs données sont préservées.

### Voir les membres archivés (admin uniquement)

Basculez sur l'onglet **Archivés** dans la liste des membres. Les membres archivés apparaissent avec un bouton **Restaurer**. Cliquez sur restaurer pour les réactiver.

### Anonymiser un membre — RGPD (admin uniquement)

Pour les demandes d'effacement RGPD : ouvrez le profil du membre, cliquez sur **Anonymiser (RGPD)**. Un modal vous demande de saisir **ANONYMISER** pour confirmer. Cette action :
- Remplace le nom, l'email, le téléphone, l'adresse et l'IBAN par des valeurs anonymes
- Supprime la photo et les documents
- Supprime (de façon réversible) le compte du membre
- **Irréversible**

Les résultats de tournois et les enregistrements de paiements sont conservés (aucune donnée personnelle n'y est rattachée).

### Actions groupées (bulk)

Sélectionnez plusieurs membres avec les cases à cocher. Une barre de confirmation apparaît en bas avec les actions disponibles :
- **Activer la sélection** — marque les membres comme actifs
- **Désactiver la sélection** — marque les membres comme inactifs
- **Archiver la sélection** (admin uniquement) — suppression réversible de tous les membres sélectionnés

Après confirmation, un message de succès s'affiche.

### Gestion des tuteurs

Lors de la création ou de la modification d'un **mineur** (moins de 18 ans), la section **Tuteur/Dépendants** s'affiche. Tapez le nom d'un parent dans la zone de recherche :
- Si trouvé : cliquez sur son nom pour le rattacher
- Si non trouvé : un petit formulaire en ligne apparaît — renseignez le prénom, le nom, le téléphone, l'email et optionnellement l'IBAN, puis cliquez sur **Ajouter le tuteur**

Un tuteur peut couvrir plusieurs frères et sœurs. Un tuteur qui est également membre du club peut être recherché dans la liste des membres existants.

**Important :** Un mineur ne peut pas finaliser son affiliation sans tuteur rattaché. Le système avertit à l'enregistrement si aucun tuteur n'est renseigné, et bloque l'affiliation jusqu'à ce qu'il soit ajouté.

---

## 2. Gestion des contacts (flux d'intégration)

Allez dans **Site web → Contacts**. Les contacts soumis via le formulaire public du site du club apparaissent ici.

> **Qui peut gérer ?** La consultation des contacts est ouverte à tout le comité. En revanche, **gérer** un contact (qualifier le profil, envoyer un email, intégrer comme membre, supprimer) est réservé au **groupe gestionnaire** : administrateurs et membres du comité ayant le rôle **secrétaire, président ou vice-président**. Les autres membres du comité voient les contacts mais n'ont pas les boutons d'action.

### Statuts des contacts

- **Nouveau** — vient d'arriver, pas encore traité
- **En cours** — en cours de traitement (suivi en cours)
- **Traité** — pris en charge
- **Refusé** — décliné

### Qualifier un contact (profil)

L'objectif est de **noter petit à petit** ce qu'on apprend au fil des échanges, sans rien imposer. Ouvrez le panneau de détail d'un contact : le bloc **Profil** propose des champs **tous optionnels**, à remplir quand l'information arrive :

- **Catégorie d'âge** — enfant / adolescent / adulte
- **Expérience** — n'a jamais joué / quelques mois / quelques années / joueur classé
- **Souhaite la compétition** — oui / non / non renseigné
- **La famille peut conduire** — utile pour le covoiturage interclubs
- **Jours souhaités** — créneaux d'entraînement envisagés

Chaque champ se sauvegarde au fil de l'eau. Ces qualifications servent à **trier l'inbox** (filtres par âge, expérience, envie de compétition) et à **retrouver les bons profils** pour composer des groupes homogènes. Quand le contact devient membre (intégration), ces informations sont **reportées** sur sa première inscription — pas de double saisie.

> **Astuce** : certains modèles d'email sont des *questionnaires* conçus pour **récolter** justement ces informations manquantes (voir « Modèles d'email » ci-dessous).

### Intégrer un contact comme membre

Pour les contacts avec l'intérêt **Nous rejoindre** ou **Essai** :

1. Ouvrez le panneau de détail du contact (cliquez sur la ligne)
2. Cliquez sur **Intégrer comme membre**
3. Un formulaire de création de membre pré-rempli s'ouvre (tiroir) avec le nom, l'email et le téléphone déjà renseignés à partir du contact
4. Vérifiez et ajustez si nécessaire, puis cliquez sur **Enregistrer**
5. Un email d'invitation est envoyé automatiquement à l'adresse email du contact
6. Le statut du contact est automatiquement mis à jour à **Traité**

Cela élimine la double saisie — le secrétariat vérifie et confirme, sans avoir à tout retaper.

### Envoyer des emails aux contacts

Depuis le panneau de détail du contact, bloc **Envoyer un email** :

- **Choisir un modèle** dans la liste déroulante → l'éditeur s'ouvre **automatiquement, pré-rempli** avec l'objet et le corps du modèle, les variables déjà résolues (prénom, intérêt, nom du club…). Vous **relisez/ajustez** le texte, puis envoyez. Vous pouvez vous envoyer une copie.
- **Email personnalisé…** → ouvre l'éditeur **vierge** pour rédiger librement.

Certains modèles appliquent **automatiquement un statut** au contact lors de l'envoi (par ex. le modèle de refus passe le contact en *Refusé*, un modèle de bienvenue en *Traité*).

### Modèles d'email

Allez dans **Site web → Modèles d'email** (réservé au groupe gestionnaire). Vous y créez et modifiez librement les modèles de réponse, sans dépendre d'un développeur :

- **Nom**, **clé**, **objet**, **corps** du message
- **Variables** disponibles à insérer dans l'objet/corps : `{{first_name}}`, `{{last_name}}`, `{{full_name}}`, `{{interest}}`, `{{club_name}}`
- **Statut appliqué** (optionnel) : statut donné au contact quand le modèle est envoyé
- **Questionnaire d'information** : marque les modèles destinés à *récolter* des informations manquantes
- **Actif/inactif** : un modèle inactif n'apparaît plus dans la liste d'envoi

Quelques modèles « système » de départ existent (bienvenue, infos cotisation, demande d'infos, refus poli, questionnaire, invitation à l'essai) ; leur **clé** est verrouillée mais leur texte reste entièrement modifiable.

### Mettre à jour le statut d'un contact

Cliquez sur le badge de statut sur une ligne ou dans le panneau de détail pour faire défiler les statuts.

### Supprimer un contact

Cliquez sur **Supprimer** dans le panneau de détail. Le contact est définitivement supprimé (suppression permanente).

---

## 3. Gestion des tournois

Allez dans **Événements club → Tournois**.

### Créer un tournoi

Cliquez sur **Nouveau tournoi** (assistant). Remplissez les étapes :
1. **Informations de base** : nom, date, lieu, prix, nombre maximum de joueurs
2. **Format** : poules, qualifiés, type de match, sets gagnants, handicap
3. **Horaires** : heure de début, durée par match, marge logistique
4. **Publication** : vérifiez et publiez (le rend visible aux membres)

### Gérer un tournoi en direct

Depuis la page du tournoi, cliquez sur **Gérer en direct**. Onglets disponibles :
- **Matchs à venir** : prochains matchs à appeler
- **Tables** : assignation des tables
- **Poules** : classements des poules
- **Tableau** : tableau à élimination directe (après la phase de poules)
- **Classements** : classements en temps réel
- **Clôture** : terminer le tournoi et publier les résultats

### Inscription groupée

Depuis la page de gestion du tournoi, cliquez sur **Inscription groupée** pour ajouter plusieurs membres à la fois.

### Inviter des joueurs externes

Depuis la page de gestion du tournoi, cliquez sur **Inviter** pour envoyer des invitations à des non-membres (par email).

---

## 4. Gestion des interclubs

Allez dans **Événements club → Interclubs**.

### Centre de contrôle

Le centre de contrôle interclubs affiche toutes les compétitions interclubs actives, le calendrier des matchs et les résultats.

### Gestion des équipes

Allez dans **Interclubs → Équipes**. Créez ou modifiez des équipes. Assignez un capitaine à chaque équipe. Le constructeur d'équipes vous permet d'ajouter/retirer des membres.

### Sélection des capitaines

Allez dans **Interclubs → Sélection capitaines**. Examinez les joueurs disponibles pour chaque journée et confirmez la composition d'équipe.

### Gestion des disponibilités

Envoyez des demandes de disponibilités aux joueurs avant chaque journée depuis la page de gestion des interclubs. Les joueurs répondent par email ou dans l'application. Consultez les réponses dans le tableau de bord des disponibilités.

### Diffusion des compositions

Une fois confirmée, diffusez la composition à tous les joueurs sélectionnés via **Diffuser la composition** — envoie à chaque joueur sa notification de sélection.

### Résultats des matchs

Saisissez les résultats des matchs depuis le centre de contrôle après chaque journée. Les résultats mettent à jour les classements automatiquement.

---

## 5. Réunions

Allez dans **Événements club → Réunions**.

### Créer une réunion

Cliquez sur **Nouvelle réunion**. Remplissez :
- Titre et description
- **Sondage de date** : proposez plusieurs options de dates aux participants pour qu'ils votent, OU définissez une date fixe
- Membres invités : sélectionnez dans la liste des membres

### Envoyer les invitations

Depuis la page de la réunion, cliquez sur **Envoyer les invitations**. Chaque membre invité reçoit un email de RSVP.

### Gérer les RSVP

Consultez les réponses de présence en temps réel sur la page de détail de la réunion. Réponses : Présent / Absent / Peut-être. Les réservations de repas apparaissent si cette option est activée.

### Ordre du jour

Ajoutez des points à l'ordre du jour de la réunion. L'ordre du jour est visible pour tous les membres invités.

### Procès-verbal

Après la réunion, ajoutez le procès-verbal (texte libre). Cliquez sur **Envoyer le PV** pour envoyer le procès-verbal par email à tous les participants.

### Points d'action

Créez des points d'action de suivi depuis la réunion, assignés à des membres spécifiques avec des dates d'échéance.

### Reporter ou annuler

Cliquez sur **Reporter** ou **Annuler la réunion** depuis la page de détail. Tous les membres invités reçoivent une notification.

---

## 6. Gestion des entraînements

Allez dans **Événements club → Entraînements**.

### Packs d'entraînement

Les packs d'entraînement représentent des séances récurrentes (jour, niveau, type). Créez et gérez les packs depuis l'index des entraînements.

### Approuver les demandes d'entraînement

Lorsqu'un membre demande à s'inscrire à un pack d'entraînement, une notification apparaît. Allez dans le profil du membre → **Gestion des inscriptions → Entraînement** et cliquez sur **Approuver** ou **Refuser**. Le membre reçoit un email avec le résultat.

### Gérer les listes d'attente

Si un pack d'entraînement est complet, les membres rejoignent la liste d'attente. Lorsqu'une place se libère (annulation), le premier membre en attente reçoit automatiquement une offre de place par email.

### Tableau de planification (board)

Allez dans **Planification → Tableau de planification**. C'est un **outil d'aide à la décision** pour composer les groupes d'entraînement de la saison et **visualiser les tensions** (effectif vs capacité) — sans toucher aux inscriptions réelles.

**Important : un plan est un brouillon de réflexion.** Rien de ce que vous y faites n'est appliqué aux vrais packs ni aux inscriptions des membres. Une fois la décision prise en comité, vous créez/ajustez les vrais packs via l'écran des entraînements.

- **Créer un plan** : donnez-lui un nom et cliquez sur **Créer depuis la saison**. Le plan est initialisé à partir de la saison active : il copie les packs actifs et place les inscrits dans leur pack (ou dans la colonne **Pool** s'ils ne sont inscrits à aucun).
- **Composer par glisser-déposer** : déplacez les cartes membres d'une colonne (pack) à l'autre, ou vers/depuis le **Pool**. Chaque carte montre le **classement**, la **catégorie d'âge**, et les pastilles *compétitif / conduit / capitaine / bénévole* — pour viser des groupes homogènes.
- **Tensions** : l'en-tête de chaque colonne affiche l'effectif sur la capacité ; une colonne **au-dessus de la capacité** est signalée — repérez d'un coup d'œil les créneaux sous tension et si l'offre est trop large ou trop juste.
- **Modéliser l'offre** : ajoutez un **groupe hypothétique** (« + Ajouter un groupe »), modifiez son nom/niveau/jour/capacité, ou supprimez-le. Supprimer un groupe **renvoie ses membres au Pool** (personne n'est perdu). Vous pouvez ainsi tester « et si on ouvrait un 2ᵉ créneau ado ? ».
- **Exporter / Importer** : exportez un plan en **CSV, ODS ou XLSX** pour le partager au comité ou le retravailler dans un tableur, puis **réimportez un CSV** (correspondance par licence, sinon par email) pour réinjecter la répartition. L'import ne modifie que le plan.

**Permissions** : tout le comité peut **consulter** un plan et l'exporter ; **composer, modéliser l'offre et importer** sont réservés au **groupe gestionnaire** (admin, secrétaire, président, vice-président).

---

## 7. Contenu du site web

Allez dans **Site web** dans la navigation.

### Articles (actualités)

Créez et modifiez des articles d'actualité publiés sur le site web public du club :
- Titre, contenu (texte enrichi), image à la une
- Publier immédiatement ou enregistrer comme brouillon
- Définir une date "à la une jusqu'au" pour les articles épinglés

### Événements

Gérez les événements publics affichés dans le calendrier d'événements du site web.

### Contacts

Voir la section Gestion des contacts ci-dessus (section 2).

### Gestion des spams

Allez dans **Site web → Spams**. Vérifiez et gérez les soumissions signalées du formulaire de contact.

---

## 8. Administration du club

### Informations du club

Allez dans **Administration club → Infos club**. Mettez à jour les coordonnées du club : nom, adresse, email de contact, téléphone, IBAN, numéro d'entreprise, URL du site web.

### Salles

Allez dans **Administration club → Salles**. Gérez les lieux d'entraînement/compétition :
- Nom, adresse, nom du bâtiment
- Capacité pour les entraînements et les interclubs
- Nombre total de tables par salle

### Tables

Allez dans **Administration club → Tables**. Gérez les tables de ping-pong individuelles :
- Nom, marque
- Interrupteur de disponibilité (marquer hors service)

### Saisons

Allez dans **Administration club → Saisons**. Créez et activez des saisons de compétition (utilisées pour les affiliations, les interclubs, les entraînements). Chaque saison a un nom, une date de début et une date de fin. Une seule saison peut être active à la fois.

---

## 9. Liste de force

La liste de force classe les joueurs compétitifs par niveau pour la sélection des équipes interclubs.

- Allez dans **Administration club → Membres** et cliquez sur **Recalculer la liste de force** pour déclencher un recalcul manuel
- La liste se recalcule automatiquement à chaque modification du classement ou du statut compétitif d'un joueur
- Vous pouvez définir ou modifier manuellement la position d'un joueur dans la liste de force depuis son profil

---

## 10. Trésorerie

### Paiements

Allez dans **Trésorerie → Paiements**. Consultez tous les paiements des membres (inscriptions à des tournois, packs d'entraînement, affiliations) :
- Filtrer par statut : en attente, payé, partiellement payé, remboursé
- Cliquez sur un paiement pour voir les détails et le marquer comme payé

### Transactions

Allez dans **Trésorerie → Transactions**. Enregistrez les transactions bancaires (depuis votre relevé de compte) :
- Ajouter une transaction (montant, date, communication structurée, payeur)
- Réconcilier : associer les transactions aux paiements en attente

### Caisse (bar)

Allez dans **Trésorerie → Caisse**. Gérez les commandes du bar, la feuille de caisse et les mouvements de stock si le module bar est actif.

---

## 11. Inscriptions

Allez dans **Administration club → Inscriptions**. Consultez et gérez toutes les demandes d'affiliation en attente pour la saison en cours. Approuvez ou refusez les affiliations. Filtrez par statut.

### Implication du membre dans la saison

Au moment de l'inscription, chaque membre (ou le secrétariat pour lui) peut indiquer, **pour la saison** :

- **Compétitif** — souhaite jouer en interclubs/compétition
- **Peut conduire** (+ nombre de places) — pour organiser le covoiturage
- **Veut être capitaine** — volontaire pour porter une équipe
- **Aide bénévole** — disponible pour aider (tournois, buvette, arbitrage…)

Si le membre vient d'un contact intégré, ces champs sont **pré-remplis** à partir de ce qui avait été noté sur le contact.

### Roster de saison

Allez dans **Administration club → Roster de saison**. C'est la vue d'ensemble des inscrits de la saison active, une ligne par membre, avec : **classement**, **catégorie d'âge** (calculée depuis la date de naissance), **compétitif**, **conduite** (+ places), **capitaine**, **bénévole**.

- **Filtrez et triez** (par classement, catégorie d'âge, compétitif, conduite, capitaine, bénévole) pour préparer la composition des groupes et des équipes.
- **Édition rapide** : basculez directement conduite / capitaine / bénévole et ajustez le nombre de places, sans ouvrir chaque fiche.

Tout le comité peut **consulter** le roster ; l'**édition** est réservée au groupe gestionnaire (admin, secrétaire, président, vice-président).

---

## 12. Notifications envoyées aux membres

En tant que membre du comité, les emails suivants sont envoyés automatiquement — vous n'avez pas besoin de les déclencher manuellement :

| Déclencheur | Email envoyé à |
|---|---|
| Membre créé | Email d'invitation (lien définition mot de passe, 48h) |
| Invitation acceptée | Email de bienvenue |
| Inscription tournoi confirmée | Membre |
| Demande de paiement tournoi | Membre |
| Rappel de paiement tournoi | Membre |
| Ajout en liste d'attente | Membre |
| Place offerte sur liste d'attente | Membre |
| Tournoi annulé | Tous les membres inscrits |
| Tournoi modifié | Tous les membres inscrits |
| Demande de disponibilités interclubs | Membres concernés |
| Sélection interclubs confirmée | Membre sélectionné |
| Diffusion de composition interclubs | Tous les membres sélectionnés |
| Pack d'entraînement approuvé | Membre |
| Pack d'entraînement refusé | Membre |
| Séance d'entraînement annulée | Membres inscrits |
| Invitation à une réunion | Membres invités |
| Confirmation de RSVP réunion | Membre |
| Procès-verbal de réunion | Tous les participants |
| Réunion annulée/reportée | Tous les membres invités |
| Demande de remboursement | Administrateur |

---

## 13. Conseils & bonnes pratiques

- **Expiration de l'invitation** : si un membre ne définit pas son mot de passe dans les 48h, renvoyez l'invitation depuis la liste des membres (cherchez le badge 🔴 Expiré)
- **Mineurs** : rattachez toujours un tuteur avant de démarrer le processus d'affiliation — celui-ci est bloqué sans tuteur
- **Les actions groupées nécessitent une confirmation** : une barre apparaît en bas — vous devez cliquer sur Confirmer. Fermer la barre annule l'action
- **Membres archivés** : utilisez l'onglet Archivés pour restaurer un membre qui est parti et revenu
- **Demandes RGPD** : traitez les demandes d'anonymisation rapidement. La demande provient du profil du membre (Zone de danger). En tant qu'admin, ouvrez le membre et cliquez sur Anonymiser
- **Intégration des contacts** : traitez rapidement les contacts **Nous rejoindre** — ils attendent de vos nouvelles
