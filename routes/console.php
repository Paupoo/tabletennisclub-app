<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Communications\Models\CommunicationRecipient;
use App\Domains\Shared\Enums\Feature;
use App\Domains\Shared\ValueObjects\FiscalYear;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| Commandes en closure et planification des tâches.
|
| Le planning vit ici plutôt que dans withSchedule() : ce dernier n'enregistre
| ses tâches qu'à travers Artisan::starting(), donc hors d'une exécution
| console le Schedule reste vide — y compris pour les tests qui vérifient qu'un
| domaine coupé cesse d'être planifié. Ici le fichier est chargé avec les
| routes de commandes, et le planning lu est toujours celui qui tournera.
|
*/

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Chaque tâche est conditionnée au feature flag de son domaine. Sans ça, un
| domaine coupé en production continuerait d'écrire aux membres à propos d'une
| fonctionnalité qu'ils ne voient plus — pire que de l'avoir laissée allumée.
|
| withoutOverlapping() partout : les deux tâches horaires parcourent des listes
| d'attente et promeuvent le joueur suivant, donc une exécution encore en cours
| quand l'heure suivante démarre promouvrait et notifierait deux fois la même
| personne. onOneServer() n'est pas utilisé — il exige un cache verrouillable
| et n'apporte rien sur une machine unique.
*/

// Expiration des confirmations de liste d'attente (48 h) + inscriptions
// impayées au-delà de leur échéance + rappels de paiement.
Schedule::command('tournament:process-deadlines')
    ->hourly()
    ->withoutOverlapping()
    ->when(Feature::Tournaments->enabled(...));

// Expiration des offres de liste d'attente non confirmées (48 h) et promotion
// du suivant.
Schedule::command('training:process-deadlines')
    ->hourly()
    ->withoutOverlapping()
    ->when(Feature::Trainings->enabled(...));

// Clôture des inscriptions des tournois dont la date est passée.
Schedule::command('tournament:close-registrations')
    ->dailyAt('00:05')
    ->withoutOverlapping()
    ->when(Feature::Tournaments->enabled(...));

// Rappel hebdomadaire des remboursements au trésorier et au secrétaire.
Schedule::command('payment:send-refund-reminder')
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping()
    ->when(Feature::Treasury->enabled(...));

// Le 1er juillet, provisionne les deux saisons à venir (+1 et +2).
// Rejouable à tout moment — idempotent, ne crée que ce qui manque.
/*
 * An attestation carries the member's national register number, the one
 * identifier this application refuses to store in a column. Twelve months
 * covers the season and a wide margin for a lost envelope; after that the file
 * goes and only the record stays.
 */
Schedule::command('attestations:purge')
    ->dailyAt('03:20')
    ->withoutOverlapping();

/*
 * Notes de frais. Le digest part le dimanche soir, pour que la semaine du
 * trésorier commence avec la liste de ce qui attend. La purge, elle, tourne
 * même quand le domaine est coupé : éteindre la fonction ne doit pas prolonger
 * la vie des justificatifs d'une note jamais payée.
 */
Schedule::command('expense-reports:send-digest')
    ->weeklyOn(0, '19:00')
    ->withoutOverlapping()
    ->when(Feature::ExpenseReports->enabled(...));

Schedule::command('expense-reports:purge-files')
    ->dailyAt('03:30')
    ->withoutOverlapping();

// Le rappel d'archivage : chaque trimestre de l'exercice, et le 5 du mois qui
// suit sa clôture plutôt que le 1er — c'est le moment où le trésorier prépare
// les comptes pour les vérificateurs. Les mois se lisent sur le club à chaque
// passage (et non en dur dans le cron) : l'exercice peut commencer n'importe
// quel mois, et lire la base au chargement des routes casserait toute
// commande lancée avant les migrations.
Schedule::command('expense-reports:remind-archiving')
    ->monthlyOn(1, '08:00')
    ->withoutOverlapping()
    ->when(Feature::ExpenseReports->enabled(...))
    ->when(fn (): bool => in_array((now()->month - FiscalYear::startMonth() + 12) % 12, [3, 6, 9], true));

Schedule::command('expense-reports:remind-archiving --year-end')
    ->monthlyOn(5, '08:00')
    ->withoutOverlapping()
    ->when(Feature::ExpenseReports->enabled(...))
    ->when(fn (): bool => now()->month === FiscalYear::startMonth());

Schedule::command('financial-exports:prune')
    ->dailyAt('03:40')
    ->withoutOverlapping();

/*
 * Images put in an article body are filed as soon as they are inserted; the
 * ones no stored text points to any more go after a week.
 */
Schedule::command('articles:prune-content-images')
    ->dailyAt('03:50')
    ->withoutOverlapping();

/*
 * Communications: the addresses each one went to are personal data, pruned
 * two seasons after the sending. What the club said is kept.
 */
Schedule::command('model:prune', ['--model' => [CommunicationRecipient::class]])
    ->dailyAt('03:50')
    ->withoutOverlapping();

/*
 * Le calendrier de la fédération, relu toutes les heures en journée : un
 * forfait se publie parfois l'après-midi d'un match du soir. Ce qui a changé
 * est consigné et annoncé aux équipes — jamais la nuit, et jamais pour une
 * rencontre déjà commencée. Décidé le 2026-10-01.
 */
Schedule::command('interclubs:import-aftt')
    ->cron('5 7-22 * * *')
    ->withoutOverlapping()
    ->when(Feature::Interclubs->enabled(...));

/*
 * Les feuilles de match de la fédération : le score officiel de chaque
 * rencontre et le détail joueur par joueur.
 *
 * Tôt le matin plutôt qu'en soirée — les rencontres se jouent le vendredi soir
 * et le week-end, et les clubs encodent dans les heures ou les jours qui
 * suivent. Rejouable sans dommage : une feuille non encodée est ignorée, une
 * feuille déjà importée est corrigée sur place.
 */
Schedule::command('interclubs:import-results')
    ->dailyAt('05:40')
    ->withoutOverlapping()
    ->when(Feature::Interclubs->enabled(...));

/*
 * Les matches de tournois officiels des membres. Une nuit suffit : aucune
 * décision de capitaine n'en dépend. 04h30, loin des deux autres imports :
 * cet appel dépasse à lui seul le quota TabT de l'adresse, qui met quelques
 * minutes à se vider. Décidé le 2026-10-04.
 */
Schedule::command('interclubs:import-tournaments')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->when(Feature::Interclubs->enabled(...));

Schedule::command('season:provision')
    ->yearlyOn(7, 1, '06:00')
    ->withoutOverlapping();

// Alerte les admins par mail synchrone quand le worker de queue semble mort
// (le scheduler, lui, continue de tourner).
Schedule::command('queue:check-health')
    ->hourly()
    ->withoutOverlapping()
    ->when(Feature::Supervision->enabled(...));

// Problème 8 (validé le 2026-09-24) : le dimanche soir, la semaine de matchs est
// bouclée ; chaque capitaine reçoit la liste de ses compos pas encore envoyées.
Schedule::command('interclubs:remind-captains')
    ->weeklyOn(0, '18:00')
    ->withoutOverlapping()
    ->when(Feature::Interclubs->enabled(...));

// Réassort automatique (décidé le 2026-09-27) : le vendredi à 6 h 05, une fois la
// soirée du jeudi close — une journée d'exploitation finit à 6 h —, et avant le
// digest du samedi qui dit ce qui a bougé.
Schedule::command('bar:restocking-recalculate')
    ->weeklyOn(5, '06:05')
    ->withoutOverlapping()
    ->when(Feature::Bar->enabled(...));

// Réassort du bar (décidé le 2026-09-26) : le samedi à 10 h, le lendemain des
// matchs, magasins ouverts — et qui n'y va pas le samedi a la semaine devant lui.
Schedule::command('bar:restocking-digest')
    ->weeklyOn(6, '10:00')
    ->withoutOverlapping()
    ->when(Feature::Bar->enabled(...));

// Enquête annuelle des membres : invitation le jour de l'ouverture, une seule
// relance à mi-parcours, récapitulatif au comité le lendemain de la clôture.
// Chaque envoi est tamponné sur la campagne : la commande ne renvoie jamais
// deux fois.
Schedule::command('feedback:send-campaign-mailings')
    ->dailyAt('09:00')
    ->withoutOverlapping();
