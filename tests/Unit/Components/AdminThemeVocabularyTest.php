<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

pest()->group('components', 'designSystem');

/*
 * One vocabulary for text and surfaces in the back office.
 *
 * app.css clamps `text-gray-300/400/500` onto a theme-aware token, so those
 * three steps follow the theme wherever they are written. The darker half of
 * the scale does not, and neither does any raw surface: `text-gray-900` on a
 * dark card measures 1.11:1, and a `bg-white` panel is a light slab on a dark
 * page. The pairing is what makes it hard to catch by eye — the supporting
 * text keeps reading while the titles beside it disappear, so a screenshot
 * looks half-right rather than broken.
 *
 * A class scoped to a theme is fine by construction: `dark:bg-gray-900` only
 * ever paints on the ground it was written for. So the rule is about the
 * unscoped form.
 *
 * Use `text-base-content` for emphasis, `text-muted` for supporting text,
 * `bg-base-100` / `bg-base-200` for surfaces and `border-base-300` /
 * `divide-base-300` for rules.
 */

/** @return array<int, string> */
function themeVocabularyBladeFiles(): array
{
    $roots = [
        resource_path('views/pages'),
        resource_path('views/components/admin'),
        resource_path('views/clubAdmin'),
    ];

    return collect($roots)
        ->flatMap(fn (string $root): array => File::allFiles($root))
        ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.blade.php'))
        ->map(fn ($file): string => $file->getPathname())
        ->values()
        ->all();
}

/*
 * A surface that states its own colour on purpose, with the reason it has to.
 * Anything added here is a decision, not a leftover.
 */
const THEME_BLIND_ON_PURPOSE = [
    // A QR code is read through the contrast between its dark modules and a
    // light quiet zone. On a dark surface it stops being scannable, which is
    // the only thing this block is for.
    'components/admin/club-events/tournaments/partials/live/drawers/score-entry.blade.php' => ['bg-white'],
];

/**
 * Every utility in the file that matches one of the banned endings and is not
 * scoped to a theme by a `dark:` variant.
 *
 * @return array<int, string>
 */
function themeBlindClassesIn(string $contents): array
{
    $banned = 'bg-white|bg-gray-(?:50|100)|text-gray-(?:600|700|800|900)|divide-gray-\d{2,3}';

    // The token is the whole utility, variants included, so `dark:` anywhere in
    // its prefix chain — `dark:hover:bg-gray-50` included — reads as scoped.
    preg_match_all('/[a-z0-9@:\[\]\.\/_-]*(?:' . $banned . ')\b/i', $contents, $matches);

    return collect($matches[0])
        ->reject(fn (string $token): bool => str_contains($token, 'dark:'))
        ->map(fn (string $token): string => ltrim($token, '-'))
        ->unique()
        ->values()
        ->all();
}

it('writes back-office text and surfaces in the theme vocabulary, never a raw one', function (): void {
    $offenders = [];

    foreach (themeVocabularyBladeFiles() as $path) {
        $relative = str_replace(resource_path('views/'), '', $path);
        $allowed = THEME_BLIND_ON_PURPOSE[$relative] ?? [];

        $found = array_values(array_diff(themeBlindClassesIn((string) file_get_contents($path)), $allowed));

        if ($found !== []) {
            $offenders[] = $relative . ' → ' . implode(', ', $found);
        }
    }

    expect($offenders)->toBe([], implode("\n", array_merge(
        ['Theme-blind classes left in the back office:'],
        $offenders,
        [
            '',
            'text-base-content for emphasis, text-muted for supporting text,',
            'bg-base-100 / bg-base-200 for surfaces, divide-base-300 for rules.',
            'A class that has to stay light belongs in THEME_BLIND_ON_PURPOSE, with its reason.',
        ],
    )));
});

/*
 * The exemption list is a decision, not a parking space: an entry that no
 * longer matches anything in its file is a rule someone has already satisfied,
 * and leaving it there quietly re-opens the hole for the next edit.
 */
it('keeps no stale entry in the theme-blind exemption list', function (): void {
    $stale = [];

    foreach (THEME_BLIND_ON_PURPOSE as $relative => $classes) {
        $path = resource_path('views/' . $relative);

        if (! file_exists($path)) {
            $stale[] = $relative . ' → the file no longer exists';

            continue;
        }

        $found = themeBlindClassesIn((string) file_get_contents($path));

        foreach (array_diff($classes, $found) as $class) {
            $stale[] = $relative . ' → no longer uses ' . $class;
        }
    }

    expect($stale)->toBe([], "Stale exemptions; drop them so the rule applies again:\n" . implode("\n", $stale));
});
