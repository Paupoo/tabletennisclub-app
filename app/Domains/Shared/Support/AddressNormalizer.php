<?php

declare(strict_types=1);

namespace App\Domains\Shared\Support;

/**
 * Puts a Belgian street or locality into the casing a human would write.
 *
 * The federation exports addresses in capitals, and capitals are not a spelling:
 * they are what the export does. `LOUVAIN-LA-NEUVE` on a member's file is the
 * club's own commune written wrong on nearly every record, and on every address
 * label printed from them.
 *
 * Plain title casing is not enough — it yields `Louvain-La-Neuve` and `Rue Du
 * Test`. The particles carry the rule: they stay lowercase everywhere except at
 * the very start, where they are the name's own first word (`La Hulpe`,
 * `Le Roeulx`). One list serves streets and localities alike; a street is built
 * out of locality names as often as not.
 *
 * What this deliberately does not do: restore accents. `CHAUSSEE` comes out
 * `Chaussee`, because the export dropped the accent and inventing one would be
 * guessing at somebody's address.
 *
 * Not applied to people's names. The casing of a particle in a surname is a fact
 * of civil registry, not a rule — `Vandenberghe`, `Van den Berghe` and `van den
 * Berghe` are three different families, and only their identity card settles it.
 */
class AddressNormalizer
{
    /**
     * Particles that only ever appear elided, before an apostrophe.
     *
     * Kept apart from {@see PARTICLES} because a lone `A`, `D` or `L` in a
     * Belgian address is a box number — `Rue du Test 12 A` — and lowercasing it
     * would turn an address into a different one.
     *
     * @var array<int, string>
     */
    private const array ELISIONS = ['d', 'l', 'n', 'qu', 's', 't'];

    /**
     * Words that stay lowercase inside a name.
     *
     * @var array<int, string>
     */
    private const array PARTICLES = [
        'à', 'au', 'aux', 'chez', 'de', 'den', 'der', 'des', 'du', 'en', 'et',
        'la', 'le', 'les', 'lez', 'op', 'sous', 'sur', 'ten', 'ter', 'tot',
        'van', 'von',
    ];

    /**
     * Idempotent: the review screen shows the result before it is written, and
     * the model mutator casts the same value again on the way in.
     */
    public static function titleCase(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Runs of spaces are how a dropped cell or a hand-typed line arrives.
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ($value === '') {
            return null;
        }

        // Hyphens are kept as separators rather than skipped over: a locality is
        // hyphenated exactly where a space would otherwise be, and its particles
        // follow the same rule — `Ottignies-Louvain-la-Neuve`.
        $parts = preg_split('/([\s\-]+)/u', $value, flags: PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $wordIndex = 0;

        foreach ($parts as $index => $part) {
            if ($index % 2 === 1) {
                continue;
            }

            $parts[$index] = self::word($part, isFirst: $wordIndex === 0);
            $wordIndex++;
        }

        return implode('', $parts);
    }

    /**
     * An elided particle and what it carries: `L'EXEMPLE` is `l'Exemple`, one
     * word to the eye and two to the rule.
     */
    private static function elision(string $word, bool $isFirst): ?string
    {
        if (preg_match("/^(\w+)'(.+)$/u", $word, $matches) !== 1) {
            return null;
        }

        [, $particle, $rest] = $matches;

        if (! in_array(mb_strtolower($particle), self::ELISIONS, true)) {
            return null;
        }

        return ($isFirst ? mb_convert_case($particle, MB_CASE_TITLE) : mb_strtolower($particle))
            . "'" . mb_convert_case($rest, MB_CASE_TITLE);
    }

    /**
     * `MB_CASE_TITLE` rather than a manual uppercase of the first letter: it
     * leaves a house number alone, where `ucfirst(strtolower())` would turn
     * `12A` into `12a`.
     */
    private static function word(string $word, bool $isFirst): string
    {
        if ($word === '') {
            return $word;
        }

        $elided = self::elision($word, $isFirst);

        if ($elided !== null) {
            return $elided;
        }

        if (! $isFirst && in_array(mb_strtolower($word), self::PARTICLES, true)) {
            return mb_strtolower($word);
        }

        return mb_convert_case($word, MB_CASE_TITLE);
    }
}
