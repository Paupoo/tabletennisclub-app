<?php

declare(strict_types=1);

/**
 * Architecture tests for UI component conventions.
 *
 * Enforces the component system established after the views refactoring:
 * - No legacy button components (use x-button with daisyUI variants instead)
 * - Legacy component files must not exist
 */
it('does not use legacy button components in views', function (): void {
    $views = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views'))
    );

    $violations = [];

    foreach ($views as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getRealPath());
        $relativePath = str_replace(resource_path('views') . '/', '', $file->getRealPath());

        foreach (['x-primary-button', 'x-secondary-button', 'x-danger-button', 'x-green-button', 'x-important-button'] as $legacyComponent) {
            if (str_contains($content, "<{$legacyComponent}")) {
                $violations[] = "{$relativePath} uses <{$legacyComponent}>";
            }
        }
    }

    expect($violations)->toBeEmpty(
        "Legacy button components found:\n" . implode("\n", $violations) . "\nUse <x-button class=\"btn-primary|btn-error|btn-success|btn-warning|btn-ghost\"> instead."
    );
});

it('does not have legacy button component files on disk', function (): void {
    $legacyFiles = [
        resource_path('views/components/button.blade.php'),
        resource_path('views/components/primary-button.blade.php'),
        resource_path('views/components/secondary-button.blade.php'),
        resource_path('views/components/danger-button.blade.php'),
        resource_path('views/components/green-button.blade.php'),
        resource_path('views/components/important-button.blade.php'),
    ];

    $existing = array_filter($legacyFiles, fn ($path) => file_exists($path));

    expect($existing)->toBeEmpty(
        "Legacy button component files still exist:\n" . implode("\n", array_values($existing))
    );
});
