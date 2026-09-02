<?php

declare(strict_types=1);

namespace App\Data\Interclub;

use App\Domains\Competitions\Interclub\Models\Season;

/**
 * The federation's season list, reduced to the one question we ask of it:
 * which season is running right now.
 *
 * The number is the federation's own index (27 for 2026-2027), the name is what
 * it prints. The name is what matters on our side: it is how an AFTT season is
 * matched to a local {@see Season}, because the index is an internal counter
 * nobody at the club would recognise.
 */
readonly class AfttSeasons
{
    /**
     * @param  array<int, string>  $all  Every season the federation publishes,
     *                                   its index mapped to its name. Kept so a
     *                                   season can be loaded before the
     *                                   federation makes it the current one.
     */
    public function __construct(
        public int $currentSeason,
        public string $currentSeasonName,
        public array $all = [],
    ) {}
}
