<?php

declare(strict_types=1);

namespace App\Console\Commands\Payments;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\PaymentCredit;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use Illuminate\Console\Command;

/**
 * Rejoue la reprise des lignes de crédit.
 *
 * La migration de reprise tourne avant les seeders : en développement, une base
 * refaite à neuf repart donc avec des paiements semés que rien ne crédite, et
 * les écrans de trésorerie annoncent un passé vierge de tout rapprochement.
 * Cette commande donne le moyen de la rejouer après coup.
 *
 * Elle appelle la migration elle-même plutôt que d'en recopier la logique :
 * deux reprises qui divergent seraient pires qu'une seule.
 */
class BackfillPaymentCreditsCommand extends Command
{
    protected $description = 'Give historical payments the credit lines they never had';

    protected $signature = 'payments:backfill-credits
        {--check : Report inconsistencies without writing anything}
        {--reset : Undo a previous run before starting over}';

    public function handle(): int
    {
        if ($this->option('check')) {
            return $this->report();
        }

        $migration = require database_path('migrations/2026_09_21_102700_backfill_payment_credits.php');

        // La reprise est idempotente : elle laisse tel quel un paiement qui
        // porte déjà un crédit. C'est ce qu'on veut en production, et ce qui
        // empêche de corriger une passe ratée — d'où la remise à zéro.
        if ($this->option('reset')) {
            $migration->down();
            $this->line('Previous run undone.');
        }

        $before = PaymentCredit::count();

        $migration->up();

        $this->info(sprintf('%d credit line(s) created.', PaymentCredit::count() - $before));

        return self::SUCCESS;
    }

    /**
     * Constate sans rien écrire.
     *
     * Le test d'architecture garde le code source ; il ne dit rien de la
     * production, où la reprise a tourné une fois sur des données dont la forme
     * n'a jamais été observée. Ceci se lance là-bas.
     */
    private function report(): int
    {
        $orphans = Payment::where('amount_paid', '>', 0)->doesntHave('credits')->count();

        $drifted = Transaction::all()->filter(function (Transaction $transaction): bool {
            $credited = abs((int) $transaction->credits()->sum('amount'));

            return $credited !== (int) round(abs((float) $transaction->allocated_amount) * 100);
        })->count();

        $this->line(sprintf('%d payment(s) credited with no credit line', $orphans));
        $this->line(sprintf('%d transaction(s) whose mirror disagrees with its credits', $drifted));

        if ($orphans === 0 && $drifted === 0) {
            $this->info('Mirrors agree.');

            return self::SUCCESS;
        }

        $this->warn('Run without --check to rebuild them.');

        return self::FAILURE;
    }
}
