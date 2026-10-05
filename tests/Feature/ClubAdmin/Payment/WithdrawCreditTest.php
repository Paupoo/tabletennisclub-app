<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Payments\SettleTransactionResidueAction;
use App\Actions\ClubAdmin\Subscriptions\RequestSubscriptionRefundAction;
use App\Domains\ClubAdmin\ExpenseReports\Actions\AcceptExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportPaidNotification;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\PaymentCredit;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Shared\Enums\ExpenseReportDisplayStatus;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * Le trésorier a placé un virement sur la mauvaise créance : l'affiliation
 * 2026-2027 d'un membre soldée par son virement de l'an passé.
 *
 * Rien ne défaisait un rapprochement. Le retrait supprime la ligne de crédit
 * et ramène chaque miroir là où il aurait été sans elle.
 */
function withdrawCreditClaim(float $amount = 285.0, string $reference = '016/0926/00191'): Payment
{
    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'confirmed',
        'amount_due' => $amount,
    ]);

    return $subscription->payments()->create([
        'reference' => $reference,
        'amount_due' => $amount,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);
}

function withdrawCreditTransfer(float $amount = 285.0, string $date = '2025-09-03'): Transaction
{
    return Transaction::create([
        'date' => $date,
        'description' => 'VIREMENT EN VOTRE FAVEUR',
        'amount' => $amount,
        'counterparty_name' => 'LOIX - DUPLAT',
    ]);
}

function withdrawCreditPlace(Transaction $transaction, Payment $payment, float $amount): PaymentCredit
{
    (new AllocateTransactionAction)($transaction, [$payment->id => $amount]);

    return $payment->credits()->where('transaction_id', $transaction->id)->latest('id')->firstOrFail();
}

it('puts a wrongly settled affiliation back to confirmed and unpaid', function (): void {
    $payment = withdrawCreditClaim();
    $credit = withdrawCreditPlace(withdrawCreditTransfer(), $payment, 285.0);

    expect($payment->fresh()->status)->toBe('paid')
        ->and($payment->payable->fresh()->status)->toBe('paid');

    $confirmedAt = $payment->payable->fresh()->confirmed_at;

    (new AllocateTransactionAction)->withdraw($credit);

    $subscription = $payment->payable->fresh();

    expect(PaymentCredit::find($credit->id))->toBeNull()
        ->and($payment->fresh()->amount_paid)->toBe(0.0)
        ->and($payment->fresh()->status)->toBe('pending')
        ->and($subscription->status)->toBe('confirmed')
        ->and($subscription->balanceDue())->toBe(285.0)
        ->and($subscription->confirmed_at?->toDateTimeString())->toBe($confirmedAt?->toDateTimeString());
})->group('payments', 'reconciliation');

it('frees the bank line so the right claim can be placed on it again', function (): void {
    $payment = withdrawCreditClaim();
    $wrong = withdrawCreditTransfer(285.0, '2025-09-03');
    $right = withdrawCreditTransfer(285.0, '2026-09-02');

    $credit = withdrawCreditPlace($wrong, $payment, 285.0);

    (new AllocateTransactionAction)->withdraw($credit);

    expect($wrong->fresh()->allocated_amount)->toBe(0.0)
        ->and($wrong->fresh()->isSettled())->toBeFalse();

    withdrawCreditPlace($right, $payment, 285.0);

    expect($payment->fresh()->status)->toBe('paid')
        ->and($payment->payable->fresh()->status)->toBe('paid')
        ->and($right->fresh()->allocated_amount)->toBe(285.0);
})->group('payments', 'reconciliation');

it('keeps the other claims a split transfer paid', function (): void {
    $lea = withdrawCreditClaim(150.0);
    $tom = withdrawCreditClaim(150.0, '016/0926/00292');
    $transfer = withdrawCreditTransfer(300.0);

    (new AllocateTransactionAction)($transfer, [$lea->id => 150.0, $tom->id => 150.0]);

    $credit = $tom->credits()->firstOrFail();

    (new AllocateTransactionAction)->withdraw($credit);

    expect($lea->fresh()->status)->toBe('paid')
        ->and($tom->fresh()->status)->toBe('pending')
        ->and($transfer->fresh()->allocated_amount)->toBe(150.0);
})->group('payments', 'reconciliation');

it('leaves a claim paid when other credits still cover it', function (): void {
    $payment = withdrawCreditClaim(285.0);
    $first = withdrawCreditTransfer(285.0, '2026-09-02');
    $second = withdrawCreditTransfer(100.0, '2026-09-05');

    withdrawCreditPlace($first, $payment, 285.0);
    $extra = withdrawCreditPlace($second, $payment, 100.0);

    (new AllocateTransactionAction)->withdraw($extra);

    expect($payment->fresh()->amount_paid)->toBe(285.0)
        ->and($payment->fresh()->status)->toBe('paid')
        ->and($payment->payable->fresh()->status)->toBe('paid');
})->group('payments', 'reconciliation');

it('reopens a written-off residue along with the line', function (): void {
    $payment = withdrawCreditClaim(285.0);
    $transfer = withdrawCreditTransfer(300.0);

    $credit = withdrawCreditPlace($transfer, $payment, 285.0);
    (new SettleTransactionResidueAction)($transfer->fresh(), 'Arrondi du membre');

    expect($transfer->fresh()->isSettled())->toBeTrue();

    (new AllocateTransactionAction)->withdraw($credit);

    $transfer->refresh();

    expect($transfer->settled_at)->toBeNull()
        ->and($transfer->settled_reason)->toBeNull()
        ->and($transfer->settled_by_id)->toBeNull()
        ->and($transfer->isSettled())->toBeFalse();
})->group('payments', 'reconciliation');

it('unticks a tournament entry paid by the wrong transfer', function (): void {
    $user = User::factory()->create();
    $tournament = Tournament::factory()->create(['price' => 25]);
    $tournament->users()->attach($user->id, ['registration_status' => 'confirmed', 'has_paid' => false]);

    $registration = TournamentRegistration::where('tournament_id', $tournament->id)->where('user_id', $user->id)->firstOrFail();
    $payment = $registration->payment()->create([
        'reference' => '001/2506/00001',
        'amount_due' => 25,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $credit = withdrawCreditPlace(withdrawCreditTransfer(25.0), $payment, 25.0);

    expect((bool) $registration->fresh()->has_paid)->toBeTrue();

    (new AllocateTransactionAction)->withdraw($credit);

    expect((bool) $registration->fresh()->has_paid)->toBeFalse()
        ->and($payment->fresh()->status)->toBe('pending');
})->group('payments', 'reconciliation');

it('puts an expense refund back to pay without writing to the member', function (): void {
    Notification::fake();

    $report = ExpenseReport::factory()->create(['amount' => 42.5]);
    (new AcceptExpenseReport)($report, User::factory()->create());
    $refund = $report->refresh()->refund;

    $debit = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EUROPEEN',
        'amount' => -42.5,
        'counterparty_name' => 'Membre',
    ]);

    $credit = withdrawCreditPlace($debit, $refund, 42.5);

    Notification::assertSentToTimes($report->user, ExpenseReportPaidNotification::class, 1);

    (new AllocateTransactionAction)->withdraw($credit);

    expect($refund->fresh()->status)->toBe('to_refund')
        ->and($report->fresh()->displayStatus())->toBe(ExpenseReportDisplayStatus::Accepted)
        ->and($debit->fresh()->allocated_amount)->toBe(0.0);

    Notification::assertSentToTimes($report->user, ExpenseReportPaidNotification::class, 1);
})->group('payments', 'reconciliation');

it('refuses while a refund is committed on the same claim', function (): void {
    $payment = withdrawCreditClaim(285.0);
    $credit = withdrawCreditPlace(withdrawCreditTransfer(300.0), $payment, 300.0);

    (new RequestSubscriptionRefundAction)($payment->payable->fresh(), 15.0);

    expect(fn () => (new AllocateTransactionAction)->withdraw($credit))
        ->toThrow(DomainException::class);

    expect(PaymentCredit::find($credit->id))->not->toBeNull()
        ->and($payment->fresh()->status)->toBe('paid');
})->group('payments', 'reconciliation');

it('refuses a credit that came from outside the bank', function (): void {
    $payment = withdrawCreditClaim(25.0);

    (new AllocateTransactionAction)->credit($payment, 25.0, 'cash');

    $credit = $payment->credits()->firstOrFail();

    expect(fn () => (new AllocateTransactionAction)->withdraw($credit))
        ->toThrow(DomainException::class);

    expect(PaymentCredit::find($credit->id))->not->toBeNull();
})->group('payments', 'reconciliation');

it('leaves a trace naming the transfer, the amount and who removed it', function (): void {
    $treasurer = User::factory()->create();
    $payment = withdrawCreditClaim();
    $transfer = withdrawCreditTransfer();
    $credit = withdrawCreditPlace($transfer, $payment, 285.0);

    $this->actingAs($treasurer);

    (new AllocateTransactionAction)->withdraw($credit);

    $entry = Activity::query()->where('event', 'reconciliation_removed')->latest('id')->firstOrFail();

    expect($entry->subject_type)->toBe(Payment::class)
        ->and($entry->subject_id)->toBe($payment->id)
        ->and($entry->causer_id)->toBe($treasurer->id)
        ->and($entry->attribute_changes['old'])->toMatchArray([
            'transaction' => $transfer->id,
            'transaction_date' => '03/09/2025',
            'counterparty' => 'LOIX - DUPLAT',
            'amount' => '285,00 €',
            'placed_on' => $credit->created_at->format('d/m/Y'),
        ])
        ->and($entry->attribute_changes['attributes'])->toBe(['transaction' => null]);
})->group('payments', 'reconciliation');

describe('from the payments screen', function (): void {
    it('lets the treasurer remove the wrong transfer and place the right one in the same window', function (): void {
        $treasurer = User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create();
        $payment = withdrawCreditClaim();
        $wrong = withdrawCreditTransfer(285.0, '2025-09-03');
        $right = withdrawCreditTransfer(285.0, '2026-09-02');
        $credit = withdrawCreditPlace($wrong, $payment, 285.0);

        Livewire::actingAs($treasurer)
            ->test('pages::club-admin.treasury.payments')
            ->set('statusFilter', 'paid')
            ->assertSeeHtml('openReconcile(' . $payment->id . ')')
            ->call('openReconcile', $payment->id)
            ->assertDontSee(__('Unreconciled bank transactions'))
            ->call('askWithdrawCredit', $credit->id)
            ->assertSee(__('This payment will be unpaid again.'))
            ->call('confirmWithdrawCredit')
            ->assertHasNoErrors()
            ->assertSet('reconcileModal', true)
            ->assertSee(__('Unreconciled bank transactions'))
            ->set('selectedTransactionId', $right->id)
            ->call('confirmReconcile');

        expect($payment->fresh()->status)->toBe('paid')
            ->and($payment->credits()->sole()->transaction_id)->toBe($right->id)
            ->and($wrong->fresh()->allocated_amount)->toBe(0.0);
    });

    it('offers nothing to remove on money received outside the bank', function (): void {
        $treasurer = User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create();
        $payment = withdrawCreditClaim(25.0);
        (new AllocateTransactionAction)->credit($payment, 25.0, 'cash');

        Livewire::actingAs($treasurer)
            ->test('pages::club-admin.treasury.payments')
            ->call('openReconcile', $payment->id)
            ->assertSee(__('Received outside the bank'))
            ->assertDontSeeHtml('askWithdrawCredit(');
    });

    it('shows the refusal when a refund is committed', function (): void {
        $treasurer = User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create();
        $payment = withdrawCreditClaim(285.0);
        $credit = withdrawCreditPlace(withdrawCreditTransfer(300.0), $payment, 300.0);
        (new RequestSubscriptionRefundAction)($payment->payable->fresh(), 15.0);

        Livewire::actingAs($treasurer)
            ->test('pages::club-admin.treasury.payments')
            ->call('openReconcile', $payment->id)
            ->call('askWithdrawCredit', $credit->id)
            ->call('confirmWithdrawCredit');

        expect(PaymentCredit::find($credit->id))->not->toBeNull();
    });
});

describe('from the transactions screen', function (): void {
    it('opens a fully allocated line and removes what was placed on it', function (): void {
        $treasurer = User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create();
        $payment = withdrawCreditClaim();
        $transfer = withdrawCreditTransfer(300.0);
        $credit = withdrawCreditPlace($transfer, $payment, 285.0);
        (new SettleTransactionResidueAction)($transfer->fresh(), 'Arrondi');

        Livewire::actingAs($treasurer)
            ->test('pages::club-admin.treasury.transactions')
            ->assertSeeHtml('openAllocation(' . $transfer->id . ')')
            ->call('openAllocation', $transfer->id)
            ->call('askWithdrawCredit', $credit->id)
            ->assertSee(__('This payment will be unpaid again.'))
            ->assertSee(__('The :amount € written off on this transfer will be back to handle too.', ['amount' => '15,00']))
            ->call('confirmWithdrawCredit')
            ->assertHasNoErrors()
            ->assertDontSee(__('Already placed'));

        expect($payment->fresh()->status)->toBe('pending')
            ->and($transfer->fresh()->isSettled())->toBeFalse();
    });

    it('forbids the removal to whoever cannot reconcile', function (): void {
        $payment = withdrawCreditClaim();
        $credit = withdrawCreditPlace(withdrawCreditTransfer(), $payment, 285.0);

        Livewire::actingAs(User::factory()->create())
            ->test('pages::club-admin.treasury.transactions')
            ->set('withdrawCreditId', $credit->id)
            ->call('confirmWithdrawCredit')
            ->assertForbidden();

        expect(PaymentCredit::find($credit->id))->not->toBeNull();
    });
});
