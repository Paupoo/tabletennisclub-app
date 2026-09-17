<?php

declare(strict_types=1);

namespace App\Console\Commands\Interclub;

use App\Data\Interclub\AfttSeasons;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Services\AfttFixtureMatcher;
use App\Domains\Competitions\Interclub\Services\AfttResultsImporter;
use App\Domains\Competitions\Interclub\Services\TabtClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Loads the club's past seasons from the federation, once, at setup.
 *
 * Three commands' work in the order that makes it safe:
 *
 *   1. create the club season if it is missing;
 *   2. give a federation identifier to fixtures the club typed in by hand;
 *   3. import the calendar, then the match sheets.
 *
 * Step 2 is the reason this exists rather than a note telling somebody to run
 * the other two with `--season`. The calendar import keys on
 * `interclubs.aftt_match_id`; a season recorded before the club ever spoke to
 * TabT carries none, so the import would not recognise a single fixture, would
 * build a second season beside the first, and would say nothing about it. The
 * alternative on offer was `--fresh`, which solves it by destroying the
 * captains, line-ups and availability answers of the season it rebuilds.
 *
 * Read-only until told otherwise: `--dry-run` reports what each step would do
 * and writes nothing, which is the only sane way to point a command at ten
 * seasons of history for the first time.
 */
class ImportAfttHistoryCommand extends Command
{
    protected $description = 'Load past interclub seasons from the federation: fixtures, scores and match sheets';

    protected $signature = 'interclubs:import-history
                            {--from= : Earliest season to load, as the club writes it, e.g. 2014-2015. Required.}
                            {--to= : Latest season to load. Defaults to the one before the active season.}
                            {--dry-run : Report what would happen and write nothing.}';

    public function handle(TabtClient $client, AfttFixtureMatcher $matcher, AfttResultsImporter $results): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $clubCode = (string) Club::query()->where('is_own_club', true)->value('licence');

        if ($clubCode === '') {
            $this->error('Our own club has no federation licence. Set it before importing.');

            return self::FAILURE;
        }

        try {
            $published = $client->seasons();
        } catch (Throwable $exception) {
            $this->error('The federation could not be reached: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $wanted = $this->seasonsWanted($published);

        if ($wanted === []) {
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Dry run — nothing will be written.');
        }

        $this->info(sprintf('Loading %d season(s) for %s.', count($wanted), $clubCode));
        $this->newLine();

        $failed = false;

        foreach ($wanted as $afttSeason => $name) {
            $failed = $this->loadSeason($name, $afttSeason, $clubCode, $client, $matcher, $results, $dryRun) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * The club's own season row, created if this is the first time we look that
     * far back.
     *
     * September to June, inactive, closed to affiliations: a season that ended
     * years ago must never be mistaken for one members can sign up to.
     */
    private function ensureSeason(string $name, bool $dryRun): ?Season
    {
        $season = Season::where('name', $name)->first();

        if ($season instanceof Season) {
            return $season;
        }

        [$startYear] = explode('-', $name);

        if (! ctype_digit($startYear)) {
            $this->error(sprintf('  "%s" is not a season name this command can read.', $name));

            return null;
        }

        $this->line(sprintf('  Season %s does not exist yet — creating it.', $name));

        if ($dryRun) {
            return null;
        }

        return Season::create([
            'name' => $name,
            'start_at' => CarbonImmutable::create((int) $startYear, 9, 1),
            'end_at' => CarbonImmutable::create((int) $startYear + 1, 6, 30),
            'is_active' => false,
            'affiliations_open' => false,
        ]);
    }

    /**
     * One season, end to end. Returns true when something went wrong.
     */
    private function loadSeason(
        string $name,
        int $afttSeason,
        string $clubCode,
        TabtClient $client,
        AfttFixtureMatcher $matcher,
        AfttResultsImporter $results,
        bool $dryRun,
    ): bool {
        $this->components->info($name);

        $season = $this->ensureSeason($name, $dryRun);

        if (! $season instanceof Season) {
            // In a dry run a missing season is expected and not a failure: the
            // steps below simply have nothing to read yet.
            return ! $dryRun;
        }

        try {
            ['linked' => $linked, 'linked_ids' => $linkedIds, 'report' => $linkReport] = $matcher->link(
                $season, $afttSeason, $clubCode, $client, $dryRun,
            );
        } catch (Throwable $exception) {
            $this->error('  Could not pair existing fixtures: ' . $exception->getMessage());

            return true;
        }

        $this->line(sprintf('  Existing fixtures given a federation id: %d', $linked));

        foreach (['unmatched' => 'no local fixture', 'ambiguous' => 'more than one candidate'] as $key => $why) {
            $rows = $linkReport[$key];

            if ($rows === []) {
                continue;
            }

            $this->line(sprintf('  %d federation fixture(s) with %s — the calendar import will create them:', count($rows), $why));

            foreach (array_slice($rows, 0, 5) as $row) {
                $this->line('    ' . $row);
            }

            if (count($rows) > 5) {
                $this->line(sprintf('    … and %d more', count($rows) - 5));
            }
        }

        $this->warnAboutLeftovers($season, $linkedIds);

        if ($dryRun) {
            $this->line('  Calendar and match sheets: skipped in a dry run.');
            $this->newLine();

            return false;
        }

        $calendar = $this->call('interclubs:import-aftt', ['--season' => $name]);

        if ($calendar !== self::SUCCESS) {
            $this->error('  The calendar import failed; match sheets were not attempted.');

            return true;
        }

        ['tally' => $tally, 'report' => $report] = $results->import($season, $afttSeason, $client);

        $this->line(sprintf(
            '  Sheets: %d fixture(s) updated, %d individual match(es), %d not encoded, %d not ours',
            $tally['fixtures_updated'],
            $tally['individual_matches'],
            $tally['sheets_pending'],
            $tally['sheets_unknown'],
        ));
        $this->line(sprintf('  Final positions written: %d', $tally['positions_written']));

        $unknown = array_unique($report['unknown_licences']);

        if ($unknown !== []) {
            $this->warn(sprintf('  %d licence(s) on our side match no member:', count($unknown)));

            foreach (array_slice($unknown, 0, 10) as $line) {
                $this->line('    ' . $line);
            }

            if (count($unknown) > 10) {
                $this->line(sprintf('    … and %d more', count($unknown) - 10));
            }
        }

        $this->newLine();

        return $report['divisions_failed'] !== [];
    }

    /**
     * The seasons to load, oldest first, keyed by the federation's own index.
     *
     * @return array<int, string>
     */
    private function seasonsWanted(AfttSeasons $published): array
    {
        $from = (string) ($this->option('from') ?? '');

        if ($from === '') {
            $this->error('Say how far back to go, e.g. --from=2014-2015.');

            return [];
        }

        $all = $published->all;
        ksort($all);

        $fromIndex = array_search($from, $all, strict: true);

        if ($fromIndex === false) {
            $this->error(sprintf('The federation does not publish a season called "%s".', $from));

            return [];
        }

        // Default upper bound: everything before the season the club is
        // currently playing, which the ordinary nightly import already keeps.
        $to = (string) ($this->option('to') ?? '');
        $toIndex = $to === '' ? $published->currentSeason - 1 : array_search($to, $all, strict: true);

        if ($toIndex === false) {
            $this->error(sprintf('The federation does not publish a season called "%s".', $to));

            return [];
        }

        if ($toIndex < $fromIndex) {
            $this->error('The --to season is earlier than the --from season.');

            return [];
        }

        return array_filter(
            $all,
            fn (int $index): bool => $index >= (int) $fromIndex && $index <= (int) $toIndex,
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Local fixtures the pairing could not claim, which is the dangerous half.
     *
     * A federation fixture with no counterpart is harmless: the calendar import
     * creates it, which is what should happen to a match the club never wrote
     * down. A *local* fixture with no federation id is the opposite — nothing
     * will ever touch it again. It will not be corrected, it will not be swept
     * away with the orphans (that sweep only looks at rows that carry an id),
     * and it will sit in the season next to the imported one, with whatever
     * score somebody typed, looking exactly like a real match.
     *
     * Reported rather than deleted: these are rows a human entered, and a
     * command that quietly removes them is a command nobody should run against
     * ten seasons at once.
     */
    /**
     * @param  array<int, int>  $justPaired
     */
    private function warnAboutLeftovers(Season $season, array $justPaired): void
    {
        $leftovers = Interclub::with(['visitedTeam.club', 'visitingTeam.club'])
            ->where('season_id', $season->id)
            ->whereNull('aftt_match_id')
            // A dry run has written nothing, so the rows it decided to pair are
            // still null here and would otherwise be reported as leftovers.
            ->whereNotIn('id', $justPaired)
            ->orderBy('start_date_time')
            ->get();

        if ($leftovers->isEmpty()) {
            return;
        }

        $this->warn(sprintf(
            '  %d local fixture(s) still carry no federation id. They will be left beside the imported ones:',
            $leftovers->count(),
        ));

        foreach ($leftovers->take(5) as $fixture) {
            $this->line(sprintf(
                '    #%d %s — %s vs %s',
                $fixture->id,
                $fixture->start_date_time?->format('d/m/Y') ?? '?',
                $fixture->visitedTeam?->fullName() ?? '?',
                $fixture->visitingTeam?->fullName() ?? '?',
            ));
        }

        if ($leftovers->count() > 5) {
            $this->line(sprintf('    … and %d more', $leftovers->count() - 5));
        }

        $this->line('    Check them before running without --dry-run; they are duplicates waiting to happen.');
    }
}
