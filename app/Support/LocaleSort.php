<?php

declare(strict_types=1);

namespace App\Support;

use Collator;
use Illuminate\Support\Collection;

/**
 * Alphabetical order as the interface language reads it.
 *
 * PHP's own sort compares byte by byte, which files every accented word after
 * Z: « Équipe » lands past « Zone », and « Œuf » past both. Collator applies
 * the locale's collation rules instead — the reason ext-intl is a declared
 * requirement rather than a convenience.
 *
 * Only for lists drawn from data, whose order carries nothing of its own: the
 * members who authored something, the item types present in a log. A status, an
 * age category or a season is ordered on purpose, and sorting those alphabetically
 * throws away the very information the order was carrying.
 */
final class LocaleSort
{
    /**
     * Sort option rows on the label the dropdown actually shows.
     *
     * @param  Collection<array-key, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public static function byKey(Collection $rows, string $key): Collection
    {
        $collator = self::collator();

        return $rows
            ->sort(fn (array $a, array $b): int => (int) $collator->compare((string) $a[$key], (string) $b[$key]))
            ->values();
    }

    public static function collator(): Collator
    {
        return new Collator(app()->getLocale());
    }
}
