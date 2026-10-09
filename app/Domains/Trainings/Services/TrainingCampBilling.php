<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Services;

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Actions\ClubAdmin\Payments\InviteToPayAction;
use App\Actions\ClubAdmin\Payments\OpenRefundAction;
use App\Actions\ClubAdmin\Subscriptions\ReduceOutstandingInvoiceAction;
use App\Contracts\CampEnrolment;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a stage's invoice in step with what the line costs.
 *
 * A stage line carries its own payments ({@see SubscriptionTrainingPack}), and
 * so does a non-member's registration ({@see CampEnrolment}). One
 * rule answers every change — enrolment, forced amount, encoding error, the
 * club calling the stage off, the affiliation cancelled: what the line is
 * still claiming must equal what it costs.
 *
 * - Claiming too little: the unpaid request grows, or a complement is issued
 *   when the request is already settled.
 * - Claiming too much: unpaid requests shrink first, latest first, exactly as
 *   {@see ReduceOutstandingInvoiceAction}
 *   does for an affiliation. What remains was already received and leaves as a
 *   refund, opened in the treasury workflow — the same gesture as adjusting a
 *   season pack downwards.
 */
final readonly class TrainingCampBilling
{
    public function __construct(private TrainingPackExit $exit = new TrainingPackExit) {}

    /**
     * Invoices a line that just became `enrolled`, and asks the member to pay.
     */
    public function invoice(SubscriptionTrainingPack $line): ?Payment
    {
        $issued = $this->sync($line)['issued'];

        if ($issued instanceof Payment) {
            (new InviteToPayAction)($issued);
        }

        return $issued;
    }

    /**
     * Does this line's money live outside the affiliation?
     */
    public function isInvoicedSeparately(Subscription $subscription, TrainingPack $pack): bool
    {
        return (bool) DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->where('training_pack_id', $pack->id)
            ->value('invoiced_separately');
    }

    /**
     * The line, read as a payable — or null when the member never touched the pack.
     */
    public function line(Subscription $subscription, TrainingPack $pack): ?SubscriptionTrainingPack
    {
        return SubscriptionTrainingPack::query()
            ->where('subscription_id', $subscription->id)
            ->where('training_pack_id', $pack->id)
            ->first();
    }

    /**
     * What is still claimed on the line, refunds already committed deducted.
     */
    public function outstandingClaim(CampEnrolment&Model $line): float
    {
        $payments = $line->payments()->get();

        return round($this->claims($payments)->sum(fn (Payment $payment): float => (float) $payment->amount_due)
            - $this->committedRefunds($payments), 2);
    }

    /**
     * What {@see sync()} would do if the line cost `$target`, without writing.
     *
     * Previews read the same reasoning as the click, so a screen cannot
     * promise something else than what the action will do.
     *
     * @return array{reduced: float, refund: float}
     */
    public function project(CampEnrolment&Model $line, float $target): array
    {
        $payments = $line->payments()->get();
        $claims = $this->claims($payments);
        $committed = $this->committedRefunds($payments);

        $excess = round($claims->sum(fn (Payment $payment): float => (float) $payment->amount_due) - $committed - $target, 2);

        if ($excess <= 0.0) {
            return ['reduced' => 0.0, 'refund' => 0.0];
        }

        $unpaid = round((float) $claims->where('status', 'pending')
            ->sum(fn (Payment $payment): float => max(0.0, (float) $payment->amount_due - (float) $payment->amount_paid)), 2);

        $reduced = min($excess, $unpaid);
        $received = round($claims->sum(fn (Payment $payment): float => (float) $payment->amount_paid) - $committed, 2);

        return [
            'reduced' => round($reduced, 2),
            'refund' => round(min($excess - $reduced, max(0.0, $received)), 2),
        ];
    }

    /**
     * What becomes of the stages when the affiliation they hang on is cancelled.
     *
     * A stage requires the affiliation of its season: without it the member can
     * no longer take part. A line the coach already marked was attended and
     * stays owed — it is dated out like a departure. Every other line is
     * cancelled and its money handed back.
     */
    public function releaseWithAffiliation(Subscription $subscription): void
    {
        $lines = SubscriptionTrainingPack::query()
            ->with('trainingPack')
            ->where('subscription_id', $subscription->id)
            ->where('invoiced_separately', true)
            ->whereNotIn('status', ['cancelled', 'left'])
            ->get();

        foreach ($lines as $line) {
            $pack = $line->trainingPack;

            if ($line->status === 'enrolled' && $this->exit->markedSessionsCount($subscription, $pack) > 0) {
                $line->forceFill(['status' => 'left', 'ends_on' => Carbon::today()->toDateString()])->save();

                continue;
            }

            $line->forceFill([
                'status' => 'cancelled',
                'waitlist_position' => null,
                'confirmation_deadline' => null,
            ])->save();

            $this->sync($line, __('The :season affiliation has been cancelled: the :pack training camp is refunded.', [
                'season' => $subscription->season?->name,
                'pack' => $pack->name,
            ]));
        }
    }

    /**
     * Aligns the line's invoice on what it costs.
     *
     * Returns the newly issued claim when one was created — the caller decides
     * whether to send the invitation — and the amount sent to refund.
     *
     * @param  string  $refundReason  The line the treasurer reads in the refund mail.
     * @return array{issued: Payment|null, refunded: float}
     */
    public function sync(CampEnrolment&Model $line, string $refundReason = ''): array
    {
        $target = $line->isOwed() ? $line->getAmountDue() : 0.0;

        $payments = $line->payments()->get();
        $claims = $this->claims($payments);
        $committed = $this->committedRefunds($payments);

        $delta = round($target - ($claims->sum(fn (Payment $payment): float => (float) $payment->amount_due) - $committed), 2);

        if ($delta > 0.0) {
            return ['issued' => $this->claimMore($line, $claims, $delta), 'refunded' => 0.0];
        }

        if ($delta < 0.0) {
            return ['issued' => null, 'refunded' => $this->claimLess($line, $claims, -$delta, $committed, $refundReason)];
        }

        return ['issued' => null, 'refunded' => 0.0];
    }

    /**
     * @param  Collection<int, Payment>  $claims
     */
    private function claimLess(CampEnrolment&Model $line, Collection $claims, float $excess, float $committed, string $refundReason): float
    {
        foreach ($claims->where('status', 'pending')->sortByDesc('id') as $payment) {
            if ($excess <= 0.0) {
                break;
            }

            // Never below what already came in on this request: the rest is
            // money received, and money received is refunded, not erased.
            $reducible = round((float) $payment->amount_due - (float) $payment->amount_paid, 2);

            if ($reducible <= 0.0) {
                continue;
            }

            $taken = min($excess, $reducible);

            if ($taken >= (float) $payment->amount_due) {
                $payment->update(['status' => 'cancelled']);
            } else {
                $payment->update(['amount_due' => round((float) $payment->amount_due - $taken, 2)]);
                (new AllocateTransactionAction)->settleIfCovered($payment->setRelation('payable', $line));
            }

            $excess = round($excess - $taken, 2);
        }

        $received = round($claims->sum(fn (Payment $payment): float => (float) $payment->amount_paid) - $committed, 2);
        $refund = round(min($excess, max(0.0, $received)), 2);

        if ($refund <= 0.0) {
            return 0.0;
        }

        $payment = (new OpenRefundAction)->forPayable($line, $refund, $line->refundIban());

        User::permission(Permission::PaymentsRefund->value)
            ->get()
            ->each->notify($line->refundRequestedNotification($payment, $refundReason));

        return $refund;
    }

    /**
     * @param  Collection<int, Payment>  $claims
     */
    private function claimMore(CampEnrolment&Model $line, Collection $claims, float $delta): ?Payment
    {
        // An unpaid request grows rather than doubling up: the member reads one
        // structured communication, not two for the same stage.
        $untouched = $claims
            ->filter(fn (Payment $payment): bool => $payment->status === 'pending' && (float) $payment->amount_paid === 0.0)
            ->sortByDesc('id')
            ->first();

        if ($untouched instanceof Payment) {
            $untouched->update(['amount_due' => round((float) $untouched->amount_due + $delta, 2)]);

            return null;
        }

        return $line->payments()->create([
            'reference' => (new GeneratePaymentReference)(),
            'amount_due' => $delta,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);
    }

    /**
     * Requests that still claim something: neither a refund nor a cancelled line.
     *
     * @param  Collection<int, Payment>  $payments
     * @return Collection<int, Payment>
     */
    private function claims(Collection $payments): Collection
    {
        return $payments->filter(
            fn (Payment $payment): bool => $payment->payment_method !== 'refund' && $payment->status !== 'cancelled'
        );
    }

    /**
     * @param  Collection<int, Payment>  $payments
     */
    private function committedRefunds(Collection $payments): float
    {
        return round((float) $payments
            ->filter(fn (Payment $payment): bool => $payment->payment_method === 'refund'
                && in_array($payment->status, Payment::REFUND_COMMITTED_STATUSES, true))
            ->sum(fn (Payment $payment): float => (float) $payment->amount_due), 2);
    }
}
