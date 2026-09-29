<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\BankAccount;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\BankAccountType;
use App\Domains\Shared\Enums\Role;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const BAS_COMPONENT = 'pages::club-admin.treasury.transactions';

beforeEach(function (): void {
    Club::factory()->ownClub()->create(['bank_account' => null]);
});

function basTreasurer(): User
{
    $treasurer = User::factory()->create();
    $treasurer->assignRole(Role::TREASURY->value);

    return $treasurer;
}

function basQuickExport(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'export.csv',
        (string) file_get_contents(base_path('tests/Fixtures/BankStatements/cbc-quick-export.csv')),
    );
}

function basImportingUnknownAccount(): Testable
{
    return Livewire::actingAs(basTreasurer())
        ->test(BAS_COMPONENT)
        ->set('importModal', true)
        ->set('importFile', basQuickExport())
        ->call('processImport');
}

it('asks the treasurer about a statement of an unregistered account instead of importing it', function (): void {
    basImportingUnknownAccount()
        ->assertSet('unknownAccountIban', 'BE68539007547034')
        ->assertSet('importModal', true);

    expect(Transaction::count())->toBe(0);
});

it('imports the statement once the treasurer registers its account', function (): void {
    basImportingUnknownAccount()
        ->set('newAccountName', 'Compte épargne')
        ->set('newAccountType', BankAccountType::Savings->value)
        ->call('registerAccountAndImport')
        ->assertHasNoErrors()
        ->assertSet('unknownAccountIban', null)
        ->assertSet('importModal', false);

    $account = BankAccount::sole();

    expect([$account->iban, $account->name, $account->type])->toBe(['BE68539007547034', 'Compte épargne', BankAccountType::Savings])
        ->and(Transaction::where('bank_account_id', $account->id)->count())->toBe(4);
});

it('wants a name for the account it registers', function (): void {
    basImportingUnknownAccount()
        ->set('newAccountName', '')
        ->call('registerAccountAndImport')
        ->assertHasErrors(['newAccountName' => 'required']);

    expect(BankAccount::count())->toBe(0);
});

it('marks as internal the lines already imported that went to the account just registered', function (): void {
    $toSavings = Transaction::create([
        'date' => '2026-09-01',
        'description' => 'VIREMENT VERS EPARGNE',
        'amount' => -500.0,
        'counterparty_bank_account' => 'BE68 5390 0754 7034',
    ]);
    $toSupplier = Transaction::create([
        'date' => '2026-09-02',
        'description' => 'VIREMENT',
        'amount' => -50.0,
        'counterparty_bank_account' => 'BE98 0010 6227 5793',
    ]);

    basImportingUnknownAccount()
        ->set('newAccountName', 'Épargne')
        ->set('newAccountType', BankAccountType::Savings->value)
        ->call('registerAccountAndImport');

    expect($toSavings->fresh()->is_internal)->toBeTrue()
        ->and($toSupplier->fresh()->is_internal)->toBeFalse();
});

it('lets only an importer register an account', function (): void {
    $member = User::factory()->create();
    $member->assignRole(Role::COMMITTEE->value);

    Livewire::actingAs($member)
        ->test(BAS_COMPONENT)
        ->set('unknownAccountIban', 'BE68539007547034')
        ->set('newAccountName', 'Épargne')
        ->call('registerAccountAndImport')
        ->assertForbidden();

    expect(BankAccount::count())->toBe(0);
});

it('lists the lines of one account', function (): void {
    $current = BankAccount::factory()->create();
    $savings = BankAccount::factory()->savings()->create();
    $onCurrent = Transaction::create(['date' => '2026-09-01', 'description' => 'A', 'amount' => 10.0, 'bank_account_id' => $current->id]);
    Transaction::create(['date' => '2026-09-02', 'description' => 'B', 'amount' => 20.0, 'bank_account_id' => $savings->id]);

    $screen = Livewire::actingAs(basTreasurer())
        ->test(BAS_COMPONENT)
        ->set('accountFilter', (string) $current->id);

    expect(collect($screen->viewData('transactions')->items())->pluck('id')->all())->toBe([$onCurrent->id])
        ->and($screen->viewData('filterChips'))->toContain(['key' => 'accountFilter', 'label' => $current->name]);
});

it('shows each line with its account, statement number and balance, and internal transfers as such', function (): void {
    $current = BankAccount::factory()->create(['name' => 'Compte courant']);
    $savings = BankAccount::factory()->savings()->create(['name' => 'Compte épargne']);
    Transaction::create([
        'date' => '2026-09-01', 'description' => 'VERS EPARGNE', 'amount' => -500.0, 'is_internal' => true,
        'bank_account_id' => $current->id, 'balance_after' => 7813.3, 'statement_number' => '2026077',
    ]);
    Transaction::create([
        'date' => '2026-09-01', 'description' => 'DEPUIS COURANT', 'amount' => 500.0, 'is_internal' => true,
        'bank_account_id' => $savings->id,
    ]);

    Livewire::actingAs(basTreasurer())
        ->test(BAS_COMPONENT)
        ->assertSee('Compte courant')
        ->assertSee('Compte épargne')
        ->assertSee(__('Stmt :number', ['number' => '2026077']))
        ->assertSee(__('Balance :amount €', ['amount' => '7 813,30']))
        ->assertSeeHtml('badge-neutral');
});

it('shows the question about an unregistered account in the import dialog', function (): void {
    basImportingUnknownAccount()
        ->assertSee(__('This statement is for account :account, which the club has not registered yet.', ['account' => 'BE68 5390 0754 7034']))
        ->assertSee(__('Register and import'));
});
