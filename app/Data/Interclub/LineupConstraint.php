<?php

declare(strict_types=1);

namespace App\Data\Interclub;

/**
 * Ce que les équipes supérieures imposent à la composition en cours.
 *
 * Les deux bornes encadrent le seuil de l'article C.22 : `strongest` s'il
 * alignent leurs meilleurs, `weakest` s'ils alignent leurs plus faibles. Elles
 * se confondent dès qu'une composition existe, puisqu'il n'y a plus rien à
 * prédire.
 *
 * `rankIsReadable` porte la décision de se taire : le rang d'une équipe se
 * déduit de son nom, et un nom qui ne se range pas désactive la règle pour toute
 * la catégorie plutôt que de la calculer sur un ordre inventé. Deux bornes nulles
 * ne suffisent pas à distinguer ce cas de celui de l'équipe la plus forte, qui
 * n'a simplement personne au-dessus d'elle.
 */
readonly class LineupConstraint
{
    /** @param array<int, string> $superiorTeamNames */
    public function __construct(
        public ?int $strongest,
        public ?int $weakest,
        public bool $rankIsReadable,
        public array $superiorTeamNames,
    ) {}

    /** @return array{strongest: int|null, weakest: int|null} */
    public function bounds(): array
    {
        return ['strongest' => $this->strongest, 'weakest' => $this->weakest];
    }

    /** La règle a-t-elle quelque chose à dire sur cette composition ? */
    public function constrains(): bool
    {
        return $this->rankIsReadable && ($this->strongest !== null || $this->weakest !== null);
    }
}
