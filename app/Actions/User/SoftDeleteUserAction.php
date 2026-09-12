<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Trainings\Models\TrainingPlanAssignment;

class SoftDeleteUserAction
{
    /**
     * Archive a member, and hand back the teams they were captaining.
     *
     * @return array<int, string> the letters of the teams left without a captain
     *
     * @throws \DomainException when the member still has an unresolved subscription for the active season
     */
    public static function handle(User $user): array
    {
        if ($user->isAffiliatedForCurrentSeason()) {
            throw new \DomainException(__('This member has an active subscription for the current season. Cancel it before archiving.'));
        }

        // An archived member no longer occupies a spot in future training
        // packs/pool; the FK survives a soft delete, so drop it explicitly.
        TrainingPlanAssignment::query()->where('user_id', $user->id)->delete();

        // `teams.captain_id` est une clé étrangère `nullOnDelete` : elle ne se
        // déclenche pas sur un soft delete. Sans ça, la colonne pointerait sur un
        // archivé pendant que l'écran affiche « Non défini », la relation traversant
        // SoftDeletes. Même raison que les assignations ci-dessus.
        //
        // Ce cas n'existait pas tant qu'un capitaine était forcément un compétiteur
        // affilié, donc inarchivable par le garde-fou du dessus.
        $captained = Team::query()->where('captain_id', $user->id)->get();
        Team::query()->where('captain_id', $user->id)->update(['captain_id' => null]);

        $user->delete();

        return $captained->pluck('name')->all();
    }
}
