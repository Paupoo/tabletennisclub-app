<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Livewire;

const TRANSACTIONS_COMPONENT = 'pages::club-admin.treasury.transactions';

function transactionsAdmin(): User
{
    return User::factory()->isAdmin()->create();
}

it('the unreconciled filter shows only credits, like the unreconciled tile (I8)', function (): void {
    $admin = transactionsAdmin();
    $credit = Transaction::create(['date' => now(), 'amount' => 50, 'description' => 'incoming']);
    $debit = Transaction::create(['date' => now(), 'amount' => -30, 'description' => 'outgoing']);

    $component = Livewire::actingAs($admin)
        ->test(TRANSACTIONS_COMPONENT)
        ->set('reconciledFilter', 'unreconciled');

    $ids = collect($component->viewData('transactions')->items())->pluck('id');

    // A debit (outgoing) has no payment by nature — it is not an "unreconciled"
    // incoming payment and must not pad the list the tile already excludes.
    expect($ids)->toContain($credit->id)
        ->and($ids)->not->toContain($debit->id);
});

it('the unreconciled filter count matches the unreconciled tile', function (): void {
    $admin = transactionsAdmin();
    Transaction::create(['date' => now(), 'amount' => 50, 'description' => 'in 1']);   // credit, unreconciled
    Transaction::create(['date' => now(), 'amount' => 80, 'description' => 'in 2']);   // credit, unreconciled
    Transaction::create(['date' => now(), 'amount' => -30, 'description' => 'out']);   // debit, unreconciled

    $component = Livewire::actingAs($admin)
        ->test(TRANSACTIONS_COMPONENT)
        ->set('reconciledFilter', 'unreconciled');

    $tile = $component->instance()->stats['unreconciled'];
    $filtered = $component->viewData('transactions')->total();

    expect($filtered)->toBe($tile)
        ->and($filtered)->toBe(2);
});
