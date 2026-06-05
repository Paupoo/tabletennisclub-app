<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Competitions\Interclub\Models\InterclubResult;
use App\Domains\Competitions\Interclub\Models\Season;
use Database\Seeders\Data\InterclubSeasonData;
use Illuminate\Database\Seeder;

/**
 * Applies the known 2025-2026 scores onto the InterclubResult rows created by
 * InterclubScheduleSeeder (via the InterclubObserver).
 *
 * Must run AFTER InterclubScheduleSeeder. Matches with score === null (the last
 * round of 2026-04-17) are left empty on purpose.
 */
class InterclubResultsSeeder extends Seeder
{
    public function run(): void
    {
        $season = Season::firstWhere('name', InterclubSeasonData::SEASON_NAME);

        if (! $season) {
            $this->command->warn('Season ' . InterclubSeasonData::SEASON_NAME . ' not found. Run InterclubScheduleSeeder first.');

            return;
        }

        foreach (InterclubSeasonData::teams() as $teamData) {
            foreach ($teamData['matches'] as $match) {
                // Skip byes and fixtures not yet played (no score AND no result).
                // Withdrawals legitimately have a result with a null score.
                if (($match['bye'] ?? false) || ($match['score'] === null && $match['result'] === null)) {
                    continue;
                }

                InterclubResult::where('season_id', $season->id)
                    ->whereDate('match_date', $match['date'])
                    ->where('opponent_name', $match['opponent'])
                    ->update([
                        'score'  => $match['score'],
                        'result' => $match['result']?->value,
                    ]);
            }
        }
    }
}
