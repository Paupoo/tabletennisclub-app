<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\LinkCashDepositAction;
use App\Actions\ClubAdmin\Payments\UnlinkCashDepositAction;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\LinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\UnlinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * The accounts auditors read who said which money a document justifies, and
 * which bank line a till deposit became: the links live in pivots and a
 * foreign key the model log never sees, so each gesture writes its own entry.
 */
beforeEach(function (): void {
    Storage::fake('local');
    $this->treasurer = User::factory()->isAdmin()->create();
    $this->actingAs($this->treasurer);
});

function laLine(float $amount): Transaction
{
    return Transaction::create(['date' => '2026-09-15', 'description' => 'VIREMENT', 'amount' => $amount]);
}

function laEntry(int $cents): CashRegisterEntry
{
    return CashRegisterEntry::create([
        'cash_register_id' => CashRegister::create(['name' => 'Caisse'])->id,
        'amount' => $cents,
        'reason' => 'Ticket',
        'recorded_by_id' => User::factory()->create()->id,
    ]);
}

function laLast(string $event): ?Activity
{
    return Activity::query()->where('event', $event)->latest('id')->first();
}

it('records who linked a document to a bank line, and who took it off', function (): void {
    $document = SupportingDocument::factory()->create(['amount' => 89.4]);
    $line = laLine(-89.4);

    (new LinkSupportingDocument)($document, $line);
    (new UnlinkSupportingDocument)($document, $line);

    $linked = laLast('supporting_document_linked');
    $unlinked = laLast('supporting_document_unlinked');

    expect($linked->subject_id)->toBe($document->id)
        ->and($linked->causer_id)->toBe($this->treasurer->id)
        ->and($linked->attribute_changes['attributes'])->toBe(['transaction' => $line->id])
        ->and($unlinked->subject_id)->toBe($document->id)
        ->and($unlinked->attribute_changes['old'])->toBe(['transaction' => $line->id]);
});

it('records a document linked to a cash movement', function (): void {
    $document = SupportingDocument::factory()->create(['amount' => 12.0]);
    $entry = laEntry(-1200);

    (new LinkSupportingDocument)($document, $entry);

    expect(laLast('supporting_document_linked')->attribute_changes['attributes'])->toBe(['cash_register_entry' => $entry->id]);
});

it('records a till deposit linked to its bank line, then unlinked', function (): void {
    $entry = laEntry(-50000);
    $line = laLine(500.0);

    (new LinkCashDepositAction)($line, $entry);
    (new UnlinkCashDepositAction)($entry->fresh());

    expect(laLast('cash_deposit_linked')->subject_id)->toBe($entry->id)
        ->and(laLast('cash_deposit_linked')->attribute_changes['attributes'])->toBe(['transaction' => $line->id])
        ->and(laLast('cash_deposit_unlinked')->attribute_changes['old'])->toBe(['transaction' => $line->id]);
});

it('names the gestures on the audit screen', function (): void {
    $document = SupportingDocument::factory()->create(['amount' => 20.0]);
    (new LinkSupportingDocument)($document, laLine(-20.0));

    Livewire::test('pages::club-admin.audit.index')
        ->assertSee(__('Linked to a movement'))
        ->assertSee(__('Supporting document'));
});
