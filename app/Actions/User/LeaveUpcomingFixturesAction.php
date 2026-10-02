<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Notifications\MemberLeftLineupNotification;
use App\Domains\Competitions\Interclub\Services\InterclubChangeNotifier;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Take a departing member off the interclub matches still to come.
 *
 * Every row goes — an availability, a place in a draft, a place in a lineup
 * already sent — from the day of the departure on, and never before now: a
 * match already played belongs to the results and the match sheets.
 *
 * Only a lineup the team has received is news. Its captain now has a name to
 * find, so the captain and whoever holds the interclubs duty are mailed, once
 * each for the whole departure. A draft or an availability goes in silence:
 * nobody was counting on it yet.
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
     * @return array{fixtures: int, lineups: int, captain_told: bool}
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
            return ['fixtures' => 0, 'lineups' => 0, 'captain_told' => false];
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

        $published = Interclub::query()
            ->whereKey($rows->whereNotNull('selection_confirmed_at')->pluck('interclub_id')->all())
            ->orderBy('start_date_time')
            ->orderBy('id')
            ->get();

        return [
            'fixtures' => $rows->count(),
            'lineups' => $published->count(),
            'captain_told' => self::tellCaptainsAndManager($user, $published, $shortHanded->modelKeys()),
        ];
    }

    /**
     * One mail per person, listing the matches that concern them: the captain
     * of each team its own, the interclubs duty all of them.
     *
     * @param  EloquentCollection<int, Interclub>  $published
     * @param  array<int, int>  $shortHandedWithdrawn  the fixtures whose declaration to play with three was withdrawn
     * @return bool whether a captain was among those told
     */
    private static function tellCaptainsAndManager(User $leaver, EloquentCollection $published, array $shortHandedWithdrawn): bool
    {
        if ($published->isEmpty()) {
            return false;
        }

        /** @var array<int, list<int>> $fixturesByRecipient */
        $fixturesByRecipient = [];
        $captainIds = [];

        foreach ($published as $fixture) {
            $captainId = $fixture->ourTeam()?->captain_id;

            if ($captainId !== null && $captainId !== $leaver->id) {
                $fixturesByRecipient[$captainId][] = $fixture->id;
                $captainIds[] = $captainId;
            }
        }

        foreach (app(InterclubChangeNotifier::class)->interclubsDuty() as $manager) {
            $fixturesByRecipient[$manager->id] = $published->modelKeys();
        }

        unset($fixturesByRecipient[$leaver->id]);

        $recipients = User::query()->whereKey(array_keys($fixturesByRecipient))->get();

        foreach ($recipients as $recipient) {
            $fixtureIds = array_values(array_unique($fixturesByRecipient[$recipient->id]));

            $recipient->notify(new MemberLeftLineupNotification(
                $leaver->full_name,
                $fixtureIds,
                array_values(array_intersect($fixtureIds, $shortHandedWithdrawn)),
            ));
        }

        return array_intersect($captainIds, $recipients->modelKeys()) !== [];
    }
}
