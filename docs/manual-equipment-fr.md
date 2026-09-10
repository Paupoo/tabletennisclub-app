# Gestion des équipements confiés

Ce guide explique comment suivre le matériel appartenant au club et confié à des personnes : trousseaux de clés et caisses enregistreuses.

---

## Trousseaux de clés

### Pourquoi ?

Un trousseau permet d'ouvrir et de fermer les locaux. Le club en possède un nombre fini : certains sont dans la poche d'un membre, d'autres attendent au tiroir. L'inventaire répond à une seule question, mais la bonne : **un soir d'événement, à qui je m'adresse ?**

Chaque trousseau porte un **numéro attribué automatiquement**, qui n'est jamais réutilisé. Un trousseau retiré du service laisse donc un trou définitif dans la numérotation : le n°3 du club reste le seul n°3 de son histoire.

> Détenir un trousseau **n'ouvre aucun écran** de l'application. C'est une information de suivi, pas une permission.

### Où ?

**Administration → Paramètres du club → Trousseaux de clé**.

L'écran liste un trousseau par ligne : son numéro, son détenteur (ou « Au tiroir » si personne ne l'a) et ses notes éventuelles.

> Cet écran est réservé aux titulaires de la délégation **Installations** et aux **administrateurs**.

### Créer un trousseau

1. Cliquez sur **Créer un trousseau**.
2. Le numéro est attribué tout seul, vous n'avez rien à saisir.
3. Choisissez éventuellement un détenteur — laissez vide pour le garder au tiroir.
4. Ajoutez éventuellement une note (ce que le trousseau ouvre, où il est rangé).
5. Cliquez sur **Créer**.

### Déplacer un trousseau

1. Sur la ligne du trousseau, cliquez sur **Déplacer**.
2. La fenêtre rappelle qui le détient aujourd'hui.
3. Recherchez le nouveau détenteur par son nom.
4. Cliquez sur **Déplacer**.

Seuls les **membres actifs** apparaissent dans la recherche : un trousseau ne se confie pas à quelqu'un qui n'est plus au club. Laissez le champ vide pour remettre le trousseau au tiroir.

Si le détenteur actuel n'est plus actif, il reste affiché sur sa ligne et dans la fenêtre — c'est justement le trousseau à récupérer — mais il n'est pas proposé comme destination.

### Retirer un trousseau du service

1. Sur la ligne du trousseau, cliquez sur **Retirer**.
2. Confirmez.

Le trousseau quitte la liste mais **n'est pas effacé** : il conserve son numéro et son dernier détenteur. C'est ce qui permet de savoir de la poche de qui un trousseau perdu a disparu.

### Remettre un trousseau en service

1. Cochez **Afficher les trousseaux retirés** au-dessus de la liste.
2. Sur la ligne grisée, cliquez sur **Remettre en service**.

---

## Caisses enregistreuses

### Pourquoi ?

Chaque caisse du club est confiée à un membre (généralement le trésorier ou un délégué). Cela permet de savoir qui est responsable de chaque caisse à tout moment.

### Créer une caisse avec un détenteur

1. Allez dans **Administration → Trésorerie → Caisse**.
2. Cliquez sur **Nouvelle caisse**.
3. Saisissez le nom de la caisse et recherchez le détenteur par son nom.
4. Cliquez sur **Créer**.

> Cette action est réservée aux **administrateurs** et aux **trésoriers**.

### Changer le détenteur d'une caisse

1. Allez dans **Administration → Trésorerie → Caisse**.
2. Sélectionnez la caisse concernée (si plusieurs existent).
3. À côté du nom du détenteur actuel, cliquez sur **Changer**.
4. Recherchez le nouveau détenteur par son nom.
5. Cliquez sur **Enregistrer**.

> Cette action est réservée aux **administrateurs** et aux **trésoriers**.

Changer le détenteur **ne solde pas la caisse et ne transfère pas d'argent** : cela déplace seulement la responsabilité affichée.

### Retirer une caisse du service

1. Allez dans **Administration → Trésorerie → Caisse** et sélectionnez la caisse.
2. Cliquez sur **Retirer**, puis confirmez.

La caisse quitte la liste mais **son livre de comptes est intégralement conservé** : aucun mouvement n'est effacé. Cochez **Afficher les caisses retirées** pour la retrouver et **Remettre en service** pour la réactiver.

---

## Vue d'ensemble des équipements

Pour voir en un coup d'œil qui détient quoi :

1. Allez dans **Administration → Informations du club**.
2. Faites défiler jusqu'à la section **Détenteurs d'équipements**.

Vous y trouverez :
- Chaque trousseau avec son détenteur, y compris ceux qui sont au tiroir
- Pour chaque caisse : le nom de la caisse et le membre qui la détient

Les trousseaux retirés du service n'y figurent pas.

> Cette page est réservée aux titulaires de la délégation **Supervision** et aux **administrateurs**.

---

## Filtrer la liste des membres

Dans **Administration → Membres**, deux filtres sont disponibles :

| Filtre | Résultat |
|--------|---------|
| **A un trousseau** | Affiche uniquement les membres qui détiennent au moins un trousseau |
| **Détient une caisse** | Affiche uniquement les membres qui ont une caisse |

Pour activer un filtre : cliquez sur **Filtres** (icône entonnoir en haut à droite) puis activez le filtre souhaité.

---

## Consulter l'historique

Chaque création, déplacement et retrait de trousseau ou de caisse est enregistré dans le journal d'audit, avec l'auteur et la date.

Ouvrez **Audit** dans le menu, puis filtrez sur le modèle **Trousseau** ou **Caisse**.

> Le journal d'audit est réservé aux titulaires de la délégation **Supervision** et aux **administrateurs**.
