<?php

declare(strict_types=1);

namespace App\Console\Commands\Interclub;

use App\Data\Interclub\AfttSeasons;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Services\OfficialTournamentImporter;
use App\Domains\Competitions\Interclub\Services\TabtClient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Copies the official tournament matches of the club's members.
 *
 * Nightly for the active season. `--history` loads every season the interclub
 * match sheets already reach, and no further: a member's results page lays the
 * two side by side, and a season with interclub lines and no tournaments would
 * read as a player who never entered one.
 *
 * Each season is one call heavier than the whole quota, so history waits
 * between seasons rather than having the federation refuse the next one — and
 * whatever else runs from this address with it.
 */
class ImportOfficialTournamentsCommand extends Command
{
    protected $description = 'Import the official tournament matches of the club members from the federation';

    protected $signature = 'interclubs:import-tournaments
                            {--season= : Season name as the club writes it, e.g. 2026-2027. Defaults to the active one.}
                            {--history : Every season the interclub match sheets reach, one after the other.}';

    public function handle(TabtClient $client, OfficialTournamentImporter $importer): int
    {
        $seasons = $this->seasonsWanted();

        if ($seasons->isEmpty()) {
            $this->error($this->option('history')
                ? 'No season carries interclub match sheets yet; import those first.'
                : 'No such season in the club calendar.');

            return self::FAILURE;
        }

        try {
            $published = $client->seasons();
        } catch (Throwable $exception) {
            $this->error('The federation could not be reached: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $failed = false;

        foreach ($seasons->values() as $index => $season) {
            if ($index > 0) {
                Sleep::for(OfficialTournamentImporter::QUOTA_WAIT_MINUTES)->minutes();
            }

            $failed = ! $this->importSeason($season, $published, $client, $importer) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function afttSeasonNumber(AfttSeasons $published, string $name): ?int
    {
        if ($name === $published->currentSeasonName) {
            return $published->currentSeason;
        }

        $found = array_search($name, $published->all, strict: true);

        return $found === false ? null : (int) $found;
    }

    private function importSeason(Season $season, AfttSeasons $published, TabtClient $client, OfficialTournamentImporter $importer): bool
    {
        $afttSeason = $this->afttSeasonNumber($published, $season->name);

        if ($afttSeason === null) {
            $this->error(sprintf('The federation does not publish a season called "%s".', $season->name));

            return false;
        }

        $this->info(sprintf('Importing tournament matches for %s (federation season %d).', $season->name, $afttSeason));

        try {
            ['tally' => $tally, 'report' => $report] = $importer->import($season, $afttSeason, $client);
        } catch (Throwable $exception) {
            $this->error('The federation refused, nothing was changed: ' . $exception->getMessage());

            return false;
        }

        $this->table(['', ''], [
            ['Tournament matches written', $tally['matches_written']],
            ['Players', $tally['players']],
        ]);

        // A licence nobody holds: the matches are kept, and land on the member
        // as soon as the roster knows them.
        $this->namedList(
            $report['unknown_licences'],
            '%d licence(s) match no member — their matches are kept and wait for one:',
        );

        // Kept, never emptied: a short answer from the federation must not
        // read as a player who stopped playing.
        $this->namedList(
            $report['emptied_licences'],
            '%d player(s) came back with no tournament match although we hold some — kept as they were:',
        );

        return true;
    }

    /**
     * @param  array<string, string>  $names
     */
    private function namedList(array $names, string $heading): void
    {
        if ($names === []) {
            return;
        }

        $this->newLine();
        $this->warn(sprintf($heading, count($names)));

        foreach ($names as $licence => $name) {
            $this->line(sprintf('  %s — %s', $licence, $name));
        }
    }

    /**
     * @return Collection<int, Season>
     */
    private function seasonsWanted(): Collection
    {
        if ($this->option('history')) {
            return Season::query()
                ->whereIn('id', InterclubIndividualMatch::query()
                    ->join('interclubs', 'interclubs.id', '=', 'interclub_individual_matches.interclub_id')
                    ->select('interclubs.season_id'))
                ->orderBy('start_at')
                ->orderBy('id')
                ->get();
        }

        $season = $this->option('season')
            ? Season::where('name', $this->option('season'))->first()
            : Season::current();

        return collect($season === null ? [] : [$season]);
    }
}
