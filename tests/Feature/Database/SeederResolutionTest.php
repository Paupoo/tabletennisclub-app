<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Every seeder must still be resolvable
|--------------------------------------------------------------------------
|
| Seeders declare their collaborators in the constructor, so a class deleted
| during a refactor only surfaces when `db:seed` is actually run. That is how
| DatabaseSeeder kept injecting App\Services\ForceList long after the force
| list moved to RecalculateForceListAction: the container blew up on the very
| first seed, nowhere near the commit that caused it.
|
| Resolving each seeder is enough to catch that, and costs no database writes.
|
| The dataset is built with plain glob(): Pest resolves datasets before the
| application boots, so neither database_path() nor the File facade is usable
| here. Database\Seeders\Data holds value objects, not seeders, and the
| non-recursive glob leaves it out.
|
*/

it('resolves every seeder from the container', function (string $seeder): void {
    expect(app($seeder))->toBeInstanceOf($seeder);
})->with(fn () => collect(glob(dirname(__DIR__, 3) . '/database/seeders/*.php'))
    ->map(fn (string $path): string => 'Database\\Seeders\\' . basename($path, '.php'))
    ->all());
