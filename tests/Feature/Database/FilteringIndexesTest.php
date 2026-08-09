<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Indexes on the columns the hot screens filter by
|--------------------------------------------------------------------------
|
| InnoDB indexes foreign keys by itself, so the joins were always covered. The
| status and date columns the treasury and roster screens narrow by were not.
|
*/

it('indexes the columns the treasury and roster screens filter on', function (string $table, array $columns): void {
    expect(Schema::hasIndex($table, $columns))->toBeTrue();
})->with([
    'subscriptions by season and status' => ['subscriptions', ['season_id', 'status']],
    'payments by status' => ['payments', ['status']],
    'contacts by status' => ['contacts', ['status']],
    'seasons by date range' => ['seasons', ['start_at', 'end_at']],
])->group('database');
