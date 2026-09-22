<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Payments\SettleTransactionResidueAction;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
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

/**
 * Le cas B, depuis la transaction : une mère vire 730 € pour ses deux enfants.
 *
 * Le sens paiement → transaction sait déjà le faire depuis que le lien n'est
 * plus unique, mais il oblige le trésorier à ouvrir deux fiches et à retrouver
 * deux fois la même ligne de relevé. Le geste naturel part du virement.
 */
it('splits one transfer across several payments from the transaction drawer', function (): void {
    $mother = User::factory()->create();

    $children = collect([1, 2])->map(function (): object {
        $child = User::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $child->id,
            'status' => 'confirmed',
            'amount_due' => 365,
        ]);

        return (object) [
            'subscription' => $subscription,
            'payment' => $subscription->payments()->create([
                'reference' => sprintf('300/0000/%05d', $child->id),
                'amount_due' => 365,
                'amount_paid' => 0,
                'status' => 'pending',
            ]),
        ];
    });

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT',
        'amount' => 730.0,
        'counterparty_name' => $mother->full_name,
    ]);

    settlementScreen()
        ->call('openAllocation', $transaction->id)
        ->set('allocations', [
            (string) $children[0]->payment->id => 365.0,
            (string) $children[1]->payment->id => 365.0,
        ])
        ->call('confirmAllocation')
        ->assertHasNoErrors();

    expect($children[0]->subscription->fresh()->balanceDue())->toBe(0.0)
        ->and($children[1]->subscription->fresh()->balanceDue())->toBe(0.0)
        ->and($transaction->fresh()->isSettled())->toBeTrue();
})->group('payments', 'transactions');

/**
 * I1 tient aussi de ce côté : le tiroir refuse d'inventer de l'argent, et il
 * le dit au trésorier plutôt que d'échouer en silence.
 */
it('refuses an allocation that exceeds the transfer, and writes nothing', function (): void {
    $transaction = allocatableTransaction(200.0);

    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'confirmed',
        'amount_due' => 365,
    ]);

    $payment = $subscription->payments()->create([
        'reference' => '400/0000/00001',
        'amount_due' => 365,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    settlementScreen()
        ->call('openAllocation', $transaction->id)
        ->set('allocations', [(string) $payment->id => 250.0])
        ->call('confirmAllocation');

    expect($payment->fresh()->amount_paid)->toBe(0.0)
        ->and($transaction->fresh()->allocated_amount)->toBe(0.0);
})->group('payments', 'transactions');

/**
 * Solder un reliquat depuis le tiroir : le troisième geste du cas C.
 */
it('writes off a residue from the drawer, with a reason', function (): void {
    $transaction = allocatableTransaction(305.0, 300.0);

    settlementScreen()
        ->call('openAllocation', $transaction->id)
        ->set('residueReason', 'Arrondi du membre, acquis au club')
        ->call('settleResidue')
        ->assertHasNoErrors();

    expect($transaction->fresh()->isSettled())->toBeTrue()
        ->and($transaction->fresh()->settled_reason)->toBe('Arrondi du membre, acquis au club');
})->group('payments', 'transactions');

/**
 * Le tiroir doit supporter tous les payables, pas seulement les affiliations.
 *
 * `TransactionMatcher::payer()` lit `$payable->user` pour une affiliation, une
 * inscription au tournoi **et** une participation à une réunion. N'en charger
 * qu'un seul fait tomber l'écran en `LazyLoadingViolation` dès qu'une créance
 * d'un autre type traîne — et dix-neuf des créances ouvertes de la base de
 * démonstration sont des inscriptions à un tournoi.
 */
it('opens the drawer when a tournament registration is waiting for money', function (): void {
    $member = User::factory()->create();

    $tournament = Tournament::factory()->create(['price' => 15]);
    $tournament->users()->attach($member->id, ['registration_status' => 'registered']);

    $registration = TournamentRegistration::where('tournament_id', $tournament->id)
        ->where('user_id', $member->id)
        ->firstOrFail();

    $registration->payment()->create([
        'reference' => '800/0000/00001',
        'amount_due' => 15,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT',
        'amount' => 15.0,
        'counterparty_name' => $member->full_name,
    ]);

    $component = settlementScreen()->call('openAllocation', $transaction->id);

    // On évalue vraiment les candidats : `assertOk()` ne force pas une
    // propriété calculée, et c'est là que le barème lit `$payable->user`.
    $candidates = $component->instance()->allocationCandidates();

    expect($candidates->pluck('reference'))->toContain('800/0000/00001');

    // L'invariant, plutôt que l'exception : `payer()` lit `$payable->user` sur
    // les trois payables qui en portent un, et une relation non chargée fait
    // tomber l'écran. Attendre l'exception ne suffit pas — elle ne se lève que
    // si le payable se résout, ce qui dépend du type.
    foreach ($candidates as $candidate) {
        $payable = $candidate->payable;

        if ($payable === null || ! method_exists($payable, 'user')) {
            continue;
        }

        expect($payable->relationLoaded('user'))->toBeTrue(sprintf(
            '%s doit arriver avec son membre déjà chargé',
            $payable::class,
        ));
    }
})->group('payments', 'transactions');
