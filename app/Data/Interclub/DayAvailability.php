<?php

declare(strict_types=1);

namespace App\Data\Interclub;

use App\Domains\Shared\Enums\LeagueCategory;
use Illuminate\Support\Collection;

/**
 * Une catégorie d'une journée d'interclubs : ses joueurs disponibles, sur
 * l'échelle de sa propre liste de force.
 *
 * Deux décomptes accompagnent la liste sans y figurer : les oui et peut-être
 * sans indice dans la catégorie, qu'on ne peut pas aligner, et les membres des
 * équipes qui n'ont rien répondu, qu'une relance peut encore débloquer.
 *
 * @property Collection<int, DayTeam> $teams
 * @property Collection<int, DayAvailabilityRow> $players
 */
readonly class DayAvailability
{
    /**
     * @param  Collection<int, DayTeam>  $teams
     * @param  Collection<int, DayAvailabilityRow>  $players
     */
    public function __construct(
        public ?LeagueCategory $category,
        public Collection $teams,
        public Collection $players,
        public int $unrankedCount,
        public int $silentCount,
    ) {}
}
