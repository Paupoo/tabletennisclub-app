<?php

declare(strict_types=1);

namespace tests\Architecture;

use Symfony\Component\Finder\Finder;

/*
 * Mary UI renders a modal as <dialog class="modal"> and puts the title in a
 * header, but it emits no aria-label, no aria-labelledby and no role. A screen
 * reader therefore announces "dialog" and nothing else — including on the
 * confirmations that delete a member or cancel a fine, where the missing name
 * is the difference between confirming and guessing.
 *
 * <x-app-modal> wraps Mary's component, demands a title and turns it into the
 * accessible name. These tests keep every modal going through that wrapper, so
 * a nameless dialog cannot come back through a new call site.
 */

it('routes every modal through the wrapper that names it', function (): void {
    $viewsPath = dirname(__DIR__, 2) . '/resources/views';

    $offenders = [];

    // The wrapper is the one place allowed to reach Mary's component: it is what
    // adds the accessible name the rest of the application then inherits.
    $files = (new Finder)->files()->in($viewsPath)->name('*.blade.php')
        ->notPath('components/app-modal.blade.php');

    foreach ($files as $file) {
        foreach (explode("\n", (string) file_get_contents($file->getPathname())) as $number => $line) {
            if (preg_match('/<x-modal[\s>]/', $line) === 1) {
                $offenders[] = str_replace($viewsPath . '/', '', $file->getPathname()) . ':' . ($number + 1);
            }
        }
    }

    expect($offenders)->toBe([], sprintf(
        "Mary's <x-modal> emits no accessible name. Use <x-app-modal> instead:\n%s",
        implode("\n", $offenders)
    ));
});

it('gives every modal a title to be named by', function (): void {
    $viewsPath = dirname(__DIR__, 2) . '/resources/views';

    $offenders = [];

    foreach ((new Finder)->files()->in($viewsPath)->name('*.blade.php') as $file) {
        $contents = (string) file_get_contents($file->getPathname());

        // Opening tags routinely wrap over several lines, so match the whole tag
        // rather than the line the component name happens to sit on.
        preg_match_all('/<x-app-modal[\s][^>]*>/s', $contents, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$tag, $offset]) {
            if (str_contains($tag, 'title=')) {
                continue;
            }

            $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
            $offenders[] = str_replace($viewsPath . '/', '', $file->getPathname()) . ':' . $line;
        }
    }

    expect($offenders)->toBe([], sprintf(
        "A modal without a title has no accessible name:\n%s",
        implode("\n", $offenders)
    ));
});
