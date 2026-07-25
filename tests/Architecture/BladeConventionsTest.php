<?php

declare(strict_types=1);

namespace tests\Architecture;

use Symfony\Component\Finder\Finder;

/*
 * Blade compiles `@php ... @endphp` blocks by running the regex
 * /(?<!@)@php(.*?)@endphp/s over the whole template before any directive is
 * handled. An inline `@php(...)` opens a block that regex never closes, so it
 * swallows everything up to the next `@endphp` further down the file.
 *
 * The page then fails on whatever the swallowed markup was doing, never on the
 * `@php(` that caused it, which makes the trap expensive to diagnose. Blocks
 * are always safe, so require them.
 */

it('has no inline @php() directives in views', function (): void {
    $viewsPath = dirname(__DIR__, 2) . '/resources/views';

    $offenders = [];

    foreach ((new Finder)->files()->in($viewsPath)->name('*.blade.php') as $file) {
        foreach (explode("\n", (string) file_get_contents($file->getPathname())) as $number => $line) {
            if (preg_match('/(?<!@)@php\s*\(/', $line) === 1) {
                $relative = str_replace($viewsPath . '/', '', $file->getPathname());
                $offenders[] = $relative . ':' . ($number + 1);
            }
        }
    }

    expect($offenders)->toBe([], sprintf(
        "Inline @php() swallows every line up to the next @endphp in the same file. Use @php ... @endphp instead:\n%s",
        implode("\n", $offenders)
    ));
});
