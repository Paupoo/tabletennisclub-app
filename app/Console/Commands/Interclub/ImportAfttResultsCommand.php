<?php

declare(strict_types=1);

namespace App\Console\Commands\Interclub;

use App\Data\Interclub\AfttSeasons;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Services\AfttResultsImporter;
use App\Domains\Competitions\Interclub\Services\TabtClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pulls the federation's match sheets into our own rows.
 *
 * Scheduled daily, unlike the calendar import which is a September operation
 * run by hand. Nothing here destroys: a sheet the clubs have not encoded is
 * skipped rather than written as emptiness, and a fixture the federation does
 * not know about is left untouched.
 *
 * The exit code is not cosmetic. A division the federation refuses to serve
 * makes the run fail so a scheduler notices, while the other divisions are
 * still imported — losing nine because of one would be the worse outcome.
 */
class ImportAfttResultsCommand extends Command
{
    protected $description = 'Import AFTT interclub match sheets: team scores and individual results';

    protected $signature = 'interclubs:import-results
                            {--season= : Season name as the club writes it, e.g. 2026-2027. Defaults to the active one.}';

    public function handle(TabtClient $client, AfttResultsImporter $importer): int
    {
        if (! $this->schemaIsReady()) {
            return self::FAILURE;
        }

        $season = $this->option('season')
            ? Season::where('name', $this->option('season'))->first()
            : Season::current();

        if ($season === null) {
            $this->error('No such season in the club calendar.');

            return self::FAILURE;
        }

        try {
            $published = $client->seasons();
        } catch (Throwable $exception) {
            $this->error('The federation could not be reached: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $afttSeason = $this->afttSeasonNumber($published, $season->name);

        if ($afttSeason === null) {
            $this->error(sprintf('The federation does not publish a season called "%s".', $season->name));

            return self::FAILURE;
        }

        $this->info(sprintf('Importing match sheets for %s (federation season %d).', $season->name, $afttSeason));

        ['tally' => $tally, 'report' => $report] = $importer->import($season, $afttSeason, $client);

        /*
         * A run that read nothing looked exactly like a run that found nothing
         * to do: four zeroes and no explanation. The divisions of a season only
         * carry a federation id once the calendar has been imported, so a
         * season that never was reports success while doing nothing at all.
         */
        if ($tally['divisions_read'] === 0) {
            $this->warn(sprintf(
                'No division of %s carries a federation id, so there was nothing to ask for.',
                $season->name,
            ));
            $this->line('Run `interclubs:import-aftt --season=' . $season->name . '` first.');

            return self::FAILURE;
        }

        $this->table(['', ''], [
            ['Fixtures updated', $tally['fixtures_updated']],
            ['Individual matches written', $tally['individual_matches']],
            ['Sheets not encoded yet', $tally['sheets_pending']],
            ['Sheets with no fixture of ours', $tally['sheets_unknown']],
            ['Final positions written', $tally['positions_written']],
            ['Divisions read', $tally['divisions_read']],
        ]);

        /*
         * The one failure a screen would never show. A licence on a sheet of
         * ours that matches no member means the individual results are filed
         * against nobody: the tie reads correctly, and the player's own history
         * silently loses a match. Naming them is the only way anyone finds out.
         */
        $unknown = $report['unknown_licences'];

        if ($unknown !== []) {
            $this->newLine();
            $this->warn(sprintf(
                '%d licence(s) on our side match no member — their results are filed against nobody:',
                count($unknown),
            ));

            foreach ($unknown as $licence => $name) {
                $this->line(sprintf('  %s — %s', $licence, $name));
            }

            $this->line('Fix the licence on the member record, then run this command again.');
        }

        if ($report['divisions_failed'] !== []) {
            $this->newLine();
            $this->error('Divisions the federation would not serve:');

            foreach ($report['divisions_failed'] as $line) {
                $this->line('  ' . $line);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function afttSeasonNumber(AfttSeasons $published, string $name): ?int
    {
        if ($name === $published->currentSeasonName) {
            return $published->currentSeason;
        }

        $found = array_search($name, $published->all, strict: true);

        return $found === false ? null : (int) $found;
    }

    private function schemaIsReady(): bool
    {
        $missing = ! Schema::hasTable('interclub_individual_matches')
            || ! Schema::hasColumn('interclubs', 'aftt_match_id')
            || ! Schema::hasColumn('leagues', 'aftt_division_id');

        if ($missing) {
            $this->error('The database is missing the interclub import tables. Run `php artisan migrate` first.');
        }

        return ! $missing;
    }
}
