<?php

declare(strict_types=1);

namespace App\Data\Interclub;

use App\Domains\Shared\Enums\TeamLineupNeed;

/**
 * Une équipe du club dans une journée : combien elle a coché, et ce qu'il lui
 * manque.
 */
readonly class DayTeam
{
    public function __construct(
        public int $fixtureId,
        public string $teamName,
        public int $selectedCount,
        public int $totalPlayers,
        public TeamLineupNeed $need,
        public bool $isShortHanded,
    ) {}

    public function isShort(): bool
    {
        return $this->need !== TeamLineupNeed::COMPLETE;
    }
}
