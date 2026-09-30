<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\BankAccount;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/**
 * Members pay into the current account. A savings account, or a transfer
 * between the club's own accounts, is never anybody's payment: the automatic
 * reconciliation must not propose it.
 */
function rcTreasurer(): User
{
    $treasurer = User::factory()->create();
    $treasurer->assignRole(Role::TREASURY->value);

    return $treasurer;
}

function rcClaim(string $reference): Payment
{
    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create(['first_name' => 'Finn', 'last_name' => 'Martin'])->id,
        'status' => 'confirmed',
        'amount_due' => 60,
    ]);

    return $subscription->payments()->create([
        'reference' => $reference,
        'amount_due' => 60,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function rcTransfer(string $reference, array $attributes = []): Transaction
{
    return Transaction::create([
        'date' => '2026-09-10',
        'description' => 'VIREMENT',
        'amount' => 60.0,
        'counterparty_name' => 'Finn Martin',
        'structured_reference' => $reference,
        ...$attributes,
    ]);
}

it('matches in bulk only the transfers of a current account', function (): void {
    $claim = rcClaim('025/0926/00297');
    rcTransfer($claim->reference, ['bank_account_id' => BankAccount::factory()->savings()->create()->id]);
    rcTransfer($claim->reference, ['is_internal' => true]);
    $onCurrent = rcTransfer($claim->reference, ['bank_account_id' => BankAccount::factory()->create()->id]);

    $matches = Livewire::actingAs(rcTreasurer())
        ->test('pages::club-admin.treasury.payments')
        ->call('previewBatchMatch')
        ->get('batchMatches');

    expect(collect($matches)->pluck('transaction_id')->all())->toBe([$onCurrent->id]);
});

it('offers a claim only the transfers of a current account', function (): void {
    $claim = rcClaim('025/0926/00297');
    rcTransfer($claim->reference, ['bank_account_id' => BankAccount::factory()->savings()->create()->id]);
    rcTransfer($claim->reference, ['is_internal' => true]);
    $legacy = rcTransfer($claim->reference);

    $candidates = Livewire::actingAs(rcTreasurer())
        ->test('pages::club-admin.treasury.payments')
        ->call('openReconcile', $claim->id)
        ->viewData('pendingTransactions');

    expect($candidates->pluck('id')->all())->toBe([$legacy->id]);
});

it('suggests no claim for a line of a savings account', function (): void {
    $claim = rcClaim('025/0926/00297');
    $interest = rcTransfer($claim->reference, ['bank_account_id' => BankAccount::factory()->savings()->create()->id]);

    $candidates = Livewire::actingAs(rcTreasurer())
        ->test('pages::club-admin.treasury.transactions')
        ->call('openAllocation', $interest->id)
        ->instance()
        ->allocationCandidates();

    expect($candidates)->toBeEmpty();
});
