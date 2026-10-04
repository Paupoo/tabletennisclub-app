<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;

/*
| Le trop-perçu d'une liste se calcule en SQL, une ligne à la fois il se calcule
| en PHP. Les deux doivent dire le même chiffre sur chaque ligne : c'est ce que
| ce fichier vérifie, cas par cas.
*/

/** @param  array<string, mixed>  $attributes */
function overpaymentLine(string $payableType, int $payableId, array $attributes): Payment
{
    static $counter = 0;
    $counter++;

    return Payment::forceCreate([
        'payable_type' => $payableType,
        'payable_id' => $payableId,
        'reference' => sprintf('OVP/2026/%05d', $counter),
        'amount_paid' => 0,
        'status' => 'pending',
        ...$attributes,
    ]);
}

/** Chaque ligne, lue en SQL puis en PHP, avec le même trop-perçu. */
function expectSqlToAgreeWithPhp(): void
{
    $fromSql = Payment::query()->withOverpayment()->orderBy('id')->get();

    expect($fromSql)->not->toBeEmpty();

    foreach ($fromSql as $line) {
        expect($line->overpayment())
            ->toBe(Payment::findOrFail($line->id)->overpayment(), "ligne {$line->reference}");
    }

    $overpaidInPhp = Payment::query()->orderBy('id')->get()
        ->filter(fn (Payment $line): bool => $line->payment_method !== 'refund' && $line->overpayment() > 0.0)
        ->pluck('id')->values()->all();

    expect(Payment::query()->overpaid()->orderBy('id')->pluck('id')->all())->toBe($overpaidInPhp);
}

describe('an affiliation', function (): void {
    beforeEach(function (): void {
        $this->subscription = Subscription::factory()->create(['amount_due' => 100]);
        $this->line = fn (array $attributes): Payment => overpaymentLine(Subscription::class, $this->subscription->id, $attributes);
    });

    it('carries nothing while it is not overpaid', function (): void {
        ($this->line)(['amount_due' => 100, 'amount_paid' => 100, 'status' => 'paid']);
        ($this->line)(['amount_due' => 50]);

        expectSqlToAgreeWithPhp();
    });

    it('carries the excess on its last credited line only', function (): void {
        $first = ($this->line)(['amount_due' => 60, 'amount_paid' => 60, 'status' => 'paid']);
        $last = ($this->line)(['amount_due' => 60, 'amount_paid' => 60, 'status' => 'paid']);
        ($this->line)(['amount_due' => 10]);

        expect(Payment::withOverpayment()->find($last->id)->overpayment())->toBe(20.0)
            ->and(Payment::withOverpayment()->find($first->id)->overpayment())->toBe(0.0);

        expectSqlToAgreeWithPhp();
    });

    it('nets the refunds promised or paid out, never a cancelled one', function (string $refundStatus, float $expected): void {
        $claim = ($this->line)(['amount_due' => 120, 'amount_paid' => 120, 'status' => 'paid']);
        ($this->line)(['payment_method' => 'refund', 'amount_due' => 5, 'status' => $refundStatus]);

        expect(Payment::withOverpayment()->find($claim->id)->overpayment())->toBe($expected);

        expectSqlToAgreeWithPhp();
    })->with([
        'open' => ['to_refund', 15.0],
        'paid out' => ['refunded', 15.0],
        'cancelled' => ['cancelled', 20.0],
    ]);

    it('leaves a cancelled claim out of what was received', function (): void {
        ($this->line)(['amount_due' => 100, 'amount_paid' => 100, 'status' => 'paid']);
        ($this->line)(['amount_due' => 30, 'amount_paid' => 30, 'status' => 'cancelled']);

        expectSqlToAgreeWithPhp();
    });
});

describe('any other line', function (): void {
    beforeEach(function (): void {
        $tournament = Tournament::factory()->create();
        $tournament->users()->attach(User::factory()->create()->id, ['registration_status' => 'registered']);
        $this->registration = TournamentRegistration::where('tournament_id', $tournament->id)->firstOrFail();
        $this->line = fn (array $attributes): Payment => overpaymentLine(TournamentRegistration::class, $this->registration->id, $attributes);
    });

    it('carries what it received beyond what it claimed', function (): void {
        $claim = ($this->line)(['amount_due' => 10, 'amount_paid' => 15, 'status' => 'paid']);

        expect(Payment::withOverpayment()->find($claim->id)->overpayment())->toBe(5.0);

        expectSqlToAgreeWithPhp();
    });

    it('nets the refunds promised or paid out, never a cancelled one', function (string $refundStatus, float $expected): void {
        $claim = ($this->line)(['amount_due' => 10, 'amount_paid' => 15, 'status' => 'paid']);
        ($this->line)(['payment_method' => 'refund', 'amount_due' => 3, 'status' => $refundStatus]);

        expect(Payment::withOverpayment()->find($claim->id)->overpayment())->toBe($expected);

        expectSqlToAgreeWithPhp();
    })->with([
        'open' => ['to_refund', 2.0],
        'paid out' => ['refunded', 2.0],
        'cancelled' => ['cancelled', 5.0],
    ]);

    it('carries nothing on an unpaid claim, nor on the refund line itself', function (): void {
        ($this->line)(['amount_due' => 10]);
        ($this->line)(['payment_method' => 'refund', 'amount_due' => 3, 'amount_paid' => 3, 'status' => 'refunded']);

        expectSqlToAgreeWithPhp();
    });
});
