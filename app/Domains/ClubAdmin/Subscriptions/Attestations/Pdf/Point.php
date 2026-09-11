<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf;

/**
 * A spot on a page, in millimetres from the top-left corner.
 *
 * Millimetres and top-left because that is mPDF's own coordinate system: the
 * conversion from poppler's points happens once, in {@see PdfTextExtractor},
 * rather than at every call site where it could be forgotten.
 */
final readonly class Point
{
    public function __construct(
        public float $x,
        public float $y,
        public int $page = 1,
    ) {}
}
