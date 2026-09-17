<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Data\Interclub\AfttMatchSheet;
use App\Data\Interclub\AfttSheetPlayer;
use App\Data\Interclub\AfttSheetResult;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\InterclubResult;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\InterclubResultEnum;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Turns federation match sheets into our own rows.
 *
 * The counterpart of {@see AfttCalendarImporter}, and deliberately its
 * opposite in temperament: the calendar import is a September operation that
 * rebuilds a season, this one runs every morning and must be safe to run twice
 * in a row on the same data. Every write is keyed and idempotent.
 *
 * It never invents a fixture. A sheet is only ever matched to a row we already
 * have, through `interclubs.aftt_match_id`, which the calendar import wrote; a
 * sheet with no local counterpart is counted and skipped, because a fixture we
 * do not know is a fixture no member of ours played.
 */
class AfttResultsImporter
{
    /** @var array<string, array<int, string>> */
    private array $report = [
        'divisions_failed' => [],
        'unknown_licences' => [],
    ];

    /** @var array<string, int> */
    private array $tally = [
        'fixtures_updated' => 0,
        'individual_matches' => 0,
        'sheets_pending' => 0,
        'sheets_unknown' => 0,
    ];

    /**
     * Import every sheet of every division our club plays in this season.
     *
     * @return array{tally: array<string, int>, report: array<string, array<int, string>>}
     */
    public function import(Season $season, int $afttSeason, TabtClient $client): array
    {
        $divisions = League::where('season_id', $season->id)
            ->whereNotNull('aftt_division_id')
            ->pluck('aftt_division_id')
            ->unique()
            ->values();

        foreach ($divisions as $divisionId) {
            try {
                $sheets = $client->divisionMatchSheets((int) $divisionId, $afttSeason);
            } catch (Throwable $exception) {
                // One division the federation will not serve must not cost us
                // the other nine. Named in the report, and the command's exit
                // code reflects it.
                $this->report['divisions_failed'][] = $divisionId . ' — ' . $exception->getMessage();

                continue;
            }

            foreach ($sheets as $sheet) {
                $this->importSheet($sheet);
            }
        }

        return ['tally' => $this->tally, 'report' => $this->report];
    }

    /**
     * Our side's players, keyed by licence index.
     *
     * @param  array<int, AfttSheetPlayer>  $players
     * @return array<string, AfttSheetPlayer>
     */
    private function byIndex(array $players): array
    {
        $byIndex = [];

        foreach ($players as $player) {
            $byIndex[$player->uniqueIndex] = $player;
        }

        return $byIndex;
    }

    private function importSheet(AfttMatchSheet $sheet): void
    {
        $interclub = Interclub::with(['visitedTeam.club', 'visitingTeam.club'])
            ->where('aftt_match_id', $sheet->matchId)
            ->first();

        if ($interclub === null) {
            $this->tally['sheets_unknown']++;

            return;
        }

        // An empty shell: the fixture has a date and nothing else yet. Writing
        // it would erase a score a captain has typed in the meantime.
        if (! $sheet->detailsCreated) {
            $this->tally['sheets_pending']++;

            return;
        }

        $weAreHome = $interclub->isHome();

        DB::transaction(function () use ($interclub, $sheet, $weAreHome): void {
            $this->writeTeamScore($interclub, $sheet, $weAreHome);
            $this->writeIndividualMatches($interclub, $sheet, $weAreHome);
        });

        $this->tally['fixtures_updated']++;
    }

    /**
     * The member holding this licence, if the club roster knows it.
     */
    private function memberFor(?string $licence): ?User
    {
        return $licence === null ? null : User::where('licence', $licence)->first();
    }

    /**
     * The verdict our side earned, from the score our side reads.
     */
    private function resultFrom(?int $us, ?int $them, AfttMatchSheet $sheet, bool $weAreHome): ?InterclubResultEnum
    {
        $weForfeited = $weAreHome ? $sheet->isHomeForfeited : $sheet->isAwayForfeited;
        $theyForfeited = $weAreHome ? $sheet->isAwayForfeited : $sheet->isHomeForfeited;

        if ($weForfeited) {
            return InterclubResultEnum::FORFEIT_LOSS;
        }

        if ($theyForfeited) {
            return InterclubResultEnum::FORFEIT_WIN;
        }

        if ($us === null || $them === null) {
            return null;
        }

        return match (true) {
            $us > $them => InterclubResultEnum::WIN,
            $us < $them => InterclubResultEnum::LOSS,
            default => InterclubResultEnum::DRAW,
        };
    }

    /**
     * One individual match, turned round so that "our" always means ours.
     *
     * @return array<string, mixed>|null
     */
    private function rowFor(AfttSheetResult $result, AfttMatchSheet $sheet, bool $weAreHome): ?array
    {
        $ourIndex = $weAreHome ? $result->homeUniqueIndex : $result->awayUniqueIndex;
        $theirIndex = $weAreHome ? $result->awayUniqueIndex : $result->homeUniqueIndex;
        $ourSets = $weAreHome ? $result->homeSetCount : $result->awaySetCount;
        $theirSets = $weAreHome ? $result->awaySetCount : $result->homeSetCount;

        $homeWon = $result->homeWon();

        if ($homeWon === null) {
            // Neither a set score nor a forfeit: the line says nothing at all,
            // and a row claiming a winner would be an invention.
            return null;
        }

        $players = $this->byIndex($weAreHome ? $sheet->homePlayers : $sheet->awayPlayers);
        $opponents = $this->byIndex($weAreHome ? $sheet->awayPlayers : $sheet->homePlayers);

        $ourPlayer = $ourIndex === null ? null : ($players[$ourIndex] ?? null);
        $theirPlayer = $theirIndex === null ? null : ($opponents[$theirIndex] ?? null);
        $member = $this->memberFor($ourIndex);

        if ($ourIndex !== null && $member === null) {
            $this->report['unknown_licences'][] = $ourIndex
                . ' — ' . ($ourPlayer?->fullName() ?? '?')
                . ' (' . $sheet->matchId . ')';
        }

        return [
            'is_double' => $result->isDouble(),
            'is_forfeit' => $result->isHomeForfeited || $result->isAwayForfeited,
            'opponent_licence' => $theirIndex,
            'opponent_name' => $theirPlayer?->fullName(),
            'opponent_ranking' => $theirPlayer?->ranking,
            'our_player_licence' => $member === null ? $ourIndex : null,
            'our_player_name' => $member === null ? $ourPlayer?->fullName() : null,
            'our_sets' => $ourSets,
            'their_sets' => $theirSets,
            'user_id' => $member?->id,
            'we_won' => $weAreHome ? $homeWon : ! $homeWon,
        ];
    }

    private function writeIndividualMatches(Interclub $interclub, AfttMatchSheet $sheet, bool $weAreHome): void
    {
        foreach ($sheet->results as $result) {
            $row = $this->rowFor($result, $sheet, $weAreHome);

            if ($row === null) {
                continue;
            }

            InterclubIndividualMatch::updateOrCreate(
                ['interclub_id' => $interclub->id, 'position' => $result->position],
                $row,
            );

            $this->tally['individual_matches']++;
        }
    }

    /**
     * The official score, written where the application keeps a score.
     *
     * `interclub_results` is the one home for it, and the federation overrules
     * the captain: their screen exists for the days between the match and its
     * encoding, and what they typed was always provisional.
     */
    private function writeTeamScore(Interclub $interclub, AfttMatchSheet $sheet, bool $weAreHome): void
    {
        $matchResult = InterclubResult::where('interclub_id', $interclub->id)->first();

        if ($matchResult === null) {
            return;
        }

        $us = $them = null;

        if ($sheet->score !== null && str_contains($sheet->score, '-')) {
            [$home, $away] = array_map(intval(...), explode('-', $sheet->score, 2));
            [$us, $them] = $weAreHome ? [$home, $away] : [$away, $home];
        }

        $matchResult->update([
            // Stored home-first, like everything else that reads this column.
            'score' => $sheet->score,
            'result' => $this->resultFrom($us, $them, $sheet, $weAreHome)?->value,
        ]);
    }
}
