<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\LinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\UnlinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\SupportingDocumentState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * A bank line a supporting document explains is closed: the third way out of
 * the list to handle, next to a full allocation and a written-off residue.
 */
beforeEach(function (): void {
    Storage::fake('local');
});

function jtLine(float $amount, string $date = '2026-09-10'): Transaction
{
    return Transaction::create([
        'date' => $date,
        'description' => 'VIREMENT',
        'amount' => $amount,
        'counterparty_name' => 'Tibhar Belgium',
    ]);
}

/** @return Collection<int, int> */
function jtIdsUnder(string $filter): Collection
{
    return collect(Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-admin.treasury.transactions')
        ->set('reconciledFilter', $filter)
        ->viewData('transactions')->items())->pluck('id')->sort()->values();
}

it('closes a line justified by a document, and files it under settled and justified', function (): void {
    $justified = jtLine(-89.4);
    $untouched = jtLine(-18.5);
    $document = SupportingDocument::factory()->expense(ExpenseCategory::SportsEquipment)->create(['amount' => 89.4]);

    (new LinkSupportingDocument)($document, $justified);

    expect($justified->fresh()->isSettled())->toBeTrue()
        ->and($document->fresh()->state())->toBe(SupportingDocumentState::Settled)
        ->and(jtIdsUnder('justified')->all())->toBe([$justified->id])
        ->and(jtIdsUnder('reconciled')->all())->toBe([$justified->id])
        ->and(jtIdsUnder('unreconciled')->all())->toBe([$untouched->id]);
});

it('puts a line back to handle when its last document is unlinked', function (): void {
    $line = jtLine(-89.4);
    $document = SupportingDocument::factory()->create(['amount' => 89.4]);
    (new LinkSupportingDocument)($document, $line);

    (new UnlinkSupportingDocument)($document, $line);

    expect($line->fresh()->isSettled())->toBeFalse()
        ->and($document->fresh()->state())->toBe(SupportingDocumentState::ToSettle)
        ->and(jtIdsUnder('unreconciled')->all())->toBe([$line->id]);
});

it('never offers a justified line to the website\'s reconciliation', function (): void {
    $line = jtLine(120.0);
    (new LinkSupportingDocument)(SupportingDocument::factory()->create(), $line);

    expect(Transaction::reconcilable()->whereKey($line->id)->exists())->toBeFalse();
});

it('refuses to allocate a website payment on a justified line', function (): void {
    $line = jtLine(120.0);
    (new LinkSupportingDocument)(SupportingDocument::factory()->create(), $line);
    $subscription = Subscription::factory()->create(['amount_due' => 120]);
    $payment = $subscription->payments()->create(['reference' => '700/0000/00001', 'amount_due' => 120, 'amount_paid' => 0, 'status' => 'pending']);

    (new AllocateTransactionAction)($line, [$payment->id => 120.0]);
})->throws(DomainException::class);

it('refuses a document on a line already allocated to the website\'s payments', function (): void {
    $line = jtLine(120.0);
    $subscription = Subscription::factory()->create(['amount_due' => 60]);
    $payment = $subscription->payments()->create(['reference' => '700/0000/00002', 'amount_due' => 60, 'amount_paid' => 0, 'status' => 'pending']);
    (new AllocateTransactionAction)($line, [$payment->id => 60.0]);

    (new LinkSupportingDocument)(SupportingDocument::factory()->create(), $line->fresh());
})->throws(DomainException::class);

it('refuses a document on an internal transfer', function (): void {
    $line = Transaction::create(['date' => '2026-09-10', 'description' => 'VERS EPARGNE', 'amount' => -500, 'is_internal' => true]);

    (new LinkSupportingDocument)(SupportingDocument::factory()->create(), $line);
})->throws(DomainException::class);

it('refuses a document on a cash movement the website accounts for', function (): void {
    $register = CashRegister::create(['name' => 'Caisse']);
    $subscription = Subscription::factory()->create();
    $entry = CashRegisterEntry::create([
        'cash_register_id' => $register->id,
        'amount' => 12000,
        'reason' => 'Cotisation',
        'payable_type' => $subscription->getMorphClass(),
        'payable_id' => $subscription->id,
        'recorded_by_id' => User::factory()->create()->id,
    ]);

    (new LinkSupportingDocument)(SupportingDocument::factory()->create(), $entry);
})->throws(DomainException::class);

it('settles a document paid from the till', function (): void {
    $register = CashRegister::create(['name' => 'Caisse']);
    $entry = CashRegisterEntry::create([
        'cash_register_id' => $register->id,
        'amount' => -2350,
        'reason' => 'Ticket Colruyt',
        'recorded_by_id' => User::factory()->create()->id,
    ]);
    $document = SupportingDocument::factory()->expense(ExpenseCategory::Bar)->create(['amount' => 23.5]);

    (new LinkSupportingDocument)($document, $entry);

    expect($document->fresh()->state())->toBe(SupportingDocumentState::Settled)
        ->and($document->fresh()->hasAmountMismatch())->toBeFalse()
        ->and($entry->fresh()->isJustified())->toBeTrue();
});

it('warns, without refusing, when the linked money does not add up to the document', function (): void {
    $document = SupportingDocument::factory()->create(['amount' => 100]);

    (new LinkSupportingDocument)($document, jtLine(-60.0));

    expect($document->fresh()->hasAmountMismatch())->toBeTrue()
        ->and($document->fresh()->linkedAmount())->toBe(60.0);
});

it('shares a debit out between the categories of its documents, pro rata', function (): void {
    $debit = jtLine(-400.0);
    (new LinkSupportingDocument)(SupportingDocument::factory()->expense(ExpenseCategory::Hall)->create(['amount' => 300]), $debit);
    (new LinkSupportingDocument)(SupportingDocument::factory()->expense(ExpenseCategory::SportsEquipment)->create(['amount' => 100]), $debit);

    expect($debit->fresh()->categoryShares())->toEqualCanonicalizing([
        ['category' => ExpenseCategory::Hall, 'amount' => -300.0],
        ['category' => ExpenseCategory::SportsEquipment, 'amount' => -100.0],
    ]);
});
