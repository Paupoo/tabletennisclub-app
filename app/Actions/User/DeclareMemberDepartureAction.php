<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Data\MemberDepartureOutcome;
use App\Domains\ClubAdmin\Users\Models\MemberDeparture;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Models\TeamUser;
use App\Domains\Shared\Enums\DepartureReason;
use App\Domains\Trainings\Models\TrainingPack;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Record that a member is leaving the club this season.
 *
 * Not a cancellation: the affiliation, its payments and its training packs are
 * left exactly as they are — a member who paid in September and leaves in
 * December still counts for the federation and for the treasurer. Cancelling
 * the affiliation, with or without a refund, stays its own gesture.
 *
 * What the departure does hand back is what the member was holding for the
 * season: a place on a team sheet, a captaincy, a seat in a training plan, a
 * place in the interclub matches still to come. The
 * sessions still to come and the club-wide mailings read the departure itself
 * ({@see TrainingPack::trainees()} and the
 * communications audience), so nothing has to be rewritten there.
 */
class DeclareMemberDepartureAction
{
    /**
     * @throws \DomainException when no season is running
     */
    public static function handle(User $user, CarbonInterface $leftOn, DepartureReason $reason, ?string $note, ?User $recordedBy): MemberDepartureOutcome
    {
        $season = Season::current() ?? throw new \DomainException(__('No season is running: a departure belongs to one.'));

        return DB::transaction(function () use ($user, $season, $leftOn, $reason, $note, $recordedBy): MemberDepartureOutcome {
            // Declared twice the same season, it is a correction, not a second departure.
            MemberDeparture::query()->updateOrCreate(
                ['user_id' => $user->id, 'season_id' => $season->id],
                [
                    'left_on' => $leftOn->toDateString(),
                    'reason' => $reason,
                    'note' => filled($note) ? $note : null,
                    'recorded_by' => $recordedBy?->id,
                ],
            );

            // One by one through the pivot model, so the audit log records who
            // took the player off each sheet.
            TeamUser::query()
                ->where('user_id', $user->id)
                ->whereIn('team_id', Team::query()->select('id')->whereIn('season_id', ReleaseClubPlacesAction::seasonsFrom($season)))
                ->get()
                ->each->delete();

            $teamsWithoutCaptain = ReleaseClubPlacesAction::handle($user, $season);

            $fixtures = LeaveUpcomingFixturesAction::handle($user, $leftOn);

            return new MemberDepartureOutcome(
                teamsWithoutCaptain: $teamsWithoutCaptain,
                fixturesLeft: $fixtures['fixtures'],
                lineupsLeft: $fixtures['lineups'],
                captainTold: $fixtures['captain_told'],
            );
        });
    }
}
