<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Trainings\Models\TrainingPlan;
use App\Domains\Trainings\Models\TrainingPlanAssignment;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hand back what a member was holding for the club: the teams they captain and
 * their seats in the training plans.
 *
 * Shared by the two ways a member stops counting — archived, or gone for the
 * season — so the teams left without a captain are found, and named, the same
 * way in both.
 */
class ReleaseClubPlacesAction
{
    /**
     * @param  Season|null  $from  only what belongs to this season or a later one; everything when null
     * @return array<int, string> the names of the teams left without a captain
     */
    public static function handle(User $user, ?Season $from = null): array
    {
        TrainingPlanAssignment::query()
            ->where('user_id', $user->id)
            ->when($from, fn (Builder $assignment): Builder => $assignment->whereIn(
                'training_plan_id',
                TrainingPlan::query()->select('id')->whereIn('season_id', self::seasonsFrom($from)),
            ))
            ->delete();

        // `teams.captain_id` est une clé étrangère `nullOnDelete` : elle ne se
        // déclenche ni sur un soft delete ni sur un départ. Sans ça, la colonne
        // pointerait sur quelqu'un qui n'est plus là pendant que l'écran affiche
        // « Non défini », sans que personne ne sache depuis quand ni pourquoi.
        $captained = Team::query()
            ->where('captain_id', $user->id)
            ->when($from, fn (Builder $team): Builder => $team->whereIn('season_id', self::seasonsFrom($from)))
            ->get();

        Team::query()->whereKey($captained->modelKeys())->update(['captain_id' => null]);

        return $captained->pluck('name')->all();
    }

    /**
     * The given season and every one that starts after it — a team or a plan
     * may already be drawn up for next season.
     *
     * @return Builder<Season>
     */
    public static function seasonsFrom(Season $season): Builder
    {
        return Season::query()->select('id')->where('start_at', '>=', $season->start_at);
    }
}
