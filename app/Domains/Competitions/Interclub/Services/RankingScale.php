<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

/**
 * The federation's rankings laid out on one line, strongest first.
 *
 * Read from the string a sheet prints rather than from the Ranking enum: the
 * women's scale and the A-rankings at the top are not in the enum, and a gap is
 * only ever taken between the two rankings of one sheet, which always share a
 * scale. B0 to E6 are one cran apart, NC one below E6, and every A-ranking one
 * above B0 — the club has nobody to tell them apart for.
 */
class RankingScale
{
    /**
     * How many crans stronger the opponent was, negative when weaker.
     *
     * Null when either side is unknown: no gap is better than an invented one.
     */
    public static function gap(?string $ours, ?string $theirs): ?int
    {
        $us = self::position($ours);
        $them = self::position($theirs);

        if ($us === null || $them === null) {
            return null;
        }

        return $us - $them;
    }

    /**
     * Lower is stronger.
     */
    public static function position(?string $ranking): ?int
    {
        $ranking = strtoupper(trim((string) $ranking));

        if ($ranking === 'NC') {
            return 20;
        }

        if (str_starts_with($ranking, 'A')) {
            return 3;
        }

        if (! preg_match('/^([B-E])([0246])$/', $ranking, $matches)) {
            return null;
        }

        return (ord($matches[1]) - ord('A')) * 4 + intdiv((int) $matches[2], 2);
    }
}
