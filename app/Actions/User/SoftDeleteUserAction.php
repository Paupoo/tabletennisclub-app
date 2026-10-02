<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;

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
        // packs/pool, nor captains a team: a soft delete fires no foreign key.
        //
        // Ce cas n'existait pas tant qu'un capitaine était forcément un compétiteur
        // affilié, donc inarchivable par le garde-fou du dessus.
        $freedTeams = ReleaseClubPlacesAction::handle($user);

        $user->delete();

        return $freedTeams;
    }
}
