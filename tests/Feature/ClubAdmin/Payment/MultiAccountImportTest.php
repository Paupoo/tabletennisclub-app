<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\ImportBankStatementAction;
use App\Domains\ClubAdmin\Payment\Models\BankAccount;
use App\Domains\ClubAdmin\Payment\Models\BankImport;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\BankAccountType;
use App\Exceptions\UnknownBankAccount;
use Database\Seeders\TreasurySeeder;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Club::factory()->ownClub()->create(['bank_account' => 'BE68539007547034']);
    $this->actingAs(User::factory()->isAdmin()->create());
});

function maStatement(string $name): string
{
    return base_path('tests/Fixtures/BankStatements/' . $name);
}

it('files the quick export under the club current account, with each line balance and statement number', function (): void {
    (new ImportBankStatementAction)(maStatement('cbc-quick-export.csv'));

    $account = BankAccount::sole();

    expect($account->iban)->toBe('BE68539007547034')
        ->and($account->type)->toBe(BankAccountType::Current)
        ->and(Transaction::orderBy('id')->get()->map(fn (Transaction $t): array => [
            $t->bank_account_id, $t->balance_after, $t->statement_number,
        ])->all())->toBe([
            [$account->id, 9109.3, '2026094'],
            [$account->id, 10343.8, '2026094'],
            [$account->id, 10047.8, '2026094'],
            [$account->id, 10043.3, '2026094'],
        ]);
});

it('reads the balance and statement number of the period export too', function (): void {
    (new ImportBankStatementAction)(maStatement('cbc-period-export.csv'));

    expect(Transaction::orderBy('id')->get()->map(fn (Transaction $t): array => [$t->balance_after, $t->statement_number])->all())
        ->toBe([
            [9084.3, '2026077'],
            [9109.3, '2026077'],
            [7813.3, '2026077'],
        ]);
});

it('fills in the balance and statement number of lines imported before they were read, and creates nothing', function (): void {
    (new ImportBankStatementAction)(maStatement('cbc-period-export.csv'));
    Transaction::query()->update(['balance_after' => null, 'statement_number' => null, 'bank_account_id' => null]);

    $import = (new ImportBankStatementAction)(maStatement('cbc-period-export.csv'));

    expect($import->new_count)->toBe(0)
        ->and($import->duplicate_count)->toBe(3)
        ->and(Transaction::count())->toBe(3)
        ->and(Transaction::orderBy('id')->get()->map(fn (Transaction $t): array => [$t->bank_account_id, $t->balance_after, $t->statement_number])->all())
        ->toBe([
            [BankAccount::sole()->id, 9084.3, '2026077'],
            [BankAccount::sole()->id, 9109.3, '2026077'],
            [BankAccount::sole()->id, 7813.3, '2026077'],
        ]);
});

it('asks about an account the club has not registered, and imports nothing', function (): void {
    Club::ourClub()->first()->update(['bank_account' => null]);

    try {
        (new ImportBankStatementAction)(maStatement('cbc-quick-export.csv'));
        $this->fail('The import went through.');
    } catch (UnknownBankAccount $e) {
        expect($e->ibans)->toBe(['BE68539007547034']);
    }

    expect(Transaction::count())->toBe(0)
        ->and(BankImport::count())->toBe(0)
        ->and(BankAccount::count())->toBe(0);
});

it('files a statement under the account the treasurer registered for it', function (): void {
    $savings = BankAccount::factory()->savings()->create(['iban' => 'BE71 0961 2345 6769']);
    $path = tempnam(sys_get_temp_dir(), 'bank');
    file_put_contents($path, implode("\n", [
        'Numéro de compte;Date;Montant;Solde;Description;Numéro de compte contrepartie;Communication libre',
        'BE71 0961 2345 6769;31/12/2026;12,34;5012,34;INTERETS;;',
    ]));

    (new ImportBankStatementAction)($path);

    expect(Transaction::sole()->bank_account_id)->toBe($savings->id);
});

it('marks a transfer to another club account as internal', function (): void {
    BankAccount::factory()->savings()->create(['iban' => 'BE98001062275793']);

    (new ImportBankStatementAction)(maStatement('cbc-quick-export.csv'));

    expect(Transaction::orderBy('id')->pluck('is_internal')->all())->toBe([false, false, true, false]);
});

it('tells the balance of an account at a date from the last line on or before it', function (): void {
    (new ImportBankStatementAction)(maStatement('cbc-quick-export.csv'));
    (new ImportBankStatementAction)(maStatement('cbc-period-export.csv'));

    $account = BankAccount::sole();

    expect($account->balanceAt(Carbon::parse('2026-07-01')))->toBeNull()
        ->and($account->balanceAt(Carbon::parse('2026-07-14')))->toBe(9084.3)
        ->and($account->balanceAt(Carbon::parse('2026-08-31')))->toBe(7813.3)
        ->and($account->balanceAt(Carbon::parse('2026-09-28')))->toBe(10043.3)
        ->and($account->balanceLineAt(Carbon::parse('2026-12-31'))?->date->toDateString())->toBe('2026-09-28');
});

it('seeds the demo treasury on a current account whose balance is known at every line', function (): void {
    User::factory()->create(['email' => 'gilles.herpigny@test.com']);
    Season::factory()->create();

    $this->seed(TreasurySeeder::class);

    $account = BankAccount::where('iban', 'BE68539007547034')->sole();

    expect($account->type)->toBe(BankAccountType::Current)
        ->and(Transaction::whereNull('bank_account_id')->orWhereNull('balance_after')->count())->toBe(0)
        ->and($account->balanceAt(Carbon::tomorrow()))->not->toBeNull();
});
