<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\ImportBankStatementAction;
use App\Actions\ClubAdmin\Payments\ResolveSuspectedDuplicateAction;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;

beforeEach(function (): void {
    Club::factory()->ownClub()->create(['bank_account' => 'BE68539007547034']);
    $this->actingAs(User::factory()->isAdmin()->create());
});

function statementSample(string $name): string
{
    return base_path('tests/Fixtures/BankStatements/' . $name);
}

it('keeps the fingerprints the importer has always computed, so a re-import stays a duplicate', function (): void {
    (new ImportBankStatementAction)(statementSample('cbc-period-export.csv'));

    // Calculées par l'import d'avant la refonte, sur le même fichier.
    expect(Transaction::orderBy('id')->pluck('import_fingerprint')->all())->toBe([
        'f9ee47284f25c8943a01dce9eaf16e0cec316ef01e5cfd574c874ba8497ef264',
        'a28c93b095abb8740df469b348ac2aa2a4bb3ae66d16ab16d8f6b1057834c3b8',
        '81a7f7ecbc504b34dd66a3a3d382e0715a013148f239f93767230617ecaa0db3',
    ]);
});

it('imports the quick export whose lines end with a lone carriage return', function (): void {
    $import = (new ImportBankStatementAction)(statementSample('cbc-quick-export.csv'));

    expect($import->new_count)->toBe(4)
        ->and(Transaction::orderBy('id')->get()->map(fn (Transaction $t): array => [$t->date->format('Y-m-d'), $t->amount])->all())
        ->toBe([
            ['2026-07-15', 25.0],
            ['2026-09-28', 1234.5],
            ['2026-09-28', -296.0],
            ['2026-09-28', -4.5],
        ]);
});

it('refuses a statement of another account, and writes nothing', function (): void {
    Club::ourClub()->first()->update(['bank_account' => 'BE71096123456769']);

    expect(fn (): mixed => (new ImportBankStatementAction)(statementSample('cbc-quick-export.csv')))
        ->toThrow(DomainException::class, 'BE68 5390 0754 7034');

    expect(Transaction::count())->toBe(0);
});

it('recognises the club account whether or not the bank spaces it', function (): void {
    $import = (new ImportBankStatementAction)(statementSample('cbc-period-export.csv'));

    expect($import->new_count)->toBe(3);
});

it('sets aside a line that looks like a transfer already imported from the other export', function (): void {
    (new ImportBankStatementAction)(statementSample('cbc-period-export.csv'));
    $existing = Transaction::whereDate('date', '2026-07-15')->sole();

    $import = (new ImportBankStatementAction)(statementSample('cbc-quick-export.csv'));

    expect($import->new_count)->toBe(3)
        ->and($import->error_count)->toBe(0)
        ->and(Transaction::whereDate('date', '2026-07-15')->count())->toBe(1)
        ->and($import->suspectedDuplicates())->toHaveCount(1)
        ->and($import->suspectedDuplicates()[0])->toMatchArray([
            'kind' => 'suspected_duplicate',
            'line' => 2,
            'transaction_id' => $existing->id,
        ]);
});

it('imports two look-alike lines of the same file, since a statement never repeats itself', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'bank');
    file_put_contents($path, implode("\n", [
        'Date;Montant;Description;Numéro de compte contrepartie;Communication libre',
        '26/09/2026;3,00;VIREMENT;BE43 7320 1184 8401;BAR ORDER 33',
        '26/09/2026;3,00;VIREMENT;BE43 7320 1184 8401;BAR ORDER 34',
    ]));

    $import = (new ImportBankStatementAction)($path);

    expect($import->new_count)->toBe(2)
        ->and($import->suspectedDuplicates())->toBe([]);
});

describe('settling a line set aside', function (): void {
    beforeEach(function (): void {
        (new ImportBankStatementAction)(statementSample('cbc-period-export.csv'));
        $this->import = (new ImportBankStatementAction)(statementSample('cbc-quick-export.csv'));
    });

    it('imports it after all when the treasurer says it is a distinct transfer', function (): void {
        $transaction = (new ResolveSuspectedDuplicateAction)($this->import, line: 2, keep: true);

        $this->import->refresh();

        expect(Transaction::whereDate('date', '2026-07-15')->count())->toBe(2)
            ->and($transaction->bank_import_id)->toBe($this->import->id)
            ->and($transaction->free_reference)->toBe('T-shirt club')
            ->and($this->import->suspectedDuplicates())->toBe([])
            ->and($this->import->new_count)->toBe(4);
    });

    it('drops it when the treasurer confirms it is the same transfer', function (): void {
        $transaction = (new ResolveSuspectedDuplicateAction)($this->import, line: 2, keep: false);

        $this->import->refresh();

        expect($transaction)->toBeNull()
            ->and(Transaction::whereDate('date', '2026-07-15')->count())->toBe(1)
            ->and($this->import->suspectedDuplicates())->toBe([])
            ->and($this->import->duplicate_count)->toBe(1);
    });

    it('refuses a line that is no longer waiting', function (): void {
        (new ResolveSuspectedDuplicateAction)($this->import, line: 2, keep: true);

        expect(fn (): mixed => (new ResolveSuspectedDuplicateAction)($this->import->refresh(), line: 2, keep: true))
            ->toThrow(DomainException::class);

        expect(Transaction::whereDate('date', '2026-07-15')->count())->toBe(2);
    });
});
