<?php

declare(strict_types=1);

namespace tests\Architecture;

use Symfony\Component\Finder\Finder;

/*
 * A modal ships its body on every render, open or not. The member list carried
 * six closed dialogs, 70 kB of HTML, one <select> of which held every event in
 * the club — all of it for boxes nobody had opened.
 *
 * <x-app-modal :open="$theSameProperty"> holds the body back until the dialog
 * is open. The shell stays, so Alpine keeps the mechanics it entangles with.
 * This test keeps a new modal from being written without it.
 */
it('holds back the body of every modal until it is open', function (): void {
    $viewsPath = dirname(__DIR__, 2) . '/resources/views';

    $offenders = [];

    foreach ((new Finder)->files()->in($viewsPath)->name('*.blade.php') as $file) {
        $path = $file->getPathname();

        // the two components carry the mechanism itself, not a call to it
        if (str_ends_with($path, 'components/app-modal.blade.php') || str_ends_with($path, 'components/confirm-modal.blade.php')) {
            continue;
        }

        $source = (string) file_get_contents($path);
        $cursor = 0;

        while (true) {
            $starts = array_filter([
                strpos($source, '<x-app-modal', $cursor),
                strpos($source, '<x-confirm-modal', $cursor),
            ], fn ($position) => $position !== false);

            if ($starts === []) {
                break;
            }

            $start = min($starts);
            $end = tagEnd($source, $start);

            if ($end === null) {
                break;
            }

            $tag = substr($source, $start, $end - $start + 1);

            if (! str_contains($tag, ':open=')) {
                $line = substr_count(substr($source, 0, $start), "\n") + 1;
                $offenders[] = str_replace($viewsPath . '/', '', $path) . ':' . $line;
            }

            $cursor = $end + 1;
        }
    }

    expect($offenders)->toBe([], sprintf(
        "Every modal states when it is open, so its body is only built then — add :open=\"\$theProperty\":\n%s",
        implode("\n", $offenders),
    ));
});

/**
 * Position of the `>` closing the tag opened at $start, quotes respected: a
 * `>` also lives inside `$n > 0` and `$user->id`.
 */
function tagEnd(string $source, int $start): ?int
{
    $quote = null;

    for ($index = $start; $index < strlen($source); $index++) {
        $character = $source[$index];

        if ($quote !== null) {
            if ($character === $quote) {
                $quote = null;
            }

            continue;
        }

        if ($character === '"' || $character === "'") {
            $quote = $character;

            continue;
        }

        if ($character === '>') {
            return $index;
        }
    }

    return null;
}
