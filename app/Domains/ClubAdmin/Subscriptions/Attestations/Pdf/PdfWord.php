<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf;

/**
 * One word of a template, with the box it occupies, in millimetres.
 */
final readonly class PdfWord
{
    public function __construct(
        public string $text,
        public string $normalised,
        public float $left,
        public float $top,
        public float $right,
        public float $bottom,
        /**
         * The bottom of the whole line this word sits on.
         *
         * Every word of a line shares it, which the word's own box does not:
         * « que » reaches a descender lower than « nom », and anchoring on the
         * box would drop a value a millimetre below its neighbour on the same
         * rule.
         */
        public float $lineBottom,
        public float $lineTop,
        public int $page,
    ) {}
}
