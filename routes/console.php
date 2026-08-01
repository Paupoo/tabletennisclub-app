<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\Feature;
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
Schedule::command('season:provision')
    ->yearlyOn(7, 1, '06:00')
    ->withoutOverlapping();

// Alerte les admins par mail synchrone quand le worker de queue semble mort
// (le scheduler, lui, continue de tourner).
Schedule::command('queue:check-health')
    ->hourly()
    ->withoutOverlapping()
    ->when(Feature::Supervision->enabled(...));
