<?php

declare(strict_types=1);

namespace tests\Architecture;

use Symfony\Component\Finder\Finder;

/*
 * Row actions used to be a strip of icon-only buttons whose meaning rested on a
 * tooltip: nothing announces it to a screen reader and nothing reveals it to a
 * thumb. Spelling every action out instead makes the row overflow, so the rule
 * is one named action in the row and the rest behind a named menu.
 *
 * <x-admin.shared.row-menu> is that rule made concrete. This test keeps new
 * screens from reintroducing the strip, which is how the pattern drifted before.
 */
it('routes row actions through the menu that names them', function (): void {
    $viewsPath = dirname(__DIR__, 2) . '/resources/views';

    $offenders = [];

    foreach ((new Finder)->files()->in($viewsPath)->name('*.blade.php') as $file) {
        foreach (explode("\n", (string) file_get_contents($file->getPathname())) as $number => $line) {
            if (str_contains($line, 'x-admin.shared.row-actions')) {
                $offenders[] = str_replace($viewsPath . '/', '', $file->getPathname()) . ':' . ($number + 1);
            }
        }
    }

    expect($offenders)->toBe([], sprintf(
        "Icon strips are gone; use <x-admin.shared.row-menu> instead:\n%s",
        implode("\n", $offenders),
    ));
});
