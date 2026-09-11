<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf;

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

    public function resolve(PdfTextLayout $layout, Anchor $anchor): ?Point
    {
        $box = $this->locate($layout, $anchor);

        if ($box === null) {
            return null;
        }

        [$left, $top, $right, $bottom] = $box;

        return match ($anchor->placement) {
            AnchorPlacement::After => new Point($right + $anchor->dx, $top + $anchor->dy, $anchor->page),
            AnchorPlacement::Below => new Point(
                $left + $anchor->dx,
                $bottom + self::DEFAULT_LINE_HEIGHT + $anchor->dy,
                $anchor->page,
            ),
            AnchorPlacement::At => new Point($left + $anchor->dx, $top + $anchor->dy, $anchor->page),
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
     * @return array{0: float, 1: float, 2: float, 3: float}|null
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

            return [
                min(array_map(static fn (PdfWord $word): float => $word->left, $run)),
                min(array_map(static fn (PdfWord $word): float => $word->top, $run)),
                max(array_map(static fn (PdfWord $word): float => $word->right, $run)),
                max(array_map(static fn (PdfWord $word): float => $word->bottom, $run)),
            ];
        }

        return null;
    }
}
