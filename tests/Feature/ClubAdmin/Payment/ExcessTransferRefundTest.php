<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/**
 * Un virement plus gros que ce qu'il reste à payer.
 *
 * Liam doit encore 20 € ; son virement en fait 120. La modale de rapprochement
 * plaçait 20 € sans rien demander, et les 100 autres restaient sur le virement,
 * attachés à personne : ni trop-perçu, ni remboursement possible. Le bandeau
 * promettait « remboursez-les », et aucun écran ne savait le faire.
 */
function excessTreasurer(): User
{
    $treasurer = User::factory()->create();
    $treasurer->assignRole(Role::TREASURY->value);

    return $treasurer;
}

/** @return array{0: Subscription, 1: Payment, 2: Transaction} */
function excessFixture(float $due = 20.0, float $transferred = 120.0): array
{
    $member = User::factory()->create();

    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => $due,
    ]);

    static $sequence = 0;
    $sequence++;

    $payment = $subscription->payments()->create([
        'reference' => sprintf('321/7654/%05d', $sequence),
        'amount_due' => $due,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EN VOTRE FAVEUR',
        'amount' => $transferred,
        'counterparty_name' => 'TITULAIRE DU COMPTE',
        'counterparty_bank_account' => 'BE47133085122760',
    ]);

    return [$subscription, $payment, $transaction];
}

it('places only the balance by default, as before', function (): void {
    [, $payment, $transaction] = excessFixture();

    Livewire::actingAs(excessTreasurer())
        ->test('pages::club-admin.treasury.payments')
        ->call('openReconcile', $payment->id)
        ->set('selectedTransactionId', $transaction->id)
        ->assertSee(__('Place the whole :amount €', ['amount' => '120,00']))
        ->call('confirmReconcile');

    expect($payment->fresh()->amount_paid)->toBe(20.0)
        ->and($payment->fresh()->isOverpaid())->toBeFalse()
        ->and($transaction->fresh()->residue())->toBe(100.0);
})->group('payments', 'overpaid');

it('lets the treasurer take the whole transfer, and opens the refund of the excess', function (): void {
    [, $payment, $transaction] = excessFixture();

    $screen = Livewire::actingAs(excessTreasurer())
        ->test('pages::club-admin.treasury.payments')
        ->call('openReconcile', $payment->id)
        ->set('selectedTransactionId', $transaction->id)
        ->set('reconcileWholeTransfer', true)
        ->call('confirmReconcile');

    expect($payment->fresh()->amount_paid)->toBe(120.0)
        ->and($payment->fresh()->overpayment())->toBe(100.0)
        ->and($transaction->fresh()->isSettled())->toBeTrue();

    // Le geste suivant est ouvert, prérempli : le montant en trop, et le
    // compte d'où il vient.
    $screen->assertSet('refundRequestModal', true)
        ->assertSet('refundRequestPaymentId', $payment->id)
        ->assertSet('refundRequestAmount', 100.0)
        ->assertSet('refundRequestIban', 'BE47133085122760');
})->group('payments', 'overpaid');

it('does not carry the choice over to the next transfer picked', function (): void {
    [, $payment, $transaction] = excessFixture();
    [, , $other] = excessFixture();

    Livewire::actingAs(excessTreasurer())
        ->test('pages::club-admin.treasury.payments')
        ->call('openReconcile', $payment->id)
        ->set('selectedTransactionId', $transaction->id)
        ->set('reconcileWholeTransfer', true)
        ->set('selectedTransactionId', $other->id)
        ->assertSet('reconcileWholeTransfer', false);
})->group('payments', 'overpaid');

it('offers no choice when the transfer does not exceed the balance', function (): void {
    [, $payment, $transaction] = excessFixture(due: 120.0, transferred: 100.0);

    Livewire::actingAs(excessTreasurer())
        ->test('pages::club-admin.treasury.payments')
        ->call('openReconcile', $payment->id)
        ->set('selectedTransactionId', $transaction->id)
        ->assertDontSee(__('Place the whole :amount €', ['amount' => '100,00']));
})->group('payments', 'overpaid');

/**
 * Le rattrapage : la décision a déjà été prise, 100 € dorment sur le virement.
 *
 * Le tiroir affirmait « ni la communication ni le tiers ne désignent un
 * membre » sur un virement qui venait de solder l'affiliation de Liam. Il ne
 * regardait que les créances encore ouvertes.
 */
it('says who the transfer already paid, instead of claiming it points to nobody', function (): void {
    [$subscription, $payment, $transaction] = excessFixture();

    (new AllocateTransactionAction)($transaction, [$payment->id => 20.0]);

    // Une autre créance ouverte, pour que la liste ne soit pas vide.
    excessFixture();

    Livewire::actingAs(excessTreasurer())
        ->test('pages::club-admin.treasury.transactions')
        ->call('openAllocation', $transaction->id)
        ->assertSee(__('Already placed'))
        ->assertSee($subscription->user->full_name)
        ->assertDontSee(__('No payment matches this transfer'))
        ->assertSee(__('Give :amount € back to the payer', ['amount' => '100,00']));
})->group('payments', 'overpaid');

it('gives the residue back through an overpayment on the claim it paid', function (): void {
    [, $payment, $transaction] = excessFixture();

    (new AllocateTransactionAction)($transaction, [$payment->id => 20.0]);

    Livewire::actingAs(excessTreasurer())
        ->test('pages::club-admin.treasury.transactions')
        ->call('openAllocation', $transaction->id)
        ->call('returnResidue')
        ->assertRedirect(route('admin.treasury.payments', ['refund' => $payment->id]));

    expect($payment->fresh()->overpayment())->toBe(100.0)
        ->and($transaction->fresh()->isSettled())->toBeTrue();
})->group('payments', 'overpaid');

it('lands on the refund request, prefilled, when arriving from the drawer', function (): void {
    [, $payment, $transaction] = excessFixture();

    (new AllocateTransactionAction)($transaction, [$payment->id => 120.0]);

    $this->actingAs(excessTreasurer());

    Livewire::withQueryParams(['refund' => $payment->id])
        ->test('pages::club-admin.treasury.payments')
        ->assertSet('statusFilter', 'overpaid')
        ->assertSet('refundRequestModal', true)
        ->assertSet('refundRequestAmount', 100.0)
        ->assertSet('refundRequestIban', 'BE47133085122760');
})->group('payments', 'overpaid');

/**
 * Un virement qui n'a encore payé personne n'a pas de payeur connu : on ne
 * devine pas à qui rendre, le trésorier le fait depuis sa banque.
 */
it('offers no give-back on a transfer that paid nobody yet', function (): void {
    [, , $transaction] = excessFixture();

    Livewire::actingAs(excessTreasurer())
        ->test('pages::club-admin.treasury.transactions')
        ->call('openAllocation', $transaction->id)
        ->assertDontSee(__('Give :amount € back to the payer', ['amount' => '120,00']))
        ->call('returnResidue');

    expect($transaction->fresh()->residue())->toBe(120.0);
})->group('payments', 'overpaid');

/**
 * Un parent paie deux enfants d'un seul virement : le surplus va sur la
 * dernière créance servie. Le choix est sans effet comptable — l'argent
 * retourne au même compte — mais il doit être déterminé.
 */
it('puts the excess on the last claim the transfer paid', function (): void {
    [, $first, $transaction] = excessFixture(due: 60.0, transferred: 200.0);
    [, $second] = excessFixture(due: 60.0);

    (new AllocateTransactionAction)($transaction, [$first->id => 60.0]);
    (new AllocateTransactionAction)($transaction, [$second->id => 60.0]);

    Livewire::actingAs(excessTreasurer())
        ->test('pages::club-admin.treasury.transactions')
        ->call('openAllocation', $transaction->id)
        ->call('returnResidue');

    expect($first->fresh()->overpayment())->toBe(0.0)
        ->and($second->fresh()->overpayment())->toBe(80.0);
})->group('payments', 'overpaid');
