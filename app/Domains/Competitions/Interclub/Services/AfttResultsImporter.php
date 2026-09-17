<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Data\Interclub\AfttMatchSheet;
use App\Data\Interclub\AfttRanking;
use App\Data\Interclub\AfttSheetPlayer;
use App\Data\Interclub\AfttSheetResult;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\InterclubResult;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
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
        'divisions_read' => 0,
        'fixtures_updated' => 0,
        'individual_matches' => 0,
        'positions_written' => 0,
        'sheets_pending' => 0,
        'sheets_unknown' => 0,
    ];

    /**
     * Import every sheet of every division our club plays in this season.
     *
     * @return array{tally: array<string, int>, report: array{divisions_failed: array<int, string>, unknown_licences: array<string, string>}}
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

            $this->tally['divisions_read']++;

            foreach ($sheets as $sheet) {
                $this->importSheet($season, $sheet);
            }

            $this->importRanking($season, (int) $divisionId, $afttSeason, $client);
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

    /**
     * Where our teams finished in this division.
     *
     * Writes the same `teams.final_position` the results screen writes, in the
     * same words a captain would use — this is not a second pipeline, it is the
     * federation filling in a field nobody is going to type for ten past
     * seasons. Like the score, the federation overrules what was typed: its
     * table is the official one.
     *
     * A division the federation will not rank is not a failure worth stopping
     * for. The scores are already in by this point, and a missing final
     * position leaves the column exactly as it was.
     */
    private function importRanking(Season $season, int $divisionId, int $afttSeason, TabtClient $client): void
    {
        try {
            $ranking = $client->divisionRanking($divisionId, $afttSeason);
        } catch (Throwable) {
            return;
        }

        $ourClub = Club::query()->where('is_own_club', true)->value('licence');

        foreach ($ranking as $entry) {
            if ($entry->teamClub !== $ourClub) {
                continue;
            }

            $this->writeFinalPosition($season, $divisionId, $entry);
        }
    }

    private function importSheet(Season $season, AfttMatchSheet $sheet): void
    {
        // Scoped to the season, because the unique index is on the pair. The
        // federation's identifiers carry a division and a round, both of which
        // it renumbers from year to year, so the same string can name a fixture
        // in two seasons — and an unscoped lookup would file last year's sheet
        // against this year's evening.
        $interclub = Interclub::with(['visitedTeam.club', 'visitingTeam.club'])
            ->where('season_id', $season->id)
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

    private function memberFor(?string $licence): ?User
    {
        return $licence === null ? null : User::where('licence', $licence)->first();
    }

    /**
     * The member holding this licence, if the club roster knows it.
     */
    /**
     * The club's own wording, so an imported position is indistinguishable from
     * a typed one: "1ère place", "3ème place".
     */
    private function positionLabel(int $position): string
    {
        return $position === 1 ? '1ère place' : $position . 'ème place';
    }

    /**
     * The verdict our side earned, from the score our side reads.
     */
    /**
     * The verdict our side earned, from the score our side reads.
     *
     * Which side failed to turn up comes from the sheet's own flags, never from
     * the score's suffix: the flags are unambiguous and the suffix is not. What
     * the suffix does carry, and the flags do not, is the difference between
     * forfeiting one tie ("ff") and withdrawing from the division outright
     * ("fg") — two different cases the club's own enum has always had.
     *
     * A general forfeit flags both sides, so ours is tested first: we are one of
     * the two, and it is our record being written.
     */
    private function resultFrom(?int $us, ?int $them, AfttMatchSheet $sheet, bool $weAreHome, string $marker = ''): ?InterclubResultEnum
    {
        $weForfeited = $weAreHome ? $sheet->isHomeForfeited : $sheet->isAwayForfeited;
        $theyForfeited = $weAreHome ? $sheet->isAwayForfeited : $sheet->isHomeForfeited;
        $isWithdrawal = str_contains($marker, 'fg');

        if ($weForfeited) {
            return $isWithdrawal
                ? InterclubResultEnum::WITHDRAWAL
                : InterclubResultEnum::FORFEIT_LOSS;
        }

        if ($theyForfeited) {
            return $isWithdrawal
                ? InterclubResultEnum::WITHDRAWAL_OPPONENT
                : InterclubResultEnum::FORFEIT_WIN;
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
            // Keyed by licence: the same missing member turns up in every tie
            // they played, and a list of four hundred lines naming forty people
            // is a list nobody reads to the end.
            $this->report['unknown_licences'][$ourIndex] = $ourPlayer?->fullName() ?? '?';
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

    /**
     * The federation's score, split into the pair and whatever it wrote after it.
     *
     * TabT decorates a score that was not simply played: "16-0 ff" for a tie
     * forfeited, "0-0 fg (fg)" for a withdrawal, "3-13 sm" for a result it has
     * adjusted. Eleven characters where the column holds ten, which is how a
     * whole season's import died on a truncation — but the length was the small
     * half of the problem. Every reader of this column, the captain's screen and
     * the match page included, splits it on "-" and casts both halves to int:
     * they would have read "0 fg (fg)" as zero and written the marker back out
     * as nothing. It is kept out of the column, and what it means that the flags
     * do not say is passed to the verdict instead.
     *
     * @return array{0: string|null, 1: string}
     */
    private function splitScore(?string $score): array
    {
        if ($score === null || ! preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*(.*)$/', $score, $matches)) {
            return [null, ''];
        }

        return [$matches[1] . '-' . $matches[2], trim($matches[3])];
    }

    private function writeFinalPosition(Season $season, int $divisionId, AfttRanking $entry): void
    {
        $team = Team::where('teams.season_id', $season->id)
            ->where('teams.name', $entry->letter())
            ->whereHas('club', fn ($query) => $query->where('is_own_club', true))
            ->whereHas('league', fn ($query) => $query->where('aftt_division_id', $divisionId))
            ->first();

        if (! $team instanceof Team) {
            return;
        }

        $label = $this->positionLabel($entry->position);

        if ($team->final_position === $label) {
            return;
        }

        $team->update(['final_position' => $label]);
        $this->tally['positions_written']++;
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

        [$score, $marker] = $this->splitScore($sheet->score);

        $us = $them = null;

        if ($score !== null) {
            [$home, $away] = array_map(intval(...), explode('-', $score, 2));
            [$us, $them] = $weAreHome ? [$home, $away] : [$away, $home];
        }

        $matchResult->update([
            // Stored home-first and numeric, like everything else that reads
            // this column.
            'score' => $score,
            'result' => $this->resultFrom($us, $them, $sheet, $weAreHome, $marker)?->value,
        ]);
    }
}
