<?php

declare(strict_types=1);

namespace App\Support\Charts;

/**
 * The small pieces of text and markup every chart repeats.
 */
final class ChartFormat
{
    /**
     * A bar with a 4px rounded end away from its baseline, square at the
     * baseline: vertical when `$horizontal` is false (growing up from
     * `$y + $height`), horizontal otherwise (growing right from `$x`).
     */
    public static function bar(float $x, float $y, float $width, float $height, bool $horizontal = false): string
    {
        if ($width <= 0 || $height <= 0) {
            return '';
        }

        if ($horizontal) {
            $r = min(4, $height / 2, $width);

            return sprintf(
                'M%s %s H%s Q%s %s %s %s V%s Q%s %s %s %s H%s Z',
                $x, $y, $x + $width - $r,
                $x + $width, $y, $x + $width, $y + $r,
                $y + $height - $r,
                $x + $width, $y + $height, $x + $width - $r, $y + $height,
                $x,
            );
        }

        $r = min(4, $width / 2, $height);

        return sprintf(
            'M%s %s V%s Q%s %s %s %s H%s Q%s %s %s %s V%s Z',
            $x, $y + $height, $y + $r,
            $x, $y, $x + $r, $y,
            $x + $width - $r,
            $x + $width, $y, $x + $width, $y + $r,
            $y + $height,
        );
    }

    /**
     * « +12 % », « −8 % »; nothing to compare with when the year before had
     * none of it.
     */
    public static function change(float $current, float $previous): ?string
    {
        if (abs($previous) < 0.005) {
            return null;
        }

        $percent = (int) round(($current - $previous) / abs($previous) * 100);

        return ($percent > 0 ? '+' : ($percent < 0 ? '−' : '')) . abs($percent) . ' %';
    }

    /**
     * « 1 240 € », Belgian style; cents only when asked for.
     */
    public static function euros(float $amount, int $decimals = 0): string
    {
        return number_format($amount, $decimals, ',', ' ') . ' €';
    }

    /**
     * Paint an SVG shape with a colour of {@see ChartPalette}.
     *
     * A CSS variable only resolves through a style declaration, while mPDF
     * reads a presentation attribute and ignores custom properties: the same
     * chart writes one or the other depending on what it was given. The
     * colours come from the palette, never from data.
     */
    public static function paint(string $colour, string $property = 'fill'): string
    {
        return str_starts_with($colour, 'var(')
            ? sprintf('style="%s: %s"', $property, $colour)
            : sprintf('%s="%s"', $property, $colour);
    }
}
