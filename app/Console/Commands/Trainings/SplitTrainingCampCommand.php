<?php

declare(strict_types=1);

namespace App\Console\Commands\Trainings;

use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingCampSplit;
use Illuminate\Console\Command;

/**
 * Turn a pack encoded before stages existed into a stage.
 *
 * Run it with --dry-run first and read the table: the clean lines will move,
 * the others are listed for the treasurer with the reason they stay.
 */
class SplitTrainingCampCommand extends Command
{
    protected $description = 'Turn an existing training pack into a training camp invoiced separately from the affiliation';

    protected $signature = 'trainings:split-camp
        {pack : The id of the training pack}
        {--dry-run : Report what would be moved, write nothing}';

    public function handle(TrainingCampSplit $split): int
    {
        $pack = TrainingPack::find((int) $this->argument('pack'));

        if (! $pack instanceof TrainingPack) {
            $this->error('No training pack with this id.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $report = $split->run($pack, $dryRun);

        $this->info(sprintf('%s — %d line(s)', $pack->name, count($report)));

        $this->table(
            ['Member', 'Status', 'Amount', 'Outcome'],
            array_map(fn (array $row): array => [
                $row['member'],
                $row['status'],
                number_format($row['amount'], 2, ',', ' ') . ' €',
                $this->describe($row['outcome']),
            ], $report),
        );

        $left = array_filter($report, fn (array $row): bool => in_array($row['outcome'], [TrainingCampSplit::FIRST_INVOICE, TrainingCampSplit::PRICE_MOVED], true));

        if ($left !== []) {
            $this->warn(sprintf('%d line(s) stay billed in the affiliation: the treasurer settles them one by one.', count($left)));
        }

        $this->line($dryRun ? 'Dry run: nothing was written.' : 'Done.');

        return self::SUCCESS;
    }

    private function describe(string $outcome): string
    {
        return match ($outcome) {
            TrainingCampSplit::CLEAN => 'moved: its payment now belongs to the stage',
            TrainingCampSplit::NOTHING_BILLED => 'moved: nothing was billed yet',
            TrainingCampSplit::FIRST_INVOICE => 'stays: one payment covers the fee and the stage',
            TrainingCampSplit::PRICE_MOVED => 'stays: the stage changed another price (multi-pack discount)',
            default => $outcome,
        };
    }
}
