<?php

declare(strict_types=1);

namespace App\Data\Interclub;

use Illuminate\Support\Collection;

/**
 * Tout ce que le tiroir a besoin de savoir des joueurs libres d'une journée.
 *
 * Les trois lectures voyagent ensemble parce qu'elles se répondent : un pool
 * vide ne veut rien dire sans les équipes encore en attente, et un capitaine à
 * J-2 sans personne veut savoir qu'il reste des « peut-être ». Elles partagent
 * surtout le même balayage de rencontres, et l'écran qui les affiche se recharge
 * à chaque case cochée.
 *
 * @property Collection<int, PoolCandidate> $freePlayers
 * @property Collection<int, PoolCandidate> $maybePlayers
 * @property Collection<int, WaitingTeam> $waitingTeams
 */
readonly class InterclubPool
{
    /**
     * @param  Collection<int, PoolCandidate>  $freePlayers
     * @param  Collection<int, PoolCandidate>  $maybePlayers
     * @param  Collection<int, WaitingTeam>  $waitingTeams
     */
    public function __construct(
        public Collection $freePlayers,
        public Collection $maybePlayers,
        public Collection $waitingTeams,
    ) {}

    public function isEmpty(): bool
    {
        return $this->freePlayers->isEmpty();
    }

    /**
     * Le pool n'a-t-il vraiment rien à dire ? Une liste vide accompagnée
     * d'équipes en attente ou de « peut-être » reste une information.
     */
    public function isSilent(): bool
    {
        return $this->freePlayers->isEmpty()
            && $this->maybePlayers->isEmpty()
            && $this->waitingTeams->isEmpty();
    }
}
