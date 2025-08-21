# Table Tennis Club

- [English](#about-this-project)
- [French](#à-propos-du-projet)

## About This Project

This application is designed to support the operations of a table tennis club in Belgium. It is a personal project primarily developed to learn programming, PHP, frameworks, databases, and various web technologies. Additionally, the goal is to assist any table tennis club in managing their players, competitions, and tournaments.

## Features

- Personal space for club members
- Management of club members
- Management of teams & interclubs
- Team composition management between captains and players
- Management of friendly tournaments
- Public website
  - Blog & news
  - Publication of interclub results
  - Publication of training sessions

## Roadmap

- **MVP0**: Allow players to subscribe to interclubs and captains to select and compose their teams
- **MVP1**: Send emails and notifications to club members (to confirm selections, announce events, etc.)
- **MVP2**: Create a blog feature
- **MVP3**: Enable online membership registration
- **MVP4**: Organize club events (barbecues, dinners, friendly tournaments)
- **MVP5**: Support live management of friendly tournaments (create pools, quarter-finals, semi-finals, finals...) and allow live updates of results

## Bugs & Defects

If you encounter any issues, please report them via email to aurelien.paulus@gmail.com. There is currently no bug tracking system in place.

### Known Issues

No known issues at this time. Please note that this project is still incomplete.

## Technologies

- [Laravel](https://laravel.com/)
- [MariaDB](https://mariadb.org/)
- [Node.js](https://nodejs.org/)
- [PHP](https://www.php.net/)
- [TailwindCSS](https://tailwindcss.com/)
- [Visual Studio Code](https://code.visualstudio.com/)

## License

The project is open-source and licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Tâches

### 🟥 High
- [x] Retirer les data mockup « résultats » 
- [ ] Débugger l’effacement d’un article 
- [x] Vérifier les infos dans les mails (nom, info, ip, …) 
- [x] Débuguer la suppression des contacts
- [ ] Implémenter la fonction de recherche avec livewire pour les contacts et les articles
- [ ] Implémenter la fonction de filtres avec livewire pour les contacts et les articles

### 🟧 Medium
- [x] Fix missing components (layouts.app and x::bladewind-empty-state). see in prod. 
- [x] Vérifier les liens dans les emails qui doivent ramener vers ctt ottignies 
- [ ] Coder une fonction pour envoyer une invitation à un utilisateur pour s’enregistrer et simplement confirmer son email 
- [x] Ajouter disclaimer « rgpd » / « utilisation des données » 
- [x] Ajouter gestion cookie légal avec législation
- [ ] Ajouter une fonction pour envoyer un QR et payer la cotisation par mail, mais aussi depuis la page "profil" de l'utilisateur
- [ ] Revoir la vue utilisateur (show/edit/profile...)
- [ ] Ajouter une fonction pour « nettoyer » les utilisateurs qui ne sont plus « rgpd » compliant ou demandent d’effacer leurs données 
- [x] Vérifier comment implémenter un système anti spam 
- [ ] Créer un model event / dériver les enfants (trainings / interclub / tournament…) et dédier leurs tables 
- [ ] Créer une vue globale par semaine pour voir s’il y a assez de joueur disponible ou pas 
- [ ] Créer une vue pour les joueurs qui veulent encoder leurs disponibilités 
- [ ] Créer une vue pour les capitaine lors de la composition des équipes 
- [ ] Créer une fonction pour confirmer les joueurs sélectionnés 
- [ ] Créer une vue pour s’enregistrer à un entraînement 
- [ ] Regarder s’il existe des méthode de paiement électronique vraiment pas chère 
- [ ] Ajouter bouton show/hide stats sur le dashboard 
- [ ] regrouper les couleurs des stats et des blocks par thème (users bleu, teams jaunes, etc.) 
- [ ] Ajouter les tournois dans le dashboard, ainsi que les contacts et les articles 
- [ ] Make all strings translatable and translate into English and into French all the strings.


### 🟩 Low
- [ ] Coder le CRUD pour mettre à jour les résultats 
- [ ] Mettre à jour le growler pour qu’il soit responsive sur tel 
- [ ] Gérer les pluriels/singuliers dans le dashboard et dans chaque vue avec des stats 
- [ ] Créer un système pour gérer les spams (vues, nombres, function pour bloquer des IP...) 


# Table Tennis Club

## À propos du projet

Cette application vise à soutenir la vie d'un club de tennis de table en Belgique. Il s'agit d'un projet personnel principalement réalisé pour apprendre la programmation, PHP, les frameworks, les bases de données et diverses technologies web. L'objectif est également d'aider n'importe quel club de tennis de table à gérer ses joueurs, ses compétitions et ses tournois.

## Fonctionnalités

- Espace personnel pour les membres du club
- Gestion des membres du club
- Gestion des équipes et des interclubs
- Gestion de la composition des équipes par les capitaines et les joueurs
- Gestion des tournois amicaux
- Site web public
  - Blog et actualités
  - Publication des résultats d'interclubs
  - Publication des entraînements

## Feuille de route

- **MVP0** : Permettre aux joueurs de s'inscrire aux interclubs et aux capitaines de sélectionner et composer leurs équipes
- **MVP1** : Envoyer des mails et des notifications aux membres du club (pour confirmer les sélections, annoncer des événements, etc.)
- **MVP2** : Créer un mécanisme de blog
- **MVP3** : Permettre l'inscription en ligne au club
- **MVP4** : Organiser des événements au sein du club (barbecues, repas, tournois amicaux)
- **MVP5** : Supporter la gestion en direct des tournois amicaux (création de poules, quarts de finale, demi-finales, finale...) et permettre la mise à jour des résultats en temps réel

## Bugs et défauts

Si vous trouvez un problème, merci de le signaler par mail à aurelien.paulus@gmail.com. Il n'y a pour l'instant aucun système de suivi des bugs.

### Problèmes connus

Aucun problème connu pour l'instant. Veuillez noter que ce projet est encore inachevé.

### Todo's
- remplacer "en savoir plus" par resultats
- logo + titre à revoir
- ajouter menu "contact"
- réduire les entrées stupides de mock dans le formulaire

## Technologies

- [Laravel](https://laravel.com/)
- [MariaDB](https://mariadb.org/)
- [Node.js](https://nodejs.org/fr)
- [PHP](https://www.php.net/)
- [TailwindCSS](https://tailwindcss.com/)
- [Visual Studio Code](https://code.visualstudio.com/)

## Licence

Le project est open-source sous licence [MIT](https://opensource.org/licenses/MIT).
