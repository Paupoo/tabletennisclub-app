---
title: Importer le calendrier interclubs
summary: Équipes, divisions, adversaires et rencontres, chargés depuis la fédération au lieu d'être recopiés. Ce que ça fait, et ce qu'il faut vérifier après.
audience: committee, secretary
order: 19
---

L'AFTT publie son calendrier dans une base que l'application sait lire. Plutôt que d'encoder à la main les divisions, les adversaires et les dizaines de rencontres, on les charge d'un coup.

> **Aujourd'hui, cette étape se lance depuis le serveur**, par la personne qui a l'accès technique — demandez-la-lui. Un bouton protégé arrivera dans l'interface ; ce que fait l'import ne changera pas, seulement la façon de le déclencher.
>
> La commande, pour information : `php artisan interclubs:import-aftt`

## Ce que ça charge

En une fois, pour la saison active :

- les **divisions** où le club est engagé, avec leur niveau et leur catégorie ;
- **nos équipes**, nommées par leur lettre — Hommes A à F, Vétérans A à C… ;
- les **clubs adverses**, créés s'ils sont inconnus ;
- **toutes les rencontres**, avec date, heure et adresse de la salle ;
- les **journées sans adversaire** (les « byes »).

Le résultat s'affiche en tableau : combien de rencontres créées, combien déplacées, combien inchangées, combien retirées, combien de divisions refusées.

## On peut le relancer autant qu'on veut

**C'est même l'usage normal.** Les rencontres sont reconnues par leur identifiant fédéral : une rencontre déplacée est **corrigée sur place**, pas recréée. Les disponibilités déjà données, les sélections et les résultats saisis **restent attachés**.

Relancez donc dès que vous soupçonnez un changement : un report, un changement de salle, une division recomposée. Les rencontres modifiées sont listées sous **Moved** — prévenez ensuite les joueurs concernés, l'application ne le fait pas encore toute seule.

## Ce que l'import respecte

- **Une rencontre encodée à la main n'est jamais touchée.** L'import ne se reconnaît que dans ce qu'il a lui-même écrit.
- **Un club adverse déjà enregistré n'est jamais renommé.** Les clubs sont reconnus par leur numéro de licence, pas par leur nom — vos coordonnées, IBAN et contacts sont donc à l'abri. Seule une adresse manquante est complétée.
- Si la fédération **retire** une rencontre — un forfait général, une division recalculée — elle est supprimée chez nous **si personne n'y a répondu**. Si quelqu'un y a répondu, elle est **conservée** et signalée : à vous de trancher.

## Ce qu'il ne fait pas

- **Il ne charge pas les résultats ni les scores.** Ils se saisissent à la main dans **Interclubs → Résultats**. La fédération les publie, l'application saura les lire dans une prochaine version.
- **Il ne compose pas les équipes.** Il crée les équipes et leur lettre ; qui joue dedans reste une décision du club.

## Deux messages qui méritent une explication

**« Refused, category or level we do not model »** — la fédération a engagé le club dans une catégorie que l'application ne sait pas encore représenter, typiquement une division **jeunes**. Ce n'est pas une panne : les autres divisions sont importées normalement. Signalez-le au responsable technique, il y a une décision à prendre sur la façon de modéliser cette catégorie.

**« the club has no season by that name »** — la saison n'existe pas encore côté club, ou son nom ne correspond pas exactement à celui de la fédération. Voyez le point 1 de [Préparer une nouvelle saison](preparer-une-nouvelle-saison).

## Le rechargement à blanc

Une option `--fresh` **vide la saison** — divisions, équipes, rencontres — et la reconstruit. Elle sert **une seule fois, en début de saison**, quand rien n'a encore été saisi.

Elle emporte avec elle **les capitaines et les compositions d'équipes**, qu'il faudra ressaisir. Avant d'agir, elle annonce ce qu'elle va détruire et demande confirmation ; et si des membres ont déjà répondu à des disponibilités, **elle refuse** plutôt que de les effacer.

> Si on vous propose de « recharger la saison » en cours d'année, posez la question : en dehors de la mise en place initiale, la réponse est presque toujours non. Le rechargement normal, sans option, suffit à corriger un calendrier.

## Les journées sans adversaire

Une journée où votre équipe ne joue pas est bien chargée, mais **elle n'apparaît pas** dans le planning, dans « Mes matchs », sur le tableau de bord ni dans le calendrier exporté vers votre téléphone — elle n'y serait qu'une rencontre contre personne. Vous la retrouvez sur l'écran **Résultats**, marquée « Bye ».

> La fédération ne donne aucune date à ces journées. L'application leur attribue **le jour le plus tôt où le reste de la division joue** cette journée-là. C'est une déduction raisonnable, pas une information officielle.

## Et si ça échoue en cours de route

L'import récupère **tout** auprès de la fédération avant d'écrire la moindre ligne. Si la fédération ne répond plus au milieu, **rien n'est écrit** et le message le dit. Il suffit de relancer plus tard : aucun demi-calendrier ne reste en base.
