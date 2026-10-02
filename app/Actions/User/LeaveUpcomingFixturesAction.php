<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Take a departing member off the interclub matches still to come.
 *
 * Every row goes — an availability, a place in a draft, a place in a lineup
 * already sent — from the day of the departure on, and never before now: a
 * match already played belongs to the results and the match sheets.
 *
 * Telling anybody is not done here: the captain of each team the member
 * played in, and the selector, are mailed by
 * {@see TellCaptainsAndSelectorOfDepartureAction}, which lists the matches this
 * action hands back — those the member was lined up for, sent or not. An
 * availability alone is not listed: nobody was counting on it yet.
 *
 * The fixture itself is left as it is: the lineup is still published for the
 * players who remain, and it is the captain's next send from the selection
 * screen that judges what the lineup has become — the same as when a captain
 * drops a player. One exception: a lineup declared to play with three no
 * longer describes itself once one of its players is gone — two and a
 * walkover, or three without one — so the declaration and the walkover are
 * withdrawn the way the selection screen withdraws them, and the captain is
 * told to declare it again. An availability that was not in the lineup leaves
 * the declaration standing.
 */
class LeaveUpcomingFixturesAction
{
    /**
     * @return array{fixtures: int, lineups: list<int>, short_handed_withdrawn: list<int>} the number of matches left, the ids of those the member was lined up for — by date — and of those no longer declared to play with three
     */
    public static function handle(User $user, CarbonInterface $leftOn): array
    {
        $from = Carbon::instance($leftOn)->startOfDay()->max(now());

        $rows = DB::table('interclub_user')
            ->join('interclubs', 'interclubs.id', '=', 'interclub_user.interclub_id')
            ->where('interclub_user.user_id', $user->id)
            ->where('interclub_user.has_played', false)
            ->where('interclubs.start_date_time', '>=', $from)
            ->get(['interclub_user.interclub_id', 'interclub_user.is_selected', 'interclub_user.selection_confirmed_at']);

        if ($rows->isEmpty()) {
            return ['fixtures' => 0, 'lineups' => [], 'short_handed_withdrawn' => []];
        }

        $user->interclubs()->detach($rows->pluck('interclub_id')->all());

        $shortHanded = Interclub::query()
            ->whereKey($rows->where('is_selected', true)->pluck('interclub_id')->all())
            ->whereNotNull('short_handed_confirmed_at')
            ->orderBy('id')
            ->get();

        foreach ($shortHanded as $fixture) {
            $fixture->withdrawShortHandedDeclaration();
        }

        $lineups = Interclub::query()
            ->whereKey($rows->filter(fn (object $row): bool => (bool) $row->is_selected || $row->selection_confirmed_at !== null)->pluck('interclub_id')->all())
            ->orderBy('start_date_time')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        return [
            'fixtures' => $rows->count(),
            'lineups' => array_values(array_map(intval(...), $lineups)),
            'short_handed_withdrawn' => array_values(array_map(intval(...), $shortHanded->modelKeys())),
        ];
    }
}
