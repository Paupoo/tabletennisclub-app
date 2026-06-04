<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubResult;

class InterclubObserver
{
    public function created(Interclub $interclub): void
    {
        $this->syncInterclubResult($interclub);
    }

    public function updated(Interclub $interclub): void
    {
        $this->syncInterclubResult($interclub);
    }

    public function deleted(Interclub $interclub): void
    {
        InterclubResult::where('interclub_id', $interclub->id)->delete();
    }

    /**
     * Keep the linked InterclubResult row in sync with the fixture's STRUCTURAL data only.
     *
     * Score and result are intentionally NOT written here: they are owned by
     * InterclubResultsSeeder / the results UI. Using updateOrCreate without those
     * keys means re-running this never wipes an existing score.
     */
    private function syncInterclubResult(Interclub $interclub): void
    {
        $interclub->loadMissing(['visitedTeam.club', 'visitingTeam.club']);

        $isHome       = $interclub->visitedTeam?->club?->licence === config('app.club_licence');
        $ourTeam      = $isHome ? $interclub->visitedTeam : $interclub->visitingTeam;
        $opponentTeam = $isHome ? $interclub->visitingTeam : $interclub->visitedTeam;

        if (! $ourTeam) {
            return;
        }

        $isBye = (bool) ($interclub->is_bye ?? false);

        $opponentName = $isBye
            ? null
            : (trim(($opponentTeam?->club?->name ?? '') . ' ' . ($opponentTeam?->name ?? '')) ?: null);

        InterclubResult::updateOrCreate(
            ['interclub_id' => $interclub->id],
            [
                'team_id'       => $ourTeam->id,
                'season_id'     => $interclub->season_id,
                'match_date'    => $interclub->start_date_time?->toDateString(),
                'week_number'   => $interclub->week_number,
                'is_home'       => $isHome,
                'opponent_name' => $opponentName,
                'is_bye'        => $isBye,
            ]
        );
    }
}
