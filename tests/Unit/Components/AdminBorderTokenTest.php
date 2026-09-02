<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

pest()->group('components', 'designSystem');

/*
 * One border colour in the back office.
 *
 * <x-card> draws itself with base-300 (app.css), so any hand-rolled surface that
 * wants to sit next to a card has to use the same grey — base-200 is the page
 * background, and a border painted in the background colour does not define
 * anything. Raw palette classes (border-gray-*) are worse still: they ignore the
 * theme and stay light grey on the dark surfaces.
 *
 * @return array<int, string>
 */
function adminBladeFiles(): array
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

it('paints every back-office border with the theme token, never a raw grey', function (): void {
    $offenders = [];

    foreach (adminBladeFiles() as $path) {
        $contents = (string) file_get_contents($path);

        if (preg_match_all('/border-gray-\d+/', $contents, $matches) > 0) {
            $offenders[] = str_replace(resource_path('views/'), '', $path) . ' → ' . implode(', ', array_unique($matches[0]));
        }
    }

    expect($offenders)->toBe([], "Raw grey borders left in the back office:\n" . implode("\n", $offenders));
});

it('uses one grey for back-office borders, the one the card draws', function (): void {
    $offenders = [];

    foreach (adminBladeFiles() as $path) {
        $contents = (string) file_get_contents($path);

        $count = preg_match_all('/(?:^|[\s:"\'])border-base-200\b/m', $contents);

        if ($count > 0) {
            $offenders[] = str_replace(resource_path('views/'), '', $path) . ' → ' . $count;
        }
    }

    expect($offenders)->toBe([], "base-200 borders left where the card draws base-300:\n" . implode("\n", $offenders));
});
