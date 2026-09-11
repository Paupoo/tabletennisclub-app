<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf;

/**
 * Where a value goes, expressed as "next to these words".
 *
 * Coordinates are deliberately not stored. A mutual insurer that reissues its
 * form with the layout nudged half a centimetre breaks every absolute x/y;
 * the wording of its own labels is the part that stays put, because it is what
 * the form is for. Re-deriving from the labels at upload time means a revised
 * template usually just works, and the test screen says so immediately when it
 * does not.
 */
final readonly class Anchor
{
    public function __construct(
        public string $phrase,
        public AnchorPlacement $placement = AnchorPlacement::After,
        public float $dx = 0.0,
        public float $dy = 0.0,
        public int $page = 1,
        /** Which match to take when the same words appear more than once, 1-based. */
        public int $occurrence = 1,
    ) {}
}
