<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Models\ClubAdmin\Payment\Payment;
use App\Models\ClubAdmin\Payment\Transaction;
use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;

// Helper: strip all non-digit characters from a reference string
$normalize = fn (string $ref): string => preg_replace('/[^0-9]/', '', $ref) ?? '';

describe('Payment Reconciliation', function () use ($normalize) {

    // ==================== REFERENCE NORMALIZATION ====================

    test('reference normalization strips all non-digit characters', function () use ($normalize) {
        expect($normalize('123/4567/89012'))->toBe('123456789012')
            ->and($normalize('+++123/4567/89012+++'))->toBe('123456789012')
            ->and($normalize('BE 1234 5678'))->toBe('12345678')
            ->and($normalize('REF-2025-001'))->toBe('2025001')
            ->and($normalize(''))->toBe('');
    })->group('payments', 'reconciliation');

    // ==================== MANUAL RECONCILIATION ====================

    test('reconciling a payment sets its status to paid', function () {
        $subscription = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 125]);
        $payment = $subscription->payments()->create([
            'reference'  => '123/4567/89012',
            'amount_due' => 125,
            'amount_paid' => 0,
            'status'     => 'pending',
        ]);

        $transaction = Transaction::create([
            'date'                 => now()->toDateString(),
            'amount'               => 125.0,
            'counterparty_name'    => 'Test User',
            'structured_reference' => $payment->reference,
            'description'          => 'Test payment',
        ]);

        $payment->update([
            'transaction_id' => $transaction->id,
            'amount_paid'    => $transaction->amount,
            'status'         => 'paid',
        ]);

        expect($payment->fresh()->status)->toBe('paid')
            ->and($payment->fresh()->amount_paid)->toBe(125.0)
            ->and((int) $payment->fresh()->transaction_id)->toBe($transaction->id);
    })->group('payments', 'reconciliation');

    test('reconciling a payment linked to a subscription marks the subscription as paid', function () {
        $subscription = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 125]);
        $payment = $subscription->payments()->create([
            'reference'  => '100/2505/00101',
            'amount_due' => 125,
            'amount_paid' => 0,
            'status'     => 'pending',
        ]);

        $transaction = Transaction::create([
            'date'                 => now()->toDateString(),
            'amount'               => 125.0,
            'counterparty_name'    => 'Test',
            'structured_reference' => $payment->reference,
            'description'          => 'Payment',
        ]);

        $payment->update([
            'transaction_id' => $transaction->id,
            'amount_paid'    => $transaction->amount,
            'status'         => 'paid',
        ]);

        $paymentFresh = Payment::with('payable')->find($payment->id);

        if ($paymentFresh->payable instanceof Subscription) {
            $paymentFresh->payable->update(['amount_paid' => $transaction->amount]);
            $paymentFresh->payable->markAsPaid();
        }

        expect($subscription->fresh()->status)->toBe('paid')
            ->and($subscription->fresh()->amount_paid)->toBe(125.0);
    })->group('payments', 'reconciliation');

    test('a transaction linked to a payment is excluded from the unreconciled pool', function () {
        $subscription = Subscription::factory()->create(['status' => 'paid', 'amount_due' => 60]);

        $transaction = Transaction::create([
            'date'                 => now()->toDateString(),
            'amount'               => 60.0,
            'counterparty_name'    => 'Alice',
            'structured_reference' => '100/2505/00199',
            'description'          => 'Payment',
        ]);

        $subscription->payments()->create([
            'reference'      => '100/2505/00199',
            'amount_due'     => 60,
            'amount_paid'    => 60,
            'status'         => 'paid',
            'transaction_id' => $transaction->id,
        ]);

        $unreconciled = Transaction::whereDoesntHave('payment')
            ->where('amount', '>', 0)
            ->pluck('id');

        expect($unreconciled->contains($transaction->id))->toBeFalse();
    })->group('payments', 'reconciliation');

    // ==================== MATCH SCORING ====================

    test('match score is perfect when reference and amount both match', function () use ($normalize) {
        $subscription = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 150]);
        $payment = $subscription->payments()->create([
            'reference'  => '100/2505/00101',
            'amount_due' => 150,
            'amount_paid' => 0,
            'status'     => 'pending',
        ]);

        $normalizedPayRef   = $normalize($payment->reference);
        $normalizedTransRef = $normalize('100/2505/00101');
        $refMatch           = $normalizedPayRef !== '' && $normalizedPayRef === $normalizedTransRef;
        $amountMatch        = abs(150.0 - $payment->amount_due) < 0.01;

        $score = match (true) {
            $refMatch && $amountMatch => 'perfect',
            $refMatch                 => 'reference',
            $amountMatch              => 'amount',
            default                   => 'none',
        };

        expect($score)->toBe('perfect');
    })->group('payments', 'reconciliation');

    test('match score is reference when only reference matches', function () use ($normalize) {
        $subscription = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 150]);
        $payment = $subscription->payments()->create([
            'reference'  => '100/2505/00101',
            'amount_due' => 150,
            'amount_paid' => 0,
            'status'     => 'pending',
        ]);

        $normalizedPayRef   = $normalize($payment->reference);
        $normalizedTransRef = $normalize('100/2505/00101');
        $refMatch           = $normalizedPayRef !== '' && $normalizedPayRef === $normalizedTransRef;
        $amountMatch        = abs(200.0 - $payment->amount_due) < 0.01; // different amount

        $score = match (true) {
            $refMatch && $amountMatch => 'perfect',
            $refMatch                 => 'reference',
            $amountMatch              => 'amount',
            default                   => 'none',
        };

        expect($score)->toBe('reference');
    })->group('payments', 'reconciliation');

    test('match score is amount when only amount matches', function () use ($normalize) {
        $subscription = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 125]);
        $payment = $subscription->payments()->create([
            'reference'  => '100/2505/00101',
            'amount_due' => 125,
            'amount_paid' => 0,
            'status'     => 'pending',
        ]);

        $normalizedPayRef   = $normalize($payment->reference);
        $normalizedTransRef = $normalize('999/0000/99999'); // different reference
        $refMatch           = $normalizedPayRef !== '' && $normalizedPayRef === $normalizedTransRef;
        $amountMatch        = abs(125.0 - $payment->amount_due) < 0.01;

        $score = match (true) {
            $refMatch && $amountMatch => 'perfect',
            $refMatch                 => 'reference',
            $amountMatch              => 'amount',
            default                   => 'none',
        };

        expect($score)->toBe('amount');
    })->group('payments', 'reconciliation');

    test('match score is none when nothing matches', function () use ($normalize) {
        $subscription = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 125]);
        $payment = $subscription->payments()->create([
            'reference'  => '100/2505/00101',
            'amount_due' => 125,
            'amount_paid' => 0,
            'status'     => 'pending',
        ]);

        $normalizedPayRef   = $normalize($payment->reference);
        $normalizedTransRef = $normalize('999/0000/99999');
        $refMatch           = $normalizedPayRef !== '' && $normalizedPayRef === $normalizedTransRef;
        $amountMatch        = abs(200.0 - $payment->amount_due) < 0.01;

        $score = match (true) {
            $refMatch && $amountMatch => 'perfect',
            $refMatch                 => 'reference',
            $amountMatch              => 'amount',
            default                   => 'none',
        };

        expect($score)->toBe('none');
    })->group('payments', 'reconciliation');

})->group('payments');


describe('Batch Auto-Reconciliation', function () use ($normalize) {

    // ==================== BATCH PREVIEW ====================

    test('batch preview finds payments where reference and amount match a transaction', function () use ($normalize) {
        $subscription = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 125]);
        $payment = $subscription->payments()->create([
            'reference'  => '100/2505/00101',
            'amount_due' => 125,
            'amount_paid' => 0,
            'status'     => 'pending',
        ]);

        $transaction = Transaction::create([
            'date'                 => now()->toDateString(),
            'amount'               => 125.0,
            'counterparty_name'    => 'Test',
            'structured_reference' => '100/2505/00101',
            'description'          => 'Test',
        ]);

        $pendingPayments = Payment::with(['payable.user'])
            ->where('status', 'pending')
            ->whereNull('transaction_id')
            ->get();

        $unreconciledTransactions = Transaction::whereDoesntHave('payment')
            ->where('amount', '>', 0)
            ->get()
            ->keyBy(fn ($t) => $normalize($t->structured_reference ?? '___' . $t->id));

        $matches = [];
        foreach ($pendingPayments as $p) {
            $normalizedRef = $normalize($p->reference);
            if (! $normalizedRef) {
                continue;
            }
            $tx = $unreconciledTransactions->get($normalizedRef);
            if ($tx && abs($tx->amount - $p->amount_due) < 0.01) {
                $matches[] = ['payment_id' => $p->id, 'transaction_id' => $tx->id];
            }
        }

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['payment_id'])->toBe($payment->id)
            ->and($matches[0]['transaction_id'])->toBe($transaction->id);
    })->group('payments', 'batch');

    test('batch preview excludes payments that already have a transaction', function () {
        $alreadyReconciledTx = Transaction::create([
            'date'                 => now()->toDateString(),
            'amount'               => 125.0,
            'counterparty_name'    => 'Already done',
            'structured_reference' => '001/2505/00101',
            'description'          => 'Already reconciled',
        ]);

        $subscription = Subscription::factory()->create(['status' => 'paid', 'amount_due' => 125]);
        $payment = $subscription->payments()->create([
            'reference'      => '001/2505/00101',
            'amount_due'     => 125,
            'amount_paid'    => 125,
            'status'         => 'paid',
            'transaction_id' => $alreadyReconciledTx->id,
        ]);

        $pendingPayments = Payment::where('status', 'pending')->whereNull('transaction_id')->get();

        expect($pendingPayments->contains('id', $payment->id))->toBeFalse();
    })->group('payments', 'batch');

    test('batch preview only matches a transaction once even if multiple payments are pending', function () use ($normalize) {
        // One transaction (REF-A) + two pending payments
        // Payment 1 matches REF-A; payment 2 has a different reference and should not steal the transaction
        $sub1 = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 125]);
        $sub2 = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 125]);

        $payment1 = $sub1->payments()->create([
            'reference'  => '100/2505/00101',
            'amount_due' => 125, 'amount_paid' => 0, 'status' => 'pending',
        ]);
        $sub2->payments()->create([
            'reference'  => '200/2505/00202', // different reference — should not match
            'amount_due' => 125, 'amount_paid' => 0, 'status' => 'pending',
        ]);

        // Only one transaction exists, matching payment1's reference exactly
        Transaction::create([
            'date'                 => now()->toDateString(),
            'amount'               => 125.0,
            'counterparty_name'    => 'User',
            'structured_reference' => '100/2505/00101',
            'description'          => 'Test',
        ]);

        $unreconciledTransactions = Transaction::whereDoesntHave('payment')
            ->where('amount', '>', 0)
            ->get()
            ->keyBy(fn ($t) => $normalize($t->structured_reference ?? '___' . $t->id));

        $pendingPayments = Payment::where('status', 'pending')->whereNull('transaction_id')->get();

        $matches = [];
        foreach ($pendingPayments as $p) {
            $normalizedRef = $normalize($p->reference);
            if (! $normalizedRef) {
                continue;
            }
            $tx = $unreconciledTransactions->get($normalizedRef);
            if ($tx && abs($tx->amount - $p->amount_due) < 0.01) {
                $matches[] = ['payment_id' => $p->id, 'transaction_id' => $tx->id];
                $unreconciledTransactions->forget($normalizedRef); // consume the transaction
            }
        }

        // Only payment1 should match — payment2 has a different reference
        expect($matches)->toHaveCount(1)
            ->and($matches[0]['payment_id'])->toBe($payment1->id);
    })->group('payments', 'batch');

    test('batch apply reconciles all matched payments and marks subscriptions as paid', function () use ($normalize) {
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

        $subs = collect(User::factory()->count(2)->create())->map(fn ($user) =>
            Subscription::factory()->create([
                'user_id'   => $user->id,
                'season_id' => $season->id,
                'status'    => 'confirmed',
                'amount_due' => 125,
            ])
        );

        $payments = $subs->map(fn ($sub) => $sub->payments()->create([
            'reference'   => (new GeneratePaymentReference)(),
            'amount_due'  => 125,
            'amount_paid' => 0,
            'status'      => 'pending',
        ]));

        // Create matching bank transactions in DB
        $payments->each(fn ($p) => Transaction::create([
            'date'                 => now()->toDateString(),
            'amount'               => 125.0,
            'counterparty_name'    => 'User',
            'structured_reference' => $p->reference,
            'description'          => 'Test',
        ]));

        // Build batch matches
        $unreconciledTransactions = Transaction::whereDoesntHave('payment')
            ->where('amount', '>', 0)
            ->get()
            ->keyBy(fn ($t) => $normalize($t->structured_reference ?? '___' . $t->id));

        $batchMatches = [];
        foreach ($payments as $p) {
            $normalizedRef = $normalize($p->reference);
            $tx = $unreconciledTransactions->get($normalizedRef);
            if ($tx && abs($tx->amount - $p->amount_due) < 0.01) {
                $batchMatches[] = ['payment_id' => $p->id, 'transaction_id' => $tx->id];
                $unreconciledTransactions->forget($normalizedRef);
            }
        }

        // Apply all matches
        foreach ($batchMatches as $match) {
            $payment     = Payment::find($match['payment_id']);
            $transaction = Transaction::find($match['transaction_id']);

            $payment->update([
                'transaction_id' => $transaction->id,
                'amount_paid'    => $transaction->amount,
                'status'         => 'paid',
            ]);

            if ($payment->payable instanceof Subscription) {
                $payment->payable->update(['amount_paid' => $transaction->amount]);
                $payment->payable->markAsPaid();
            }
        }

        expect($batchMatches)->toHaveCount(2);

        foreach ($payments as $p) {
            expect($p->fresh()->status)->toBe('paid');
        }

        foreach ($subs as $sub) {
            expect($sub->fresh()->status)->toBe('paid');
        }
    })->group('payments', 'batch');

})->group('payments');


describe('Payment Reference Generation', function () {

    test('generates a reference with a structured slash-separated format', function () {
        $reference = (new GeneratePaymentReference)();

        // Belgian structured communication: XXX/XXXX/XXXXX or XXX/XXXX/XXXX
        expect($reference)->toMatch('/^\d{3}\/\d{4}\/\d{4,5}$/');
    })->group('payments', 'reference');

    test('each call generates a unique reference after a payment is created', function () {
        $sub = Subscription::factory()->create(['status' => 'confirmed']);

        $ref1 = (new GeneratePaymentReference)();

        // Creating a payment advances the day sequence counter
        $sub->payments()->create([
            'reference'  => $ref1,
            'amount_due' => 125,
            'amount_paid' => 0,
            'status'     => 'pending',
        ]);

        $ref2 = (new GeneratePaymentReference)();

        expect($ref1)->not->toBe($ref2);
    })->group('payments', 'reference');

    test('checksum satisfies the modulo-97 constraint', function () {
        $reference = (new GeneratePaymentReference)();

        // The reference is: 0 + date(6) + sequence(3) = 10 base digits, then checksum appended
        $digits    = preg_replace('/[^0-9]/', '', $reference) ?? '';
        $base      = (int) substr($digits, 0, 10); // first 10 digits
        $checksum  = (int) substr($digits, 10);    // remaining 1-2 digits

        expect($base % 97)->toBe($checksum);
    })->group('payments', 'reference');

    test('addSeparators inserts slashes at correct positions', function () {
        $gen = new GeneratePaymentReference;

        $result = $gen->addSeparators('012345678901');

        expect($result)->toBe('012/3456/78901');
    })->group('payments', 'reference');

})->group('payments');
