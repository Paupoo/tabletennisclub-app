<?php

declare(strict_types=1);

namespace App\Console\Commands\Payments;

use App\Domains\ClubAdmin\Payment\Support\PaymentCoversBackfill;
use Illuminate\Console\Command;

/**
 * Tell the subscription payments made before `covers` existed what they bill.
 *
 * The migration that adds the column runs it once. Run it with --dry-run in
 * production beforehand to read what it will do: how many payments it can
 * place, and how many stay unknown.
 */
class BackfillPaymentCoversCommand extends Command
{
    protected $description = 'Rebuild what historical subscription payments bill (affiliation, training packs)';

    protected $signature = 'payments:backfill-covers
        {--dry-run : Report what would be rebuilt, write nothing}';

    public function handle(PaymentCoversBackfill $backfill): int
    {
        $report = $backfill->run((bool) $this->option('dry-run'));

        $this->line(sprintf('%d first payment(s): the affiliation and the packs taken with it', $report['affiliation']));
        $this->line(sprintf('%d later payment(s): the training packs added afterwards', $report['packs']));
        $this->line(sprintf('%d payment(s) left unknown: they keep the affiliation label', $report['unknown']));

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was written.');
        }

        return self::SUCCESS;
    }
}
