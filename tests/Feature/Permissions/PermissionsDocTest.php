<?php

declare(strict_types=1);

use App\Console\Commands\Docs\GeneratePermissionsDocCommand;

/*
| A hand-maintained table of 60 rights across 18 roles goes stale within a month
| and then quietly misleads. docs/permissions.md is rendered from the Role enum;
| this fails the moment the two drift, so changing the matrix without regenerating
| the doc cannot reach main.
|
| Lives in Feature rather than Architecture because rendering goes through __(),
| which needs the application container.
*/

it('keeps docs/permissions.md in step with the matrix', function (): void {
    $path = base_path('docs/permissions.md');

    expect(is_file($path))->toBeTrue('docs/permissions.md est absent — lancez `php artisan docs:permissions`.');

    expect(file_get_contents($path))->toBe(
        app(GeneratePermissionsDocCommand::class)->render(),
        'docs/permissions.md est désynchronisé — lancez `php artisan docs:permissions`.',
    );
});

it('exposes the same check through the command', function (): void {
    $this->artisan('docs:permissions', ['--check' => true])->assertSuccessful();
});
