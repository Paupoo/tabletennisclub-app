<?php

declare(strict_types=1);

namespace App\Data\Interclub;

/**
 * One individual match inside a tie.
 *
 * Three shapes come back on the same element, and all three are real:
 *
 * - a played singles: both licences, both set counts;
 * - a forfeited singles: both licences, no set count, and the forfeit flag on
 *   the side that did not turn up;
 * - the double: `0` for every player index, no name anywhere, only the set
 *   count. The federation records who won the double and never who played it —
 *   checked across a full veterans division, 27 ties, 27 anonymous lines, all
 *   at the same position.
 */
readonly class AfttSheetResult
{
    public function __construct(
        public int $position,
        public ?string $homeUniqueIndex,
        public ?string $awayUniqueIndex,
        public ?int $homeSetCount,
        public ?int $awaySetCount,
        public bool $isHomeForfeited,
        public bool $isAwayForfeited,
    ) {}

    /**
     * Whether the home side took the point, or null when the line says nothing.
     */
    public function homeWon(): ?bool
    {
        if ($this->isAwayForfeited) {
            return true;
        }

        if ($this->isHomeForfeited) {
            return false;
        }

        if ($this->homeSetCount === null || $this->awaySetCount === null) {
            return null;
        }

        return $this->homeSetCount > $this->awaySetCount;
    }

    /**
     * Nobody is named on either side: this is the double.
     */
    public function isDouble(): bool
    {
        return $this->homeUniqueIndex === null && $this->awayUniqueIndex === null;
    }
}
