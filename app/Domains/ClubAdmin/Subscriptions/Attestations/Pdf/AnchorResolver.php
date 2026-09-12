<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf;

use App\Domains\Shared\Enums\AttestationAnchorPlacement;

/**
 * Turns "next to these words" into a spot on the page.
 *
 * Returns null rather than guessing when the words are not there: a template
 * whose wording has changed must surface as an unresolved anchor on the test
 * screen, not as a value quietly stamped in the top-left corner.
 */
final readonly class AnchorResolver
{
    /** How far below a label its own next line sits, when nothing says otherwise. */
    private const float DEFAULT_LINE_HEIGHT = 4.5;

    /**
     * Share of a line's height that hangs below the baseline.
     *
     * poppler reports the ink extent of a line, descenders included, while mPDF
     * draws from the baseline. Written straight, every value would sit about a
     * millimetre under the rule it belongs on. A fifth of the line box is the
     * usual descent for the text faces these forms are set in.
     */
    private const float DESCENDER_SHARE = 0.22;

    public function resolve(PdfTextLayout $layout, Anchor $anchor): ?Point
    {
        $box = $this->locate($layout, $anchor);

        if ($box === null) {
            return null;
        }

        [$left, $top, $right, $bottom, $lineTop] = $box;

        $baseline = $bottom - (($bottom - $lineTop) * self::DESCENDER_SHARE);

        // The line's own bottom, not the word's: a value must sit on the same
        // rule as the label that names it, and a label ending in « que » has a
        // descender that would drag its value a millimetre lower than the one
        // next to it.
        // Below is for images, and hands back the top-left of where they go.
        return match ($anchor->placement) {
            AttestationAnchorPlacement::After => new Point($right + $anchor->dx, $baseline + $anchor->dy, $anchor->page),
            AttestationAnchorPlacement::Below => new Point(
                $left + $anchor->dx,
                $bottom + self::DEFAULT_LINE_HEIGHT + $anchor->dy,
                $anchor->page,
            ),
            AttestationAnchorPlacement::At => new Point($left + $anchor->dx, $baseline + $anchor->dy, $anchor->page),
        };
    }

    /**
     * Whether every anchor of a map is still present in a template.
     *
     * @param  array<string, Anchor>  $anchors
     * @return array<int, string> The field names whose label could not be found.
     */
    public function unresolved(PdfTextLayout $layout, array $anchors): array
    {
        $missing = [];

        foreach ($anchors as $field => $anchor) {
            if (! $this->resolve($layout, $anchor) instanceof Point) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * The box of the run of words that spells the anchor's phrase.
     *
     * Words are matched as a consecutive run in reading order, so a phrase made
     * of common words ("Nom et prénom") lands on the place it actually reads,
     * not on the first stray "Nom" three paragraphs above.
     *
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float}|null
     */
    private function locate(PdfTextLayout $layout, Anchor $anchor): ?array
    {
        $needle = array_values(array_filter(
            explode(' ', PdfTextExtractor::normalise($anchor->phrase)),
            static fn (string $part): bool => $part !== '',
        ));

        if ($needle === []) {
            return null;
        }

        $words = $layout->wordsOnPage($anchor->page);
        $length = count($needle);
        $seen = 0;

        for ($start = 0; $start + $length <= count($words); $start++) {
            $run = array_slice($words, $start, $length);

            foreach ($needle as $index => $part) {
                if ($run[$index]->normalised !== $part) {
                    continue 2;
                }
            }

            if (++$seen < $anchor->occurrence) {
                continue;
            }

            // A label long enough to wrap is measured on the line it *ends* on:
            // « représentant autorisé de (nom de l'organisation) » breaks across
            // two lines on the Mutualité Neutre form, and taking the widest line
            // put the club's name in the right margin, off the page.
            $last = $run[$length - 1];
            $first = $run[0];

            return [
                $first->left,
                $first->top,
                $last->right,
                $last->lineBottom,
                $last->lineTop,
            ];
        }

        return null;
    }
}
