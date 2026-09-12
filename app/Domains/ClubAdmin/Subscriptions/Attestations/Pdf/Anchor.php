<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf;

use App\Domains\Shared\Enums\AttestationAnchorPlacement;

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
        public AttestationAnchorPlacement $placement = AttestationAnchorPlacement::After,
        public float $dx = 0.0,
        public float $dy = 0.0,
        public int $page = 1,
        /** Which match to take when the same words appear more than once, 1-based. */
        public int $occurrence = 1,
        /**
         * Shrinks a seal or a signature to the box this form drew for it.
         *
         * The club sets one size for its seal; the forms disagree about how much
         * room they leave. Partenamut gives a 26 mm frame, Mutualité Neutre half
         * that, and an image spilling past its frame reads as carelessness on a
         * document the club signed.
         */
        public float $scale = 1.0,
        /**
         * An absolute column to write in, in millimetres from the left edge.
         *
         * A form laid out as a two-column table puts every value at the same x,
         * whatever the width of the label naming it. Measuring from the end of
         * each label instead drops the short ones back into the label column —
         * which is exactly where « Nom et prénom » landed on the MutPlus form.
         *
         * The label still decides the line; only the horizontal position is
         * taken from the table rather than from the words.
         */
        public ?float $column = null,
    ) {}
}
