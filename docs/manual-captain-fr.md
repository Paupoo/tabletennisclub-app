# Manuel — Capitaine d'équipe interclubs

Ce manuel explique comment un capitaine gère la sélection de son équipe depuis l'application. Pour les fonctionnalités générales de membre (profil, calendrier, inscriptions), référez-vous au [Manuel membre](manual-member-fr.md).

---

## 1. Accéder à la vue des sélections

Allez dans **Interclubs → Sélections** dans la navigation principale. La page s'intitule **Sélections** et affiche tous les matchs de votre équipe pour la saison en cours.

Si vous êtes capitaine de plusieurs équipes, un sélecteur d'équipe apparaît en haut — un bouton **Filtres** vous permet de basculer entre vos équipes.

---

## 2. Lire le tableau des matchs

Chaque ligne correspond à un match interclub. De gauche à droite :

| Élément | Description |
|---|---|
| **Barre colorée** | Statut du match (voir ci-dessous) |
| **Semaine / Date** | Numéro de journée et date du match |
| **Adversaire** | Nom du club adverse, avec badge **Domicile** ou **Extérieur** |
| **Heure** | Heure de début |
| **X/Y dispo** | Nombre de joueurs disponibles sur le total requis |
| **Badge statut** | **Envoyée** si la feuille a été confirmée, ou le décompte de joueurs sélectionnés |
| **Boutons** | **Sélectionner** et demande de disponibilité (icône enveloppe) |

### Signification des couleurs

| Couleur | Statut | Signification |
|---|---|---|
| 🟢 Vert | **Confirmée** | La sélection a été envoyée à l'équipe — rien à faire |
| 🔵 Bleu | **Compo à envoyer** | Sélection complète enregistrée, mais pas encore envoyée à l'équipe. Un bouton **Envoyer** figure sur la ligne |
| 🟡 Orange | **Prête à composer** | Assez de joueurs disponibles, mais pas encore de sélection complète |
| 🔴 Rouge | **Attention** | Match dans les 14 jours dont la compo n'est pas partie : le club vise un envoi à J-14 au plus tard |
| ⚪ Gris clair | **À venir** | Match lointain, sans urgence |
| ◼ Gris foncé | **Passé** | Match terminé |

Une ligne rouge a aussi un fond légèrement teinté pour attirer l'œil.

Une sélection **déclarée à 3** (voir « Jouer à 3 » plus bas) et envoyée est **verte**, comme une sélection complète : pour vous, elle est réglée. En revanche, une sélection envoyée à 4 puis amputée d'un joueur **sans** joueur WO repasse en rouge ou orange : l'application ne la considère plus comme réglée.

Sur un match **déjà joué**, le bouton **Consulter** rouvre la composition en lecture seule.

### Bandeau d'alerte

Si un ou plusieurs matchs sont en statut **Attention**, un bandeau rouge s'affiche en haut de page avec un raccourci direct vers chaque match concerné. Cliquez sur un match dans ce bandeau pour ouvrir directement le tiroir de sélection.

---

## 3. Demander les disponibilités

Sur chaque ligne de match futur, cliquez sur l'**icône enveloppe** (à droite du bouton Sélectionner). Un email est envoyé à tous les membres de l'équipe qui n'ont pas encore répondu, leur demandant d'indiquer leur disponibilité.

> Seuls les membres qui n'ont pas encore répondu reçoivent l'email — inutile de les relancer si leur réponse est déjà enregistrée.

---

## 4. Faire la sélection

Cliquez sur **Sélectionner** sur la ligne du match voulu. Un tiroir s'ouvre sur la droite.

### Comprendre le tiroir de sélection

En haut du tiroir : une barre de progression **X / Y** indique combien de joueurs sont sélectionnés par rapport au nombre requis. La barre devient verte quand l'équipe est complète.

En dessous, la **liste des membres de l'équipe** s'affiche. Pour chaque joueur :

| Élément | Description |
|---|---|
| **Classement** | Numéro de classement du joueur (fond bleu = sélectionné, rouge = indisponible) |
| **Nom** | Nom du joueur |
| **Badge de disponibilité** | Vert = disponible, rouge = indisponible, absent = pas de réponse |
| **Note** | Message éventuel laissé par le joueur avec sa réponse |
| **Joués / Sél.** | Nombre de matchs joués et de sélections cette saison |
| **Case à cocher** | Indique si le joueur est dans la sélection actuelle |

### Sélectionner / désélectionner un joueur

Cliquez sur la carte d'un joueur pour le basculer dans ou hors de la sélection. Un joueur **bloqué** (icône cadenas 🔒) ne peut pas être sélectionné : il est déjà aligné dans une autre équipe cette semaine, la règle C.22 l'interdit, ou il n'a **pas d'indice de force** (sans place sur la liste des forces, pas de feuille de match — art. C.18.2.2). Le motif est écrit sous son nom.

> **Conseil :** Privilégiez les joueurs avec le badge **Disponible** (vert). Les joueurs sans réponse peuvent être sélectionnés, mais informez-les directement.

### Enregistrer sans envoyer

Cliquez sur **Enregistrer la sélection**. La sélection est sauvegardée et le statut du match passe à **Compo à envoyer** (bleu). **Vos joueurs ne savent encore rien** : la tâche n'est finie qu'une fois la feuille envoyée (section 5).

### Jouer à 3

Le règlement permet à une équipe messieurs de **débuter à 3 joueurs sur 4** (art. C.25.6), et à une équipe dames, jeunes ou vétérans à **2 sur 3** (art. C.25.7). Moins, c'est un forfait.

1. **Cherchez d'abord un 4e joueur** : les joueurs libres de la journée, ou un joueur d'une équipe inférieure, en respectant son indice (art. C.22.1.1), la règle du 3e joueur effectif de l'équipe supérieure (art. C.22.1.3) et la règle d'un seul match par semaine (art. C.20.1).
2. **Faute de mieux, désignez le joueur WO.** Au minimum, le bloc **« Toujours à 3 ? Désignez le joueur WO »** apparaît **en bas du tiroir**, après les joueurs libres et la recherche. Le joueur WO figure sur la feuille de match mais ne joue pas : il perd ses matchs et **ne peut être aligné dans aucune autre équipe de la catégorie cette semaine** (art. C.20.1). Les indisponibles et ceux qui n'ont pas répondu sont proposés en premier. **Sans joueur WO, la sélection reste un brouillon et personne n'est convoqué.**
3. **À l'enregistrement**, la fenêtre d'envoi s'ouvre avec un rappel : les matchs du joueur manquant seront perdus. Tous les e-mails de composition annoncent que l'équipe joue à 3 et marquent le joueur **WO** ; les autres membres de l'équipe reçoivent un appel à se manifester s'ils se libèrent.
4. **Sous le minimum**, l'application enregistre sans envoyer et vous rappelle de prévenir le forfait **au moins 48 heures avant** (art. C.33.1).

Le joueur WO passe les mêmes contrôles que les autres (indice de force, C.22, un match par semaine), mais ne compte **jamais** dans le seuil C.22 des équipes inférieures : seul un joueur qui a joué un point est effectif (art. C.22.1.3).

La déclaration est enregistrée avec votre nom et la date. Elle tombe si vous cochez un vrai 4e joueur (il prend la place du WO), si vous retirez le joueur WO ou si l'équipe passe sous le minimum ; elle survit à l'échange d'un joueur contre un autre.

---

## 5. Envoyer la feuille à l'équipe

Une fois la sélection enregistrée, une fenêtre **Notifier l'équipe** s'ouvre automatiquement.

**Le club vise un envoi au moins deux semaines avant chaque match**, pour que chacun puisse s'organiser. N'attendez pas d'être certain de tout : une modification reste possible jusqu'au jour du match, et **seuls les joueurs ajoutés ou retirés sont prévenus**. Pour ne rien oublier :

- une compo enregistrée mais pas envoyée porte un bouton **Envoyer** sur la ligne du match ;
- votre tableau de bord affiche **« N compos à envoyer à votre équipe »** ;
- chaque **dimanche à 18 h**, un e-mail liste vos matchs des trois semaines à venir dont la compo n'est pas partie (rien n'est envoyé si tout est parti).

### Ce que reçoivent les joueurs

Tous les membres de l'équipe reçoivent un email avec la composition retenue. Les joueurs **sélectionnés** reçoivent en plus une **invitation calendrier (ICS)** à ajouter dans leur agenda.

### Ajouter un message de rendez-vous (optionnel)

Dans la zone de texte **Infos de rendez-vous**, saisissez des précisions pratiques : heure de rassemblement, tenue, lieu de départ... Ce message est inclus dans l'email envoyé à l'équipe.

> Exemple : *"Rendez-vous à 18h45 à l'entrée du hall, prévoir le maillot du club."*

### Envoyer

Cliquez sur le bouton d'envoi. Le statut du match passe à **Confirmée** (vert). L'action est irréversible mais vous pouvez faire une nouvelle sélection si nécessaire (un nouvel email sera envoyé).

### Ne pas envoyer maintenant

Si vous voulez sauvegarder sans notifier l'équipe, cliquez sur **Envoyer plus tard**. La sélection est enregistrée mais aucun email n'est envoyé, et un message orange vous le rappelle. Le statut reste **Compo à envoyer** (bleu) jusqu'à l'envoi.

---

## 6. Changer de saison

En haut à droite de la page, un sélecteur de saison vous permet de consulter les sélections des saisons précédentes. Les matchs passés affichent la composition envoyée mais ne sont plus modifiables.

---

## 7. Questions fréquentes

**Un joueur ne répond pas à la demande de disponibilité.**
Relancez-le manuellement, ou sélectionnez-le quand même si vous savez qu'il est disponible. L'email de sélection lui indiquera sa composition.

**Je me suis trompé dans la sélection après envoi.**
Rouvrez le tiroir de sélection, modifiez, enregistrez et renvoyez la feuille. Un nouvel email sera envoyé à toute l'équipe avec la composition corrigée.

**Un joueur se désiste et je n'ai personne pour le remplacer.**
Retirez-le, cochez *« nous jouerons à 3 »*, enregistrez et envoyez. Le joueur retiré est prévenu, les 3 restants sont reconvoqués avec la mention « à 3 ». Sans la case, seul le joueur retiré est prévenu et le match repasse à traiter ; vous pouvez revenir la cocher plus tard.

**Un joueur est bloqué (cadenas rouge).**
Il est déjà sélectionné dans une autre équipe du club pour cette journée. Choisissez un autre joueur ou contactez le sélectionneur du club pour déplacer la sélection.

**Je ne vois pas mon équipe.**
Vérifiez que vous êtes bien désigné capitaine de l'équipe par le comité. Si le problème persiste, contactez l'administrateur.
