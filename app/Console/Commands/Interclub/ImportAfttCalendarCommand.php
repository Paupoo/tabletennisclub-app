<?php

declare(strict_types=1);

namespace App\Console\Commands\Interclub;

use App\Data\Interclub\AfttSeasons;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubResult;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\AfttCalendarImporter;
use App\Domains\Competitions\Interclub\Services\TabtClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Loads the federation's interclub calendar into the club's own season.
 *
 * Run without options it is safe to repeat as often as you like: fixtures are
 * matched on the federation's own identifier and corrected in place, so a match
 * that moves keeps every availability answer already recorded against it.
 *
 * `--fresh` is the September operation and nothing else. It empties the season
 * first, which takes the captains and the team rosters with it.
 */
class ImportAfttCalendarCommand extends Command
{
    protected $description = 'Import the AFTT interclub calendar for a season';

    protected $signature = 'interclubs:import-aftt
                            {--season= : Season name as the club writes it, e.g. 2026-2027. Defaults to the one the federation calls current.}
                            {--fresh : Empty the season and rebuild it. Destroys captains and rosters.}
                            {--force : Skip the confirmation, and override the refusal to destroy member data.}';

    public function handle(TabtClient $client, AfttCalendarImporter $importer): int
    {
        $clubCode = (string) Club::query()->where('is_own_club', true)->value('licence');

        if ($clubCode === '') {
            $this->error('Our own club has no federation licence. Set it before importing.');

            return self::FAILURE;
        }

        try {
            $published = $client->seasons();
        } catch (Throwable $e) {
            $this->error('The federation could not be reached: ' . $e->getMessage());

            return self::FAILURE;
        }

        $seasonName = (string) ($this->option('season') ?? '') !== ''
            ? (string) $this->option('season')
            : $published->currentSeasonName;

        $afttSeason = $this->afttSeasonNumber($published, $seasonName);

        if ($afttSeason === null) {
            $this->error(sprintf('The federation does not publish a season called "%s".', $seasonName));

            return self::FAILURE;
        }

        $season = Season::where('name', $seasonName)->first();

        if (! $season instanceof Season) {
            $this->error(sprintf(
                'The federation is on "%s", and the club has no season by that name. Create it first.',
                $seasonName,
            ));

            return self::FAILURE;
        }

        $fresh = (bool) $this->option('fresh');

        if ($fresh && ! $this->mayRebuild($season)) {
            return self::FAILURE;
        }

        $this->info(sprintf('Importing %s for %s from the federation…', $seasonName, $clubCode));

        try {
            $report = $importer->import($season, $afttSeason, $clubCode, $fresh);
        } catch (Throwable $e) {
            $this->error('Nothing was written. The federation failed mid-import: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->summarise($report->created_count, $report->updated_count, $report->unchanged_count,
            $report->deleted_count, $report->skipped_count, $report->changes ?? []);

        return self::SUCCESS;
    }

    /**
     * Translate a season name into the federation's internal index.
     *
     * The name is what the club says and what its own Season rows are called;
     * the index is an internal counter nobody would recognise. Matching on the
     * name is also what keeps the default self-maintaining every August.
     */
    private function afttSeasonNumber(AfttSeasons $published, string $name): ?int
    {
        if ($name === $published->currentSeasonName) {
            return $published->currentSeason;
        }

        $found = array_search($name, $published->all, strict: true);

        return $found === false ? null : (int) $found;
    }

    /**
     * Decide whether a season may be emptied, and say plainly what that costs.
     *
     * The refusal only fires when something in the season carries member data, so
     * in September it never gets in the way. The day it does speak up, it is
     * right: the federation can restate every fixture, and can restate none of
     * the answers, selections or results recorded against them.
     */
    private function mayRebuild(Season $season): bool
    {
        $fixtures = Interclub::where('season_id', $season->id);

        $answers = DB::table('interclub_user')
            ->whereIn('interclub_id', (clone $fixtures)->select('id'))
            ->count();

        $results = InterclubResult::where('season_id', $season->id)
            ->where(fn ($query) => $query->whereNotNull('result')->orWhereNotNull('score'))
            ->count();

        $rosters = DB::table('team_user')
            ->whereIn('team_id', Team::where('season_id', $season->id)->select('id'))
            ->count();

        $captains = Team::where('season_id', $season->id)->whereNotNull('captain_id')->count();

        $this->warn(sprintf(
            'Rebuilding %s deletes %d fixtures, %d teams, %d captain assignments and %d roster entries.',
            $season->name,
            (clone $fixtures)->count(),
            Team::where('season_id', $season->id)->count(),
            $captains,
            $rosters,
        ));

        if ($answers > 0 || $results > 0) {
            if (! $this->option('force')) {
                $this->error(sprintf(
                    'Refusing: %d availability answers and %d recorded results would be lost. Re-run with --force if that is really what you want.',
                    $answers,
                    $results,
                ));

                return false;
            }

            $this->warn(sprintf('Destroying %d availability answers and %d recorded results, as instructed.', $answers, $results));
        }

        if ($this->option('force')) {
            return true;
        }

        return $this->confirm('Delete them and rebuild from the federation?', false);
    }

    /**
     * @param  array<string, array<int, string>>  $changes
     */
    private function summarise(int $created, int $updated, int $unchanged, int $deleted, int $skipped, array $changes): void
    {
        $this->newLine();
        $this->table(
            ['Created', 'Moved', 'Unchanged', 'Removed', 'Refused'],
            [[$created, $updated, $unchanged, $deleted, $skipped]],
        );

        foreach ($changes['refused_divisions'] ?? [] as $division) {
            $this->warn('Refused, category or level we do not model: ' . $division);
        }

        foreach ($changes['moved'] ?? [] as $matchId) {
            $this->line('Moved: ' . $matchId);
        }

        foreach ($changes['kept_orphans'] ?? [] as $matchId) {
            $this->warn(sprintf(
                'No longer on the federation calendar but kept, because members answered on it: %s',
                $matchId,
            ));
        }
    }
}
