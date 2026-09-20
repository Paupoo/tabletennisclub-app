<?php

declare(strict_types=1);

namespace App\Data\Interclub;

use App\Domains\Shared\Enums\LineupLegality;
use App\Domains\Shared\Enums\LineupLegalityReason;

/**
 * Ce que l'article C.22 dit d'un joueur pour une composition donnée : un état, et
 * la raison qui le justifie.
 *
 * Les deux voyagent ensemble parce qu'aucun des deux ne suffit. L'état décide du
 * rendu — masquer, griser, laisser cocher — et le motif décide de la phrase, qui
 * doit rester actionnable : deux incertitudes de nature différente se règlent par
 * deux gestes différents.
 */
readonly class LineupVerdict
{
    public function __construct(
        public LineupLegality $state,
        public LineupLegalityReason $reason,
    ) {}

    public function isForbidden(): bool
    {
        return $this->state === LineupLegality::FORBIDDEN;
    }

    /**
     * Le verdict porte-t-il quelque chose à dire ? Un joueur autorisé sans
     * réserve n'a pas besoin d'une pastille : la ligne reste nue.
     */
    public function isWorthShowing(): bool
    {
        return $this->state === LineupLegality::UNCERTAIN
            || $this->state === LineupLegality::FORBIDDEN;
    }
}
