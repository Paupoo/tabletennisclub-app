<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

pest()->group('components', 'designSystem');

/*
 * `progress-indicator` on <x-header> is what fires the Livewire progress bar, so
 * it is the only thing telling a reader that a click was received.
 *
 * It was not shared at random: the management screens had it, and the screens a
 * plain member actually uses — their calendar, their payments, their teams, the
 * club rules, the help pages — did not. The review rule of this project is to
 * visit every screen with the least privileged account that reaches it, and that
 * account was precisely the one that never saw anything load.
 *
 * Only the *page* header is concerned. A form's section headers (size="text-xl")
 * title a block, not a page, and a progress bar on each of them would be noise.
 */

/**
 * The opening tag starting at $from, ending at the first `>` that is not inside
 * an attribute value. A naive scan to the first `>` stops inside `$room->name`
 * and reports a header that does carry the attribute.
 */
function openingTag(string $contents, int $from): string
{
    $quote = null;

    for ($i = $from, $length = strlen($contents); $i < $length; $i++) {
        $character = $contents[$i];

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
            return substr($contents, $from, $i - $from);
        }
    }

    return substr($contents, $from);
}

/** @return array<int, string> */
function pageViews(): array
{
    return collect(File::allFiles(resource_path('views/pages')))
        ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ->map(fn ($file): string => $file->getPathname())
        ->values()
        ->all();
}

it('tells the reader something is loading on every page header', function (): void {
    $offenders = [];

    foreach (pageViews() as $path) {
        $contents = (string) File::get($path);

        $position = strpos($contents, '<x-header');

        if ($position === false) {
            continue;
        }

        /** The opening tag of the first header: the one that titles the page. */
        $tag = openingTag($contents, $position);

        if (! str_contains($tag, 'progress-indicator')) {
            $offenders[] = str_replace(resource_path('views/pages/'), '', $path);
        }
    }

    expect($offenders)->toBe([], "Page headers with no loading feedback:\n" . implode("\n", $offenders));
});
