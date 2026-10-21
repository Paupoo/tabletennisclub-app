<?php

declare(strict_types=1);

namespace tests\Architecture;

use Symfony\Component\Finder\Finder;

/*
 * A confirmation goes through the app's own dialog, never the browser's.
 *
 * `wire:confirm` and a bare `confirm()` open the native box: unstyled, out of
 * the theme, titled with the site's address, and on a phone a different
 * widget on every browser. The app has one confirmation dialog, mounted in
 * the layout: a button asks it with
 * `$dispatch('app-confirm', { message, method, params, id: $wire.$id })`.
 */

it('asks for no confirmation through the browser box', function (): void {
    $viewsPath = dirname(__DIR__, 2) . '/resources/views';

    $offenders = [];

    foreach ((new Finder)->files()->in($viewsPath)->name('*.blade.php') as $file) {
        foreach (explode("\n", (string) file_get_contents($file->getPathname())) as $number => $line) {
            if (preg_match('/wire:confirm|(?<![\w.$>])confirm\(\s*[\'"@]/', $line) === 1) {
                $offenders[] = str_replace($viewsPath . '/', '', $file->getPathname()) . ':' . ($number + 1);
            }
        }
    }

    expect($offenders)->toBe([], sprintf(
        "Use the app's confirmation dialog — \$dispatch('app-confirm', { message, method, params, id: \$wire.\$id }) — not the browser box:\n%s",
        implode("\n", $offenders),
    ));
});

it('mounts the confirmation dialog in the app layout', function (): void {
    expect((string) file_get_contents(dirname(__DIR__, 2) . '/resources/views/layouts/app.blade.php'))
        ->toContain('<x-confirm-dialog />');
});
