<?php

declare(strict_types=1);

namespace App\Console\Commands\Payments;

use App\Support\Treasury\BankStatementFixture;
use Illuminate\Console\Command;

/**
 * Fabrique un relevé bancaire de démonstration accordé à la base du moment.
 *
 * Lecture seule — elle n'écrit rien en base, donc rien à protéger et rien à
 * défaire. Les situations qu'elle met en scène viennent des seeders ; un cas
 * introuvable est annoncé, jamais inventé.
 */
class GenerateBankStatementDemoCommand extends Command
{
    protected $description = 'Write a demo bank statement matching the current database, plus a manifest of what to check';

    protected $signature = 'treasury:demo-statement
        {--output= : Where to write the two files (default: storage/app/seeders)}
        {--date= : Freeze the window on this end date, to replay a statement identically}';

    public function handle(): int
    {
        $fixture = new BankStatementFixture;
        $result = $fixture->build($this->option('date'));

        $directory = $this->option('output') ?: storage_path('app/seeders');
        $paths = $fixture->write($result, $directory);

        foreach ($result->covered as $case) {
            $this->line('  <fg=green>✓</> ' . BankStatementFixture::CASES[$case]);
        }

        foreach ($result->skipped as $case => $why) {
            $this->line('  <fg=yellow>✗</> ' . BankStatementFixture::CASES[$case]);
            $this->line('    <fg=gray>' . $why . '</>');
        }

        $this->newLine();
        $this->line('CSV       : ' . $paths['csv']);
        $this->line('Manifeste : ' . $paths['manifest']);
        $this->newLine();
        $this->info(sprintf(
            '%d lignes, %d cas sur %d.',
            $result->rowCount,
            count($result->covered),
            count(BankStatementFixture::CASES),
        ));

        // Un cas manquant n'est pas une erreur d'exécution : le fichier est
        // écrit et utilisable. Mais l'appelant doit pouvoir s'en apercevoir.
        return $result->skipped === [] ? self::SUCCESS : self::INVALID;
    }
}
