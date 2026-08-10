<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

pest()->group('components', 'designSystem');

/*
 * Indigo is not a club colour. It is what Breeze ships, and it survived in the
 * focus rings of every scaffolded form control — which is what a new member
 * sees first, on the sign-in, sign-up and e-mail-verification screens, on a
 * page that is otherwise club blue.
 *
 * The other off-brand families still in the codebase carry documented meaning
 * (status, player levels, league categories) and are deliberately left alone.
 */

/** @return array<int, string> */
function brandScannedFiles(): array
{
    $roots = [
        resource_path('views/components'),
        resource_path('views/pages'),
        resource_path('views/clubAdmin'),
        app_path('Http/Controllers/ClubAdmin'),
    ];

    return collect($roots)
        ->flatMap(fn (string $root): array => File::allFiles($root))
        ->filter(fn ($file): bool => in_array($file->getExtension(), ['php'], true))
        ->map(fn ($file): string => $file->getPathname())
        ->values()
        ->all();
}

it('has retired the Breeze indigo', function (): void {
    $offenders = [];

    foreach (brandScannedFiles() as $path) {
        if (preg_match_all('/[a-z:-]*indigo[a-z0-9\/-]*/', (string) File::get($path), $matches) > 0) {
            $offenders[] = str_replace(base_path() . '/', '', $path) . ' → ' . implode(', ', array_unique($matches[0]));
        }
    }

    expect($offenders)->toBe([], "Indigo left in the app:\n" . implode("\n", $offenders));
});

it('asks the dashboard controller for brand tile colours only', function (): void {
    $controller = (string) File::get(app_path('Http/Controllers/ClubAdmin/DashboardController.php'));

    preg_match_all("/'color'\s*=>\s*'([a-z-]+)'/", $controller, $matches);

    $offBrand = array_values(array_unique(array_diff($matches[1], ['primary', 'secondary', 'neutral'])));

    expect($offBrand)->toBe([], 'off-brand tile colours: ' . implode(', ', $offBrand));
});
