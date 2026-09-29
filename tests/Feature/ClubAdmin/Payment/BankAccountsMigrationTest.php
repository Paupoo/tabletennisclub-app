<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\BankAccount;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\BankAccountType;
use Illuminate\Database\Migrations\Migration;

/**
 * Before this migration the club had one account, written on the club record,
 * and every imported line was implicitly on it.
 */
function bamMigration(): Migration
{
    return require database_path('migrations/2026_09_30_002440_create_bank_accounts_table.php');
}

function bamLine(string $date): void
{
    Transaction::create(['date' => $date, 'description' => 'VIREMENT', 'amount' => 10.0]);
}

it('turns the club account into the current account and files the existing lines under it', function (): void {
    Club::factory()->ownClub()->create(['bank_account' => 'BE68 5390 0754 7034']);
    bamLine('2026-09-01');
    bamLine('2026-09-02');
    $migration = bamMigration();
    $migration->down();

    $migration->up();

    $account = BankAccount::sole();

    expect($account->iban)->toBe('BE68539007547034')
        ->and($account->type)->toBe(BankAccountType::Current)
        ->and(Transaction::pluck('bank_account_id')->unique()->all())->toBe([$account->id]);
});

it('leaves the lines unfiled when the club has no account on record', function (): void {
    Club::factory()->ownClub()->create(['bank_account' => null]);
    bamLine('2026-09-01');
    bamLine('2026-09-02');
    $migration = bamMigration();
    $migration->down();

    $migration->up();

    expect(BankAccount::count())->toBe(0)
        ->and(Transaction::whereNull('bank_account_id')->count())->toBe(2);
});
