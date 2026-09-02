# Manuel utilisateur — Préparer une nouvelle saison

Ce manuel décrit, dans l'ordre, tout ce qu'il faut faire pour ouvrir une nouvelle saison : créer la saison, clôturer la précédente, rouvrir les affiliations, importer les affiliés depuis la fédération, puis importer le calendrier interclubs.

Il s'adresse au membre du comité qui porte la délégation **Saisons**, et croise deux autres délégations : **Membres** (affiliations, import du listing) et **Interclubs** (équipes, divisions, calendrier). Pour le reste du back-office, voyez le [Manuel membre du comité](manual-committee-fr.md).

> **Dans l'application** : les deux mêmes procédures existent en version courte dans le centre d'aide (**Aide** dans le menu), sous « Préparer une nouvelle saison » et « Importer le calendrier interclubs ». Ce manuel-ci est la version longue, avec les détails techniques.

> **Quand ?** En pratique entre début juillet et fin août. Le premier match d'interclubs se joue à la mi-septembre, et le calendrier de la fédération n'est complet que quelques semaines avant.

---

## Qui peut faire quoi

| Étape | Délégation nécessaire |
|---|---|
| 1. Créer la saison | **Saisons** (`seasons.manage`) |
| 2. Fermer les affiliations de la saison écoulée | **Membres** (`subscriptions.manage`) |
| 3. Activer la nouvelle saison | **Saisons** (`seasons.manage`) |
| 4. Ouvrir les affiliations | **Membres** (`subscriptions.manage`) |
| 5. Importer la liste des affiliés | **Membres** (`users.import`) |
| 6. Importer le calendrier interclubs | Accès serveur (voir §6) |
| 7. Recomposer les équipes | **Interclubs** (`interclubs.manage`) |

Un administrateur détient tout. Un membre du comité sans délégation ne voit ces écrans qu'en consultation.

---

## 1. Créer la saison

**En principe, vous n'avez rien à faire.** Chaque **1er juillet à 6 h**, l'application crée automatiquement les deux saisons à venir (du 1er septembre au 30 juin). Vérifiez simplement qu'elles existent.

Allez dans **Paramètres du club → Saisons**. La liste montre les saisons existantes, l'active étant marquée comme telle.

Si la saison manque :

- **Actions supplémentaires → Auto-provision**, puis **Provision** : recrée les deux saisons à venir d'après la saison active. L'opération est sans risque et peut être relancée : une saison qui existe déjà est ignorée.
- **Nouvelle saison** : pour une saison aux dates inhabituelles. Nom, date de début, date de fin. Les dates ne peuvent pas chevaucher une saison existante — l'application refuse et vous le dit.

> **Convention de nommage** : `2026-2027`, avec un tiret et quatre chiffres de chaque côté. Ce n'est pas cosmétique : **c'est ce nom qui relie la saison du club à celle de la fédération** lors de l'import du calendrier (§6). Un nom qui s'en écarte fera échouer l'import avec un message explicite.

---

## 2. Fermer les affiliations de la saison écoulée

Avant de basculer, fermez les affiliations de la saison qui se termine : cela empêche un membre de s'inscrire à une saison révolue pendant que vous préparez la suivante.

Allez dans **Admin membres → Affiliations**, puis **Actions supplémentaires → Fermer les affiliations**. Un message confirme : « Les affiliations sont maintenant fermées. »

Tant qu'elles sont fermées, le bouton **Inscrire un membre** disparaît et un membre qui tente de s'affilier depuis son espace est refusé.

---

## 3. Activer la nouvelle saison

Toujours dans **Paramètres du club → Saisons**, sur la ligne de la nouvelle saison, cliquez sur **Activer** et confirmez.

**Une seule saison est active à la fois** : activer la nouvelle désactive l'ancienne dans la même opération, il n'y a rien à désactiver à la main.

L'activation est structurante — c'est la saison active qui détermine ce que voient les affiliations, les interclubs, les entraînements et les tableaux de bord. Faites-la quand vous êtes prêt à enchaîner, pas des semaines à l'avance.

> Les données de la saison précédente ne sont pas touchées : les résultats et les compositions restent consultables toute l'année en changeant de saison dans les filtres.

---

## 4. Ouvrir les affiliations

Retournez dans **Admin membres → Affiliations**, puis **Actions supplémentaires → Ouvrir les affiliations**. Message de confirmation : « Les affiliations sont maintenant ouvertes. »

À partir de là, les membres peuvent s'affilier depuis leur espace personnel, et le secrétariat peut le faire pour eux avec **Inscrire un membre**.

---

## 5. Importer la liste des affiliés de la fédération

Quand la fédération publie le listing des affiliés, chargez-le plutôt que de le recopier.

Allez dans **Admin membres → Utilisateurs → Actions supplémentaires → Importer la liste des affiliés**, puis déposez le fichier.

L'écran vous présente **chaque ligne avec ce qu'il propose d'en faire** avant d'écrire quoi que ce soit : créer, mettre à jour, ignorer. Vous arbitrez, puis vous validez. Rien n'est envoyé aux membres à ce moment-là : **l'import ne déclenche aucune invitation**, c'est le comité qui décide plus tard quand donner un accès.

Voyez le [Manuel membre du comité](manual-committee-fr.md) pour le détail de l'écran de revue.

---

## 6. Importer le calendrier interclubs

C'est l'étape qui remplace le plus de travail manuel : équipes, divisions, clubs adverses et rencontres, chargés directement depuis la base de la fédération.

> **Aujourd'hui, c'est une commande à lancer sur le serveur**, par la personne qui a l'accès technique. Un bouton protégé par une délégation viendra dans l'interface ; le comportement décrit ici ne changera pas, seule la façon de le déclencher.

### Ce que ça charge

En une fois, pour la saison active :

- les **divisions** où le club est engagé, avec leur niveau et leur catégorie ;
- **nos équipes**, nommées par leur lettre (Hommes A à F, Vétérans A à C…) ;
- les **clubs adverses** rencontrés, créés s'ils sont inconnus ;
- **toutes les rencontres**, avec date, heure et adresse de la salle ;
- les **byes** (les journées où une équipe ne joue pas).

### La commande

```bash
php artisan interclubs:import-aftt
```

Elle ne demande aucun paramètre : le numéro de club est lu sur la fiche du club, et la saison est celle que la fédération déclare en cours, rapprochée de la saison du club **par son nom** (d'où l'importance du §1).

Elle affiche un tableau récapitulatif :

```
Importing 2026-2027 for BBW214 from the federation…

+---------+-------+-----------+---------+---------+
| Created | Moved | Unchanged | Removed | Refused |
+---------+-------+-----------+---------+---------+
| 129     | 0     | 0         | 0       | 0       |
+---------+-------+-----------+---------+---------+
```

- **Created** — rencontres nouvellement créées
- **Moved** — rencontres dont la date, l'heure ou la salle a changé ; chacune est listée en dessous
- **Unchanged** — rien à faire
- **Removed** — rencontres retirées du calendrier fédéral et supprimées chez nous
- **Refused** — divisions que l'application ne sait pas modéliser ; chacune est nommée

### Relancer la commande est sans danger

**C'est même le mode normal.** Les rencontres sont reconnues par l'identifiant de la fédération : une rencontre déplacée est **corrigée sur place**, sans être recréée. Les disponibilités déjà répondues, les sélections et les résultats saisis restent attachés.

Lancez-la donc à chaque fois que vous soupçonnez un changement — report, changement de salle, division recomposée.

### Ce qui arrive quand la fédération retire une rencontre

Cela se produit réellement, quand une équipe déclare forfait général et que la division est recalculée.

- Si **personne n'y a touché**, la rencontre est supprimée en silence : elle n'a jamais été vue.
- Si **quelqu'un y a répondu** (disponibilité, sélection, résultat), elle est **conservée** et signalée dans le rapport. À vous de décider.

Une rencontre encodée à la main, elle, n'est jamais touchée : l'import ne se reconnaît que dans ce qu'il a lui-même écrit.

### Divisions refusées

Si la fédération engage le club dans une catégorie que l'application ne connaît pas — une division **jeunes**, typiquement —, l'import la refuse et l'affiche :

```
Refused, category or level we do not model: Division 3B ... - Jeunes
```

Ce n'est pas une panne : c'est un signal. Les autres divisions sont importées normalement, et il faut décider comment le club veut modéliser cette catégorie avant d'aller plus loin. Signalez-le au responsable technique.

### Les byes

Une journée sans adversaire est chargée, mais **n'apparaît pas** dans le planning, dans « Mes matchs », sur le tableau de bord ni dans le calendrier exporté vers votre téléphone : elle n'y serait qu'une rencontre contre personne. Vous la retrouvez sur l'écran **Résultats**, où elle s'affiche « Bye ».

> Une réserve à connaître : la fédération ne donne aucune date à un bye. L'application lui attribue **le jour le plus tôt où le reste de la division joue cette journée-là**. C'est une déduction raisonnable, pas une information de la fédération.

### Recharger une saison à blanc — `--fresh`

```bash
php artisan interclubs:import-aftt --fresh
```

**À n'utiliser qu'une fois, en début de saison.** Cette option **vide la saison** — divisions, équipes, rencontres — puis la reconstruit depuis la fédération.

Elle emporte avec elle **les capitaines et les compositions d'équipes** : ce sont des données du club, que la fédération ne connaît pas et ne peut pas restituer. Il faudra les ressaisir (§7).

Avant d'agir, la commande annonce ce qu'elle va détruire et demande confirmation :

```
Rebuilding 2026-2027 deletes 129 fixtures, 81 teams, 0 captain assignments and 0 roster entries.
Delete them and rebuild from the federation? (yes/no)
```

Et si la saison porte déjà des réponses de membres, **elle refuse** :

```
Refusing: 1 availability answers and 0 recorded results would be lost.
Re-run with --force if that is really what you want.
```

En début de saison ces compteurs valent zéro et la commande ne vous gêne pas. Le jour où elle proteste, elle a raison : passez `--force` seulement si vous acceptez de perdre ces réponses.

### Préparer la saison suivante à l'avance — `--season`

```bash
php artisan interclubs:import-aftt --season=2027-2028
```

Utile si la fédération publie déjà la saison suivante sans l'avoir basculée en saison courante. Le nom doit être celui d'une saison **existant des deux côtés** ; sinon la commande s'arrête et le dit.

### Ce que l'import ne fait pas

- **Il ne charge pas les résultats ni les scores.** Ils se saisissent à la main dans **Interclubs → Résultats**. La fédération les publie et l'application saura les lire dans une prochaine version.
- **Il ne compose pas les équipes.** Il crée les équipes et leur lettre ; qui joue dedans reste une décision du club.

### Si quelque chose se passe mal

L'import **récupère tout auprès de la fédération avant d'écrire la moindre ligne**, et écrit en une seule transaction. Si la fédération ne répond plus au milieu, rien n'est écrit et la commande le dit :

```
Nothing was written. The federation failed mid-import: ...
```

Relancez plus tard : aucun état intermédiaire n'est resté en base.

---

## 7. Recomposer les équipes

Après un import `--fresh`, les équipes existent avec leur lettre et leur division, mais elles sont **vides** : pas de capitaine, pas de joueurs.

Allez dans **Interclubs → Configuration → Nos équipes** pour désigner les capitaines et rattacher les joueurs à chaque équipe.

Pensez aussi à la **liste de force**, qui classe les joueurs compétitifs et sert aux sélections : elle se recalcule seule à chaque changement de classement, mais après un import de listing fédéral il vaut la peine de forcer un recalcul depuis **Admin membres → Utilisateurs → Actions supplémentaires → Recalculer la liste de force**. Une position individuelle se corrige depuis le profil du joueur.

Ensuite seulement, les capitaines peuvent demander les disponibilités et composer leurs sélections.

---

## 8. Vérifications avant d'ouvrir la saison aux membres

Une passe rapide qui évite l'essentiel des surprises :

- [ ] La bonne saison est **active** (Paramètres du club → Saisons).
- [ ] Les **affiliations sont ouvertes**, et un membre test peut s'inscrire depuis son espace.
- [ ] Le **planning interclubs** affiche les rencontres, avec des adresses complètes (Interclubs → Planning).
- [ ] Le nombre d'équipes correspond à ce que le club a réellement engagé — **une équipe manquante ou en trop se voit ici**, pas en octobre.
- [ ] Chaque équipe a un **capitaine**.
- [ ] Aucune division n'est restée « Refused » dans le rapport d'import.

---

## Questions fréquentes

**Une rencontre a été déplacée par la fédération. Que faire ?**
Relancez `php artisan interclubs:import-aftt`. Elle sera corrigée sur place et listée sous « Moved ». Les disponibilités déjà données sont conservées. Prévenez ensuite les joueurs concernés — l'application ne le fait pas encore automatiquement.

**J'ai modifié une rencontre à la main, et l'import l'a écrasée.**
C'est voulu : la fédération fait autorité sur la date, l'heure, la salle et l'adversaire. Ce qui appartient au club — disponibilités, sélections, résultats — n'est jamais écrasé. Si une correction manuelle est vraiment nécessaire, elle sera défaite au prochain import ; signalez plutôt l'erreur à la fédération.

**Une équipe adverse s'appelle autrement chez nous que chez la fédération.**
Sans importance, et voulu aussi : les clubs sont reconnus par leur **numéro de licence** fédéral, pas par leur nom. L'import **ne renomme jamais** un club déjà enregistré, pour ne pas défaire les coordonnées, l'IBAN ou les contacts saisis par le club. Il complète seulement une adresse manquante.

**Puis-je lancer l'import plusieurs fois dans la journée ?**
Oui. Sans `--fresh`, c'est sans effet de bord.

**L'import échoue avec « the club has no season by that name ».**
La saison n'existe pas encore côté club, ou son nom ne correspond pas exactement à celui de la fédération (§1). Créez-la ou renommez-la, puis relancez.

---

## Voir aussi

- [Manuel membre du comité](manual-committee-fr.md) — le reste du back-office
- [Manuel capitaine](manual-captain-fr.md) — disponibilités et compositions
- [Manuel sélectionneur](manual-selector-fr.md) — liste de force et sélections
- [Délégations et permissions](permissions.md) — qui détient quoi
