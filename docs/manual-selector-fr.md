# Manuel — Sélectionneur / Comité interclubs

Ce manuel explique comment un sélectionneur ou un membre du comité utilise la vue globale des sélections pour superviser toutes les équipes et intervenir si nécessaire. Pour les fonctionnalités de capitaine (faire la sélection d'une équipe, envoyer la feuille), référez-vous au [Manuel capitaine](manual-captain-fr.md).

---

## 1. Accès et permissions

La vue des sélections est accessible aux :
- **Capitaines** — voient uniquement leur(s) propre(s) équipe(s)
- **Sélectionneurs** (`is_selector`) — voient toutes les équipes du club, peuvent faire la sélection pour n'importe quelle équipe et chercher des remplaçants parmi tous les joueurs du club
- **Membres du comité / Administrateurs** — accès identique au sélectionneur

Accédez à la page via **Interclubs → Sélections**.

---

## 2. Vue globale — toutes les équipes

En tant que sélectionneur, toutes les équipes de la saison sont visibles. Utilisez le bouton **Filtres** (en haut à droite) pour afficher une équipe en particulier.

Chaque équipe affiche :
- Son **nom** et sa **division** (badge)
- Le **nom du capitaine**
- La liste de ses matchs avec leur statut

---

## 3. Interpréter les statuts

Chaque match porte un statut matérialisé par une **barre colorée** à gauche de la ligne.

| Couleur | Statut | Ce que cela signifie pour vous |
|---|---|---|
| 🟢 Vert | **Confirmée** | Sélection envoyée — rien à faire |
| 🟡 Orange | **À confirmer** | Assez de joueurs disponibles ou sélection complète, mais la feuille n'a pas encore été envoyée. Relancez le capitaine ou envoyez vous-même. |
| 🔴 Rouge | **Attention** | Match dans les 14 jours et pas assez de disponibilités. Action requise en priorité. |
| ⚪ Gris clair | **À venir** | Match lointain, sans urgence |
| ◼ Gris foncé | **Passé** | Match terminé |

Le compteur **X/Y dispo** (en gris à droite de chaque ligne) indique combien de joueurs ont signalé leur disponibilité sur le nombre total requis.

---

## 4. Bandeau d'alertes urgentes

Si un ou plusieurs matchs de n'importe quelle équipe sont en statut **Attention**, un **bandeau rouge** s'affiche en haut de page. Il liste tous ces matchs avec leur nombre de joueurs disponibles.

Cliquez sur un match dans le bandeau pour ouvrir directement le tiroir de sélection de ce match — sans avoir à naviguer jusqu'à l'équipe concernée.

> Ce bandeau est votre tableau de bord prioritaire. En début de semaine, vérifiez qu'il est vide.

---

## 5. Prioriser les actions

Ordre de priorité recommandé à chaque visite :

1. **Bandeau rouge** — traitez immédiatement tous les matchs en statut **Attention** dans les 14 jours
2. **Statut orange** — vérifiez que le capitaine est au courant ; envoyez la feuille si le capitaine est absent
3. **Statut gris clair (À venir)** — vérifiez que les demandes de disponibilité ont été envoyées pour les matchs qui approchent

---

## 6. Intervenir sur une sélection

En tant que sélectionneur, vous pouvez ouvrir le tiroir de sélection de n'importe quel match, que vous soyez ou non capitaine de l'équipe.

Cliquez sur **Sélectionner** sur la ligne du match voulu. Le fonctionnement est identique au [Manuel capitaine](manual-captain-fr.md#4-faire-la-sélection), avec une fonctionnalité supplémentaire.

### Rechercher un remplaçant

En bas du tiroir de sélection, une section **Rechercher un remplaçant** est disponible exclusivement aux sélectionneurs et administrateurs.

Saisissez au moins 2 caractères du nom d'un joueur pour chercher parmi **tous les membres compétiteurs du club**, pas seulement les membres de l'équipe. Les résultats apparaissent en dessous du champ de recherche. Cliquez sur un joueur pour l'ajouter à la sélection.

> Un joueur trouvé via la recherche peut être sélectionné même s'il n'est pas dans la composition habituelle de l'équipe. Vérifiez manuellement qu'il est bien disponible et informé.

Un joueur déjà aligné dans une autre équipe pour cette semaine est signalé avec l'équipe concernée (icône cadenas 🔒) — il ne peut pas être sélectionné une seconde fois.

---

## 7. Envoyer la feuille au nom du capitaine

Après avoir fait ou modifié une sélection, cliquez sur **Enregistrer la sélection**. La fenêtre d'envoi s'ouvre. Vous pouvez ajouter un message de rendez-vous, puis envoyer. L'email sera envoyé à toute l'équipe, quelle que soit la personne qui a fait la sélection.

---

## 8. Changer de saison

Le sélecteur de saison en haut à droite permet de consulter l'historique des sélections des saisons précédentes, en lecture seule.

---

## 9. Ce que ne fait pas cette vue

- **Elle ne gère pas les compositions définitives (feuilles de match officielles)** — cela passe par les outils fédéraux
- **Elle ne suit pas les résultats** — consultez la section **Résultats** pour cela
- **Elle n'envoie pas de rappels automatiques** — les relances sont manuelles (bouton enveloppe sur chaque match)
