<?php

declare(strict_types=1);

namespace App\Data\Interclub;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubAvailability;

/**
 * Un joueur libre, tel que le tiroir de composition le montre.
 *
 * L'équipe d'origine voyage avec le joueur parce qu'elle décide de la lecture :
 * « B0, disponible » ne dit pas si l'on emprunte à l'équipe juste au-dessus ou
 * trois divisions plus bas.
 *
 * La note de disponibilité aussi, et c'est délibéré : « pas avant 20h » est le
 * genre de contrainte qui décide d'un déplacement. Mais elle a été écrite pour
 * *un autre* capitaine et *une autre* rencontre, donc la vue doit la citer comme
 * telle et non la lire comme une réponse à l'invitation en cours.
 */
readonly class PoolCandidate
{
    public function __construct(
        public User $user,
        public Team $originTeam,
        public ?InterclubAvailability $availability,
        public ?string $availabilityNote,
    ) {}
}
