<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\TrainingPlanAssignment;

class SoftDeleteUserAction
{
    /**
     * @throws \DomainException when the member still has an unresolved subscription for the active season
     */
    public static function handle(User $user): void
    {
        if ($user->isAffiliatedForCurrentSeason()) {
            throw new \DomainException(__('This member has an active subscription for the current season. Cancel it before archiving.'));
        }

        // An archived member no longer occupies a spot in future training
        // packs/pool; the FK survives a soft delete, so drop it explicitly.
        TrainingPlanAssignment::query()->where('user_id', $user->id)->delete();

        $user->delete();
    }
}
