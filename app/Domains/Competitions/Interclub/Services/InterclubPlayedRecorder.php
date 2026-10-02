<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Who of ours played a tie, as the federation's match sheet records it.
 *
 * The sheet is the official record of the evening, and it is the only source
 * of `interclub_user.has_played` once it has been imported: a published
 * line-up says who was meant to come, the sheet says who did. Everything here
 * reads `interclub_individual_matches`, never the federation, so the same rule
 * serves the daily import and a backfill over sheets already on file.
 *
 * A member played when at least one line names them and that line was not a
 * forfeit of ours. A line is stored from our side, so a forfeit we lost is the
 * one our player did not turn up to; a forfeit we won is the opponent's, and
 * our player was there. The double names nobody and makes nobody a player.
 * A licence the club roster does not know has no `user_id` and is left out —
 * the import already reports it.
 *
 * Only `has_played` is written. The availability and the selection belong to
 * the member and to the captain, and a sheet saying who played tells nothing
 * about either. A member on the sheet with no roster row — lined up on the
 * night without going through the application — gets one, carrying nothing but
 * that they played.
 */
class InterclubPlayedRecorder
{
    /**
     * Align the roster of one tie with its sheet. Safe to run any number of
     * times: a member dropped from a corrected sheet goes back to not played.
     */
    public function record(Interclub $interclub): void
    {
        $playedIds = InterclubIndividualMatch::query()
            ->where('interclub_id', $interclub->id)
            ->whereNotNull('user_id')
            ->where(fn (Builder $line): Builder => $line
                ->where('is_forfeit', false)
                ->orWhere('we_won', true))
            ->distinct()
            ->pluck('user_id')
            ->all();

        DB::transaction(function () use ($interclub, $playedIds): void {
            DB::table('interclub_user')
                ->where('interclub_id', $interclub->id)
                ->whereNotIn('user_id', $playedIds)
                ->where('has_played', true)
                ->update(['has_played' => false, 'updated_at' => now()]);

            $interclub->users()->syncWithoutDetaching(
                array_fill_keys($playedIds, ['has_played' => true]),
            );
        });
    }

    /**
     * Every tie with a sheet on file, for when the rule changes or the sheets
     * were imported before it existed. A tie without a sheet is left alone:
     * no sheet, no verdict.
     *
     * @return int the number of ties recomputed
     */
    public function recordAll(): int
    {
        $count = 0;

        Interclub::query()
            ->whereIn('id', InterclubIndividualMatch::query()->select('interclub_id'))
            ->orderBy('interclubs.id')
            ->each(function (Interclub $interclub) use (&$count): void {
                $this->record($interclub);
                $count++;
            });

        return $count;
    }
}
