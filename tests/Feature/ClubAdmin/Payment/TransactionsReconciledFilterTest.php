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

it('the unreconciled filter shows every line with nothing allocated, in either direction', function (): void {
    $admin = transactionsAdmin();
    $credit = Transaction::create(['date' => now(), 'amount' => 50, 'description' => 'incoming']);
    $debit = Transaction::create(['date' => now(), 'amount' => -30, 'description' => 'outgoing']);

    $component = Livewire::actingAs($admin)
        ->test(TRANSACTIONS_COMPONENT)
        ->set('reconciledFilter', 'unreconciled');

    $ids = collect($component->viewData('transactions')->items())->pluck('id');

    // Les débits entraient ici parce qu'« un débit n'a pas de paiement par
    // nature » — vrai du modèle d'avant, où les remboursements vivaient dans
    // une seconde colonne que `has('payment')` ne regardait pas. Un virement
    // sortant jamais rapproché est un remboursement parti sans destinataire
    // identifié : c'est du travail, et il doit se voir.
    expect($ids)->toContain($credit->id)
        ->and($ids)->toContain($debit->id);
});

it('the unreconciled filter count matches the unreconciled tile', function (): void {
    $admin = transactionsAdmin();
    Transaction::create(['date' => now(), 'amount' => 50, 'description' => 'in 1']);
    Transaction::create(['date' => now(), 'amount' => 80, 'description' => 'in 2']);
    Transaction::create(['date' => now(), 'amount' => -30, 'description' => 'out']);

    $component = Livewire::actingAs($admin)
        ->test(TRANSACTIONS_COMPONENT)
        ->set('reconciledFilter', 'unreconciled');

    $tile = $component->instance()->stats['unreconciled'];
    $filtered = $component->viewData('transactions')->total();

    // L'intention d'origine tient : la tuile et le filtre désignent le même
    // ensemble. Ce sont les trois lignes, débit compris.
    expect($filtered)->toBe($tile)
        ->and($filtered)->toBe(3);
});

it('a searched counterparty outside the date range stays out of the list', function (): void {
    $admin = transactionsAdmin();
    $inRange = Transaction::create([
        'date' => '2026-03-10',
        'amount' => 50,
        'counterparty_name' => 'Nadia Lemoine',
        'description' => 'in range',
    ]);
    $outOfRange = Transaction::create([
        'date' => '2025-11-04',
        'amount' => 80,
        'counterparty_name' => 'Nadia Lemoine',
        'description' => 'out of range',
    ]);

    // `when()` n'ouvre aucune parenthèse : sans groupe, la recherche sur le nom
    // du tiers s'évade du filtre de date et ramène toute l'histoire du membre.
    $component = Livewire::actingAs($admin)
        ->test(TRANSACTIONS_COMPONENT)
        ->set('search', 'Lemoine')
        ->set('dateFrom', '2026-01-01');

    $ids = collect($component->viewData('transactions')->items())->pluck('id');

    expect($ids)->toContain($inRange->id)
        ->and($ids)->not->toContain($outOfRange->id);
});
