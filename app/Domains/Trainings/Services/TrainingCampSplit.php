<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Services;

use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Support\Facades\DB;

/**
 * Turns a pack encoded before stages existed into a stage, line by line.
 *
 * Only the clean lines move: the stage was added to an affiliation already
 * invoiced, so it received a complement of its own whose `covers` names this
 * pack and nothing else. That payment is re-pointed at the line — not a euro
 * is touched — and the affiliation is recalculated without the stage. The
 * move is kept only if « affiliation + stage » is the same to the cent before
 * and after; otherwise the line stays where it was.
 *
 * Two cases stay with the treasurer, listed by reason:
 * - `first_invoice`: the affiliation had no payment yet, so one payment covers
 *   both the membership fee and the stage;
 * - `price_moved`: the stage moved another price — typically it triggered the
 *   multi-pack discount on another pack — so taking it out would raise the
 *   affiliation and open a balance nobody owes.
 *
 * A clean line keeps the amount it was billed, prorata or discount included:
 * when that differs from the stage's price it is frozen on the line as a
 * forced amount, with its reason, so the member never pays a different sum.
 *
 * A dry run does the whole work inside a transaction and rolls it back, so it
 * reports exactly what the real run will do.
 */
final class TrainingCampSplit
{
    public const string CLEAN = 'clean';

    public const string FIRST_INVOICE = 'first_invoice';

    public const string NOTHING_BILLED = 'nothing_billed';

    public const string PRICE_MOVED = 'price_moved';

    /**
     * @return list<array{member: string, status: string, amount: float, outcome: string}>
     */
    public function run(TrainingPack $pack, bool $dryRun): array
    {
        DB::beginTransaction();

        try {
            $report = $this->split($pack);
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return $report;
    }

    /**
     * The complement issued when this pack alone was added to the affiliation.
     */
    private function complementFor(Subscription $subscription, TrainingPack $pack): ?Payment
    {
        $complements = $subscription->payments()
            ->where('status', '!=', 'cancelled')
            ->where(fn ($q) => $q->where('payment_method', '!=', 'refund')->orWhereNull('payment_method'))
            ->get()
            ->filter(function (Payment $payment) use ($pack): bool {
                $covers = $payment->covers;

                if (! is_array($covers) || ($covers['affiliation'] ?? true)) {
                    return false;
                }

                $ids = collect($covers['training_packs'] ?? [])->pluck('id')->map(fn ($id): int => (int) $id)->all();

                return $ids === [$pack->id];
            });

        return $complements->count() === 1 ? $complements->first() : null;
    }

    /**
     * @return list<array{member: string, status: string, amount: float, outcome: string}>
     */
    private function split(TrainingPack $pack): array
    {
        $pack->forceFill(['is_camp' => true, 'allow_discount' => false])->save();

        $lines = SubscriptionTrainingPack::query()
            ->with(['subscription.user', 'trainingPack'])
            ->where('training_pack_id', $pack->id)
            ->where('invoiced_separately', false)
            ->orderBy('id')
            ->get();

        $report = [];

        foreach ($lines as $line) {
            $outcome = $this->splitLine($line, $pack);

            $report[] = [
                'member' => (string) $line->subscription?->user?->full_name,
                'status' => $line->status,
                'amount' => $line->getAmountDue(),
                'outcome' => $outcome,
            ];
        }

        return $report;
    }

    private function splitLine(SubscriptionTrainingPack $line, TrainingPack $pack): string
    {
        $subscription = $line->subscription;

        // A request, a waiting place, a cancelled line: nothing was billed on
        // the affiliation, the line simply becomes a stage line.
        if (! in_array($line->status, ['enrolled', 'left'], true)) {
            $line->forceFill(['invoiced_separately' => true])->save();

            return self::NOTHING_BILLED;
        }

        $complement = $this->complementFor($subscription, $pack);

        if (! $complement instanceof Payment) {
            return self::FIRST_INVOICE;
        }

        $familyMembersCount = $subscription->has_other_family_members ? 2 : 1;
        $before = (float) $subscription->amount_due;

        // What the affiliation billed for this line, prorata and discount included.
        $lineAmount = (float) ((new CalculatePriceAction)->quote($subscription, $familyMembersCount)['lines'][$pack->id]['amount'] ?? $line->getAmountDue());

        DB::beginTransaction();

        $frozen = round($lineAmount, 2) !== round((float) $pack->price, 2) && $line->override_amount === null;

        $line->forceFill([
            'invoiced_separately' => true,
            'starts_on' => null,
            'override_amount' => $frozen ? (int) round($lineAmount * 100) : $line->override_amount,
            'override_reason' => $frozen ? __('Amount billed before the training camp was invoiced separately') : $line->override_reason,
        ])->save();
        (new CalculatePriceAction)($subscription, $familyMembersCount);
        $after = (float) $subscription->refresh()->amount_due;

        // The same money, split in two: anything else means the stage moved
        // another price, and the affiliation would owe what nobody asked for.
        if (round($after + $lineAmount, 2) !== round($before, 2) || round((float) $complement->amount_due, 2) !== round($lineAmount, 2)) {
            DB::rollBack();
            $subscription->refresh();
            $line->refresh();

            return self::PRICE_MOVED;
        }

        $complement->forceFill([
            'payable_type' => SubscriptionTrainingPack::class,
            'payable_id' => $line->id,
            'covers' => null,
        ])->save();

        // The stage was what kept the affiliation open: with it gone, what came
        // in settles the season.
        $subscription->refresh();
        // The affiliation's own mirror of its payments, recomputed the way
        // AllocateTransactionAction does when money leaves a payable: no
        // payment's amount is touched here.
        $subscription->amount_paid = $subscription->totalPaid();
        $subscription->save();

        if ($subscription->status === 'confirmed' && $subscription->isFullyPaid() && $subscription->totalPaid() > 0) {
            $subscription->markAsPaid();
        }

        DB::commit();

        return self::CLEAN;
    }
}
