<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\LinkCashDepositAction;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\LinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('local');
});

function crdEntry(int $cents, string $reason = 'Ticket Colruyt'): CashRegisterEntry
{
    return CashRegisterEntry::create([
        'cash_register_id' => CashRegister::firstOrCreate(['name' => 'Caisse du club'])->id,
        'amount' => $cents,
        'reason' => $reason,
        'recorded_by_id' => User::factory()->create()->id,
    ]);
}

function crdScreen(Role ...$roles): Testable
{
    return Livewire::actingAs(User::factory()->withRole(...$roles)->create())->test('pages::club-admin.treasury.cash-register');
}

it('lets the cash register délégation file the ticket of a till movement', function (): void {
    $entry = crdEntry(-2350);

    crdScreen(Role::CASH_REGISTER)
        ->call('openEntryDocuments', $entry->id)
        ->assertSet('documentAmount', '23.50')
        ->set('documentCategory', 'expense:' . ExpenseCategory::Bar->value)
        ->set('documentCounterparty', 'Colruyt')
        ->set('documentLabel', 'Courses du bar')
        ->set('documentFiles', [UploadedFile::fake()->image('ticket.jpg')])
        ->call('createAndLinkEntryDocument')
        ->assertHasNoErrors();

    expect($entry->fresh()->isJustified())->toBeTrue()
        ->and(SupportingDocument::sole()->amount)->toBe(23.5);
});

it('suggests the document of the same amount to a till movement', function (): void {
    $entry = crdEntry(-2350);
    $ticket = SupportingDocument::factory()->expense(ExpenseCategory::Bar)->create(['amount' => 23.5, 'date' => now()->toDateString()]);

    $screen = crdScreen(Role::CASH_REGISTER)->call('openEntryDocuments', $entry->id);

    expect($screen->instance()->entryDocumentSuggestions()->modelKeys())->toBe([$ticket->id]);

    $screen->call('linkEntryDocument', $ticket->id);

    expect($entry->fresh()->isJustified())->toBeTrue();
});

it('never lets the cash register délégation link a document that justifies a bank line', function (): void {
    $entry = crdEntry(-2350);
    $invoice = SupportingDocument::factory()->create(['amount' => 23.5, 'counterparty' => 'Imprimerie']);
    (new LinkSupportingDocument)($invoice, Transaction::create(['date' => now()->toDateString(), 'description' => 'X', 'amount' => -23.5]));

    $screen = crdScreen(Role::CASH_REGISTER)->call('openEntryDocuments', $entry->id)->set('entryDocumentSearch', 'Imprimerie');

    expect($screen->instance()->entryDocumentSearchResults()->modelKeys())->toBe([]);

    $screen->call('linkEntryDocument', $invoice->id)->assertForbidden();
});

it('links a till deposit to its bank line, and unlinks it', function (): void {
    $entry = crdEntry(-50000, 'Versement du bar');
    $credit = Transaction::create(['date' => now()->toDateString(), 'description' => 'VERSEMENT ESPECES', 'amount' => 500]);
    Transaction::create(['date' => now()->toDateString(), 'description' => 'AUTRE', 'amount' => -500]);

    $screen = crdScreen(Role::TREASURY, Role::CASH_REGISTER)->call('openDeposit', $entry->id);

    expect($screen->instance()->depositSuggestions()->modelKeys())->toBe([$credit->id]);

    $screen->call('linkDeposit', $credit->id);
    expect($credit->fresh()->is_internal)->toBeTrue()
        ->and($entry->fresh()->isInternal())->toBeTrue();

    $screen->call('unlinkDeposit', $entry->id);
    expect($credit->fresh()->is_internal)->toBeFalse();
});

it('keeps the bank deposit to the treasury', function (): void {
    $entry = crdEntry(-50000);

    crdScreen(Role::CASH_REGISTER)->call('openDeposit', $entry->id)->assertForbidden();
});

it('tells a justified movement and a bank deposit apart in the history', function (): void {
    $ticket = crdEntry(-2350);
    (new LinkSupportingDocument)(SupportingDocument::factory()->create(['amount' => 23.5]), $ticket);
    $deposit = crdEntry(-50000, 'Versement du bar');
    (new LinkCashDepositAction)(Transaction::create(['date' => now()->toDateString(), 'description' => 'X', 'amount' => 500]), $deposit);
    crdEntry(-800, 'Affiches');

    $this->actingAs(User::factory()->withRole(Role::TREASURY, Role::CASH_REGISTER)->create())
        ->get(route('admin.treasury.cash'))
        ->assertOk()
        ->assertSeeText(__('Bank deposit'))
        ->assertSeeText(__('Justified'))
        ->assertSeeText(__('Unlink from the bank'));
});
