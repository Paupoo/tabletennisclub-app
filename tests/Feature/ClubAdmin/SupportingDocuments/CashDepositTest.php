<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\LinkCashDepositAction;
use App\Actions\ClubAdmin\Payments\UnlinkCashDepositAction;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\LinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Taking the till to the bank, or a float out of it, is neither income nor
 * expense: once the treasurer links the two sides, both are internal.
 */
function cdEntry(int $cents): CashRegisterEntry
{
    return CashRegisterEntry::create([
        'cash_register_id' => CashRegister::create(['name' => 'Caisse'])->id,
        'amount' => $cents,
        'reason' => 'Versement',
        'recorded_by_id' => User::factory()->create()->id,
    ]);
}

function cdLine(float $amount): Transaction
{
    return Transaction::create(['date' => '2026-09-15', 'description' => 'VERSEMENT ESPECES', 'amount' => $amount]);
}

it('makes a till deposit internal on both sides, and closes the bank line', function (): void {
    $entry = cdEntry(-50000);
    $line = cdLine(500.0);

    (new LinkCashDepositAction)($line, $entry);

    expect($line->fresh()->is_internal)->toBeTrue()
        ->and($line->fresh()->isSettled())->toBeTrue()
        ->and($entry->fresh()->isInternal())->toBeTrue()
        ->and(CashRegisterEntry::internal()->pluck('id')->all())->toBe([$entry->id]);
});

it('reverts both sides when the deposit is unlinked', function (): void {
    $entry = cdEntry(-50000);
    $line = cdLine(500.0);
    (new LinkCashDepositAction)($line, $entry);

    (new UnlinkCashDepositAction)($entry->fresh());

    expect($line->fresh()->is_internal)->toBeFalse()
        ->and($line->fresh()->isSettled())->toBeFalse()
        ->and($entry->fresh()->isInternal())->toBeFalse();
});

it('refuses two movements going the same way', function (): void {
    (new LinkCashDepositAction)(cdLine(-500.0), cdEntry(-50000));
})->throws(DomainException::class);

it('refuses two movements of different amounts', function (): void {
    (new LinkCashDepositAction)(cdLine(450.0), cdEntry(-50000));
})->throws(DomainException::class);

it('refuses a bank line a document already justifies', function (): void {
    Storage::fake('local');
    $line = cdLine(500.0);
    (new LinkSupportingDocument)(SupportingDocument::factory()->create(), $line);

    (new LinkCashDepositAction)($line, cdEntry(-50000));
})->throws(DomainException::class);
