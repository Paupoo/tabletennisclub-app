<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Services;

use App\Data\Attestation\AttestationVerdict;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\AttestationRefusal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Decides whether the club may certify a member's participation in a camp.
 *
 * Both conditions the office set: the member is in order for the camp's
 * season — the very rule the affiliation attestation applies, so « en ordre »
 * means one thing — and the camp itself is paid in full.
 *
 * Two more the forms impose. The camp is over: every insurer writes « a
 * participé » in the past tense, and a certificate issued on the Monday would
 * state a week that has not happened. And the camp's money is on its own line:
 * a camp still billed inside the affiliation cannot be told apart from the
 * cotisation, and the club does not seal a sum it reconstructed.
 */
final readonly class CampAttestationEligibility
{
    /** How long after its last day a camp still appears — the life of the file. */
    public const int WINDOW_MONTHS = 12;

    public function __construct(
        private AttestationEligibility $affiliation,
        private CampDaysCounter $days,
    ) {}

    /**
     * The sum the document states: what stayed with the club, never above the price.
     *
     * An overpayment not yet sent back is money the club owes, not money the
     * family spent on the camp; certifying it would have the insurer reimburse
     * a refund.
     */
    public function certifiedAmount(SubscriptionTrainingPack $line): float
    {
        return round(min($this->paidOn($line), $line->getAmountDue()), 2);
    }

    public function for(SubscriptionTrainingPack $line): AttestationVerdict
    {
        $line->loadMissing(['trainingPack', 'subscription.season', 'subscription.user']);

        if (! $line->invoiced_separately || ! $line->isOwed() || $line->trainingPack?->is_camp !== true) {
            return AttestationVerdict::refuse(AttestationRefusal::NotCertifiable, line: $line);
        }

        $last = $this->days->for($line)->last();

        if (! $last instanceof Carbon) {
            return AttestationVerdict::refuse(AttestationRefusal::NotCertifiable, line: $line);
        }

        if (Carbon::today()->lte($last)) {
            return AttestationVerdict::refuse(
                AttestationRefusal::CampNotOver,
                line: $line,
                availableOn: $last->copy()->addDay(),
            );
        }

        $season = $line->subscription->season;
        $affiliationVerdict = $this->affiliation->for($line->subscription->user, $season);

        if (! $affiliationVerdict->allowed) {
            return AttestationVerdict::refuse(
                $affiliationVerdict->refusal === AttestationRefusal::NoAffiliation
                    ? AttestationRefusal::CampNoAffiliation
                    : $affiliationVerdict->refusal,
                $affiliationVerdict->affiliation,
                $affiliationVerdict->balanceDue,
                $line,
            );
        }

        $owed = round($line->getAmountDue() - $this->paidOn($line), 2);

        if ($owed > 0.01) {
            return AttestationVerdict::refuse(AttestationRefusal::CampBalanceDue, $affiliationVerdict->affiliation, $owed, $line);
        }

        return AttestationVerdict::allow($affiliationVerdict->affiliation, $line);
    }

    /**
     * The camp lines a member can ask about today, latest camp first.
     *
     * A line still billed inside the affiliation is left out altogether: the
     * office fills those in by hand, and a card that can only say « ask the
     * secretary » is noise for the families it does not concern.
     *
     * @return Collection<int, SubscriptionTrainingPack>
     */
    public function linesFor(User $member): Collection
    {
        $since = Carbon::today()->subMonths(self::WINDOW_MONTHS);

        return SubscriptionTrainingPack::query()
            ->with(['trainingPack', 'subscription.season'])
            ->whereIn('subscription_id', Subscription::where('user_id', $member->id)->select('id'))
            ->where('invoiced_separately', true)
            ->whereIn('status', ['enrolled', 'left'])
            ->get()
            ->filter(fn (SubscriptionTrainingPack $line): bool => $line->trainingPack?->is_camp === true)
            ->map(fn (SubscriptionTrainingPack $line): array => [$line, $this->days->for($line)->last()])
            ->filter(fn (array $pair): bool => $pair[1] instanceof Carbon && $pair[1]->gte($since))
            ->sortByDesc(fn (array $pair): string => $pair[1]->toDateString())
            ->map(fn (array $pair): SubscriptionTrainingPack => $pair[0])
            ->values();
    }

    /**
     * What reached the club for this line and stayed there.
     *
     * Received on every request that still claims something, minus the refunds
     * already committed — the same reading as the affiliation's net amount, so
     * a trop-perçu sent back is not certified as paid.
     */
    public function paidOn(SubscriptionTrainingPack $line): float
    {
        $payments = $line->payments()->get();

        $received = $payments
            ->filter(fn (Payment $payment): bool => $payment->payment_method !== 'refund' && $payment->status !== 'cancelled')
            ->sum(fn (Payment $payment): float => (float) $payment->amount_paid);

        $refunded = $payments
            ->filter(fn (Payment $payment): bool => $payment->payment_method === 'refund'
                && in_array($payment->status, Payment::REFUND_COMMITTED_STATUSES, true))
            ->sum(fn (Payment $payment): float => (float) $payment->amount_due);

        return round($received - $refunded, 2);
    }
}
