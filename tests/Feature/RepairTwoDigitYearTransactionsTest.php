<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Transaction;

function runRepairTwoDigitYearMigration(): void
{
    $migration = require base_path('database/migrations/2026_10_05_225311_repair_transactions_dated_in_a_two_digit_year.php');
    $migration->up();
}

it('gives back their real date to the lines a statement dated « 22-09-26 » filed in the year 22', function (): void {
    // « 22-09-26 » read as Y-m-d: the bank's 22 September 2026.
    $misread = Transaction::create(['date' => '0022-09-26', 'description' => 'BAR ORDER 20', 'amount' => 4.5]);
    $sound = Transaction::create(['date' => '2026-09-28', 'description' => 'BAR ORDER 46', 'amount' => 25]);

    runRepairTwoDigitYearMigration();

    expect($misread->fresh()->date->format('Y-m-d'))->toBe('2026-09-22')
        ->and($sound->fresh()->date->format('Y-m-d'))->toBe('2026-09-28');
});
