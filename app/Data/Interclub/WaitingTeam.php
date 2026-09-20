<?php

declare(strict_types=1);

namespace App\Data\Interclub;

use App\Domains\Competitions\Interclub\Models\Team;

/**
 * Une équipe sœur qui tient encore ses disponibles, faute d'avoir publié.
 *
 * Sans cette ligne, un pool vide a deux causes opposées et rigoureusement le
 * même aspect : tout le monde joue déjà, ou personne n'a encore composé. Dans le
 * premier cas il faut chercher ailleurs sur-le-champ ; dans le second il suffit
 * d'attendre — ou de décrocher son téléphone, d'où le capitaine qui voyage avec
 * l'équipe.
 *
 * Le décompte est anonyme, et c'est la contrepartie de la règle qui veut qu'un
 * joueur apprenne sa non-sélection de son propre capitaine : on dit combien,
 * jamais qui.
 */
readonly class WaitingTeam
{
    public function __construct(
        public Team $team,
        public int $availableCount,
    ) {}
}
