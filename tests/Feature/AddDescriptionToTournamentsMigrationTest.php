<?php

declare(strict_types=1);

use App\Domains\Competitions\Tournament\Models\Tournament;
use Illuminate\Support\Facades\Schema;

/*
 * `tournaments.description` was slipped into the create migration after
 * production had run it: production never got the column, and creating a
 * tournament broke once the wizard wrote to it. The column now has a migration
 * of its own, which databases built after the slip already satisfy.
 */

function runAddDescriptionToTournamentsMigration(): void
{
    $migration = require base_path('database/migrations/2026_10_21_192653_add_description_to_tournaments_table.php');
    $migration->up();
}

it('adds the column to a table that never had it', function (): void {
    Schema::table('tournaments', function ($table): void {
        $table->dropColumn('description');
    });

    runAddDescriptionToTournamentsMigration();

    expect(Schema::hasColumn('tournaments', 'description'))->toBeTrue();
});

it('leaves a table that already has the column alone', function (): void {
    runAddDescriptionToTournamentsMigration();

    expect(Schema::hasColumn('tournaments', 'description'))->toBeTrue();
});

it('never makes a description longer than the column and the wizard allow', function (): void {
    $lengths = collect(range(1, 200))->map(fn (): int => mb_strlen((string) Tournament::factory()->make()->description));

    expect($lengths->max())->toBeLessThanOrEqual(255);
});
