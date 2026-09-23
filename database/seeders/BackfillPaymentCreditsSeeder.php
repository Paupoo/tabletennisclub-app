<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\ClubAdmin\Payment\Models\PaymentCredit;
use Illuminate\Database\Seeder;

/**
 * Rend aux paiements semés les lignes de crédit qui les expliquent.
 *
 * `payments.amount_paid` est le miroir de {@see PaymentCredit}, et les seeders
 * l'écrivent en direct — c'est assumé, ils fabriquent des états variés sans
 * avoir à inventer une transaction pour chacun. Mais la reprise qui reconstruit
 * les crédits est une **migration** : elle tourne avant eux, sur une base vide.
 *
 * Résultat sans ce rattrapage : une base fraîche ouvre sur des paiements dont
 * le montant reçu n'est adossé à rien, et des transactions qui se disent non
 * rapprochées alors que leur argent est compté ailleurs.
 *
 * Appelle la migration plutôt que d'en recopier la logique : deux reprises qui
 * divergent seraient pires qu'une seule.
 */
class BackfillPaymentCreditsSeeder extends Seeder
{
    public function run(): void
    {
        $before = PaymentCredit::count();

        $migration = require database_path('migrations/2026_09_21_102700_backfill_payment_credits.php');
        $migration->up();

        $this->command?->info(sprintf('%d credit line(s) rebuilt.', PaymentCredit::count() - $before));
    }
}
