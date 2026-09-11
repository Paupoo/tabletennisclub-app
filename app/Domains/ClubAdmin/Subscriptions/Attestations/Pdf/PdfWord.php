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
        public int $page,
    ) {}
}
