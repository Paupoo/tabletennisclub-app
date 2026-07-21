<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\BankImport;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

// ── Helpers ────────────────────────────────────────────────────────────────────

function makeCsvContent(array $rows): string
{
    $header = 'Date;Montant;Description;Nom contrepartie;Numéro de compte contrepartie;Communication structurée;Communication libre';
    $lines = array_map(fn ($r) => implode(';', $r), $rows);

    return implode("\n", [$header, ...$lines]);
}

function makeCsvFile(array $rows, string $name = 'bank.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, makeCsvContent($rows));
}

function adminUser(): User
{
    return User::factory()->isAdmin()->create();
}

// ── Import scenarios ───────────────────────────────────────────────────────────

describe('Transaction import', function (): void {

    it('imports new transactions and creates a BankImport record', function (): void {
        $admin = adminUser();
        $rows = [
            ['01/06/2026', '50,00', 'Cotisation', 'Jean Dupont', 'BE12345678901234', '+++123/456/789++', ''],
            ['02/06/2026', '-25,00', 'Remboursement', 'Club', 'BE98765432109876', '', 'Refund test'],
        ];

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.transactions')
            ->set('importFile', makeCsvFile($rows))
            ->call('processImport');

        expect(Transaction::count())->toBe(2);
        expect(BankImport::count())->toBe(1);

        $import = BankImport::first();
        expect($import->new_count)->toBe(2);
        expect($import->duplicate_count)->toBe(0);
        expect($import->error_count)->toBe(0);
        expect($import->user_id)->toBe($admin->id);
    });

    it('stores amounts in cents', function (): void {
        $admin = adminUser();
        $rows = [
            ['01/06/2026', '50,25', 'Test', 'John', 'BE123', '', ''],
        ];

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.transactions')
            ->set('importFile', makeCsvFile($rows))
            ->call('processImport');

        $transaction = Transaction::first();
        expect($transaction->getRawOriginal('amount'))->toBe(5025);
        expect($transaction->amount)->toBe(50.25);
    });

    it('skips duplicate rows when importing the same file twice', function (): void {
        $admin = adminUser();
        $rows = [
            ['01/06/2026', '50,00', 'Cotisation', 'Jean Dupont', 'BE12345678901234', '+++123/456/789++', ''],
            ['02/06/2026', '-25,00', 'Remboursement', 'Club', 'BE98765432109876', '', 'Refund'],
        ];

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.transactions')
            ->set('importFile', makeCsvFile($rows))
            ->call('processImport');

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.transactions')
            ->set('importFile', makeCsvFile($rows))
            ->call('processImport');

        expect(Transaction::count())->toBe(2);
        expect(BankImport::count())->toBe(2);

        $secondImport = BankImport::orderByDesc('id')->first();
        expect($secondImport->new_count)->toBe(0);
        expect($secondImport->duplicate_count)->toBe(2);
    });

    it('imports only new rows when files overlap', function (): void {
        $admin = adminUser();

        $firstRows = [
            ['01/06/2026', '50,00', 'First', 'Jean', 'BE11', '', ''],
            ['02/06/2026', '30,00', 'Second', 'Paul', 'BE22', '', ''],
        ];
        $overlappingRows = [
            ['02/06/2026', '30,00', 'Second', 'Paul', 'BE22', '', ''],  // duplicate
            ['03/06/2026', '20,00', 'Third', 'Marc', 'BE33', '', ''],   // new
        ];

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.transactions')
            ->set('importFile', makeCsvFile($firstRows))
            ->call('processImport');

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.transactions')
            ->set('importFile', makeCsvFile($overlappingRows))
            ->call('processImport');

        expect(Transaction::count())->toBe(3);

        $secondImport = BankImport::orderByDesc('id')->first();
        expect($secondImport->new_count)->toBe(1);
        expect($secondImport->duplicate_count)->toBe(1);
    });

    it('records failed rows and continues importing valid ones', function (): void {
        $admin = adminUser();
        $rows = [
            ['01/06/2026', '50,00', 'Valid row', 'Jean', 'BE11', '', ''],
            ['', '50,00', 'Row with missing date', 'Paul', 'BE22', '', ''],  // null date → DB NOT NULL violation
            ['03/06/2026', '20,00', 'Another valid', 'Marc', 'BE33', '', ''],
        ];

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.transactions')
            ->set('importFile', makeCsvFile($rows))
            ->call('processImport');

        $import = BankImport::first();
        expect($import->new_count)->toBe(2);
        expect($import->error_count)->toBe(1);
        expect($import->failed_rows)->not->toBeNull();
        expect($import->failed_rows[0]['line'])->toBe(3);
    });

    it('stores a SHA-256 fingerprint on each transaction', function (): void {
        $admin = adminUser();
        $rows = [
            ['01/06/2026', '50,00', 'Cotisation', 'Jean', 'BE11', '+++123/456/789++', ''],
        ];

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.transactions')
            ->set('importFile', makeCsvFile($rows))
            ->call('processImport');

        $transaction = Transaction::first();
        expect($transaction->import_fingerprint)->not->toBeNull();
        expect(strlen($transaction->import_fingerprint))->toBe(64);
    });

    it('reads accented headers and values from an ISO-8859-1 encoded file', function (): void {
        $admin = adminUser();

        $header = 'Date;Montant;Description;Nom contrepartie;Numéro de compte contrepartie;Communication structurée;Communication libre';
        $row = '01/06/2026;125,00;Cotisation;Chloé Luyten;BE12345678901234;018/0626/08860;';
        $latin1Content = iconv('UTF-8', 'ISO-8859-1', implode("\n", [$header, $row]));

        $file = UploadedFile::fake()->createWithContent('bank_latin1.csv', $latin1Content);

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.transactions')
            ->set('importFile', $file)
            ->call('processImport');

        $transaction = Transaction::first();

        expect($transaction->counterparty_name)->toBe('Chloé Luyten');
        expect($transaction->structured_reference)->toBe('018/0626/08860');
    });

    it('links transactions to their BankImport', function (): void {
        $admin = adminUser();
        $rows = [
            ['01/06/2026', '50,00', 'Test', 'Jean', 'BE11', '', ''],
        ];

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.transactions')
            ->set('importFile', makeCsvFile($rows))
            ->call('processImport');

        $import = BankImport::first();
        $transaction = Transaction::first();

        expect($transaction->bank_import_id)->toBe($import->id);
        expect($import->transactions()->count())->toBe(1);
    });
});

// ── Transaction model ──────────────────────────────────────────────────────────

describe('Transaction amount accessor', function (): void {
    it('converts euros to cents on set and back on get', function (): void {
        $t = new Transaction;
        $t->amount = 42.50;

        expect($t->getAttributes()['amount'])->toBe(4250);
        expect($t->amount)->toBe(42.5);
    });

    it('handles negative amounts correctly', function (): void {
        $t = new Transaction;
        $t->amount = -10.62;

        expect($t->getAttributes()['amount'])->toBe(-1062);
        expect($t->amount)->toBe(-10.62);
    });
});
