<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Payments\SettleTransactionResidueAction;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Collection;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * L'écran Transactions était binaire — rapprochée ou non — parce que le lien
 * l'était : une colonne `unique()` ne sait dire que « attachée » ou « pas
 * attachée ». Une ligne peut désormais être affectée en partie, et le trésorier
 * doit pouvoir distinguer les trois cas.
 */
const SETTLEMENT_COMPONENT = 'pages::club-admin.treasury.transactions';

function settlementScreen(): Testable
{
    return Livewire::actingAs(User::factory()->isAdmin()->create())->test(SETTLEMENT_COMPONENT);
}

function allocatableTransaction(float $amount, float $allocate = 0.0): Transaction
{
    $member = User::factory()->create();

    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => 1000,
    ]);

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT',
        'amount' => $amount,
        'counterparty_name' => $member->full_name,
    ]);

    if ($allocate > 0.0) {
        $payment = $subscription->payments()->create([
            'reference' => sprintf('700/0000/%05d', $transaction->id),
            'amount_due' => 1000,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        (new AllocateTransactionAction)($transaction, [$payment->id => $allocate]);
    }

    return $transaction->fresh();
}

/** @return Collection<int, int> */
function idsUnder(string $filter): Collection
{
    return collect(settlementScreen()->set('reconciledFilter', $filter)->viewData('transactions')->items())
        ->pluck('id');
}

it('sorts transactions into untouched, partly allocated and settled', function (): void {
    $untouched = allocatableTransaction(300.0);
    $partly = allocatableTransaction(300.0, 120.0);
    $settled = allocatableTransaction(300.0, 300.0);

    expect(idsUnder('unreconciled'))->toContain($untouched->id)
        ->and(idsUnder('unreconciled'))->not->toContain($partly->id)
        ->and(idsUnder('unreconciled'))->not->toContain($settled->id);

    expect(idsUnder('partial'))->toContain($partly->id)
        ->and(idsUnder('partial'))->not->toContain($untouched->id)
        ->and(idsUnder('partial'))->not->toContain($settled->id);

    expect(idsUnder('reconciled'))->toContain($settled->id)
        ->and(idsUnder('reconciled'))->not->toContain($partly->id);
})->group('payments', 'transactions');

/**
 * Un reliquat abandonné clôt la ligne aussi sûrement qu'une affectation
 * complète. Sans ça, l'arrondi d'un membre la laisserait « partiellement
 * affectée » à vie et le filtre perdrait sa valeur en une saison.
 */
it('counts a written-off residue as settled', function (): void {
    $transaction = allocatableTransaction(305.0, 300.0);

    expect(idsUnder('partial'))->toContain($transaction->id);

    (new SettleTransactionResidueAction)($transaction->fresh(), 'Arrondi du membre');

    expect(idsUnder('reconciled'))->toContain($transaction->id)
        ->and(idsUnder('partial'))->not->toContain($transaction->id);
})->group('payments', 'transactions');

/**
 * Les tuiles et le filtre doivent désigner le même ensemble. Deux requêtes
 * séparées l'ont déjà démenti une fois sur cet écran.
 */
it('keeps every tile agreeing with the filter that shows the same rows', function (): void {
    allocatableTransaction(300.0);
    allocatableTransaction(300.0, 120.0);
    allocatableTransaction(300.0, 300.0);

    $stats = settlementScreen()->instance()->stats;

    foreach (['unreconciled', 'partial', 'reconciled'] as $key) {
        expect(settlementScreen()->set('reconciledFilter', $key)->viewData('transactions')->total())
            ->toBe($stats[$key], "la tuile « {$key} » ne dit pas ce que son filtre montre");
    }
})->group('payments', 'transactions');

/**
 * Un virement sortant jamais rapproché est un remboursement parti sans avoir
 * été rattaché à personne — exactement le travail que le trésorier doit voir.
 *
 * L'ancienne règle l'excluait au motif qu'« un débit n'a pas de paiement par
 * nature ». C'était vrai du modèle d'avant, où les remboursements vivaient dans
 * une seconde colonne que `has('payment')` ne regardait pas.
 */
it('shows an unallocated outgoing transfer as work still to do', function (): void {
    $debit = allocatableTransaction(-90.0);

    expect(idsUnder('unreconciled'))->toContain($debit->id);
})->group('payments', 'transactions');
