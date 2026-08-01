<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
|--------------------------------------------------------------------------
| Ghost namespaces
|--------------------------------------------------------------------------
|
| `App\Models\…` is left over from before the domain refactor: there is no
| app/Models directory any more, so every one of those names resolves to
| nothing. They survived because none of them is ever autoloaded —
| `can:update,App\Models\…\Club` reaches the right policy by accident, since
| Laravel's policy guesser walks namespace segments until it finds
| App\Policies\ClubPolicy. Rename a policy and the gate silently changes
| meaning; render one of the Blade files and it fatals.
|
| The same trap applies to `App\Services\Bar`, a stale copy of
| `App\Domains\Bar\Services` that referenced model classes that never existed.
|
*/

/**
 * @return array<int, string> Repo-relative paths mentioning a dead namespace.
 */
function filesReferencing(string $namespace): array
{
    $root = dirname(__DIR__, 2);

    $files = Finder::create()
        ->files()
        ->in([$root . '/app', $root . '/routes', $root . '/resources/views'])
        ->name(['*.php', '*.blade.php'])
        ->contains('/' . preg_quote($namespace, '/') . '/');

    return array_map(
        fn ($file): string => str_replace($root . '/', '', $file->getRealPath()),
        iterator_to_array($files, preserve_keys: false),
    );
}

it('has no app/Models directory', function (): void {
    expect(is_dir(dirname(__DIR__, 2) . '/app/Models'))->toBeFalse();
});

it('never names the App\\Models namespace that no longer exists', function (): void {
    expect(filesReferencing('App\\Models\\'))->toBe([]);
});

it('keeps a single copy of the bar services, under app/Domains', function (): void {
    expect(is_dir(dirname(__DIR__, 2) . '/app/Services/Bar'))->toBeFalse();
});
