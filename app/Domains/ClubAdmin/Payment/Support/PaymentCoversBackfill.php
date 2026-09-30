<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Support;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuild what the subscription payments made before `covers` existed billed.
 *
 * Read from the dates: a subscription's first payment is the affiliation with
 * the packs attached by then; each later one bills the packs attached since
 * the previous payment — a pack added afterwards. A later payment with no pack
 * in its window (a formula change, a pack re-enrolled on its old row) is left
 * unknown and keeps the "Affiliation 2026-2027" label it always had.
 *
 * Idempotent: a payment that already says what it covers is left alone.
 */
final class PaymentCoversBackfill
{
    /**
     * A payment is created a few seconds after the packs it bills are
     * attached: anything attached up to this long after still belongs to it.
     */
    private const int GRACE_SECONDS = 300;

    /**
     * @return array{affiliation: int, packs: int, unknown: int}
     */
    public function run(bool $dryRun = false): array
    {
        $report = ['affiliation' => 0, 'packs' => 0, 'unknown' => 0];

        // A dry run is meant to be read before the migration that adds the
        // column: without it, every payment is one that does not say yet.
        $hasColumn = Schema::hasColumn('payments', 'covers');
        abort_if(! $hasColumn && ! $dryRun, 500, 'Run the migration that adds payments.covers first.');

        Subscription::query()
            ->withTrashed()
            ->whereHas('payments', fn ($payments) => $hasColumn ? $payments->whereNull('covers') : $payments)
            ->orderBy('subscriptions.id')
            ->chunkById(100, function ($subscriptions) use (&$report, $dryRun): void {
                foreach ($subscriptions as $subscription) {
                    $this->rebuild($subscription, $report, $dryRun);
                }
            });

        return $report;
    }

    /**
     * @param  array{affiliation: int, packs: int, unknown: int}  $report
     */
    private function rebuild(Subscription $subscription, array &$report, bool $dryRun): void
    {
        $claims = $subscription->payments()
            ->where(fn ($query) => $query->where('payment_method', '!=', 'refund')->orWhereNull('payment_method'))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $attachments = DB::table('subscription_training_pack')
            ->join('training_packs', 'training_packs.id', '=', 'subscription_training_pack.training_pack_id')
            ->where('subscription_training_pack.subscription_id', $subscription->id)
            ->orderBy('subscription_training_pack.created_at')
            ->orderBy('training_packs.id')
            ->get(['training_packs.id', 'training_packs.name', 'subscription_training_pack.created_at']);

        $previous = null;

        foreach ($claims as $index => $payment) {
            $upTo = Carbon::parse($payment->created_at)->addSeconds(self::GRACE_SECONDS);
            $from = $previous === null ? null : Carbon::parse($previous->created_at)->addSeconds(self::GRACE_SECONDS);
            $previous = $payment;

            if ($payment->covers !== null) {
                continue;
            }

            $packs = $attachments
                ->filter(fn (object $row): bool => Carbon::parse($row->created_at)->lessThanOrEqualTo($upTo)
                    && ($from === null || Carbon::parse($row->created_at)->greaterThan($from)))
                ->map(fn (object $row): array => ['id' => (int) $row->id, 'name' => (string) $row->name])
                ->values()
                ->all();

            $covers = match (true) {
                $index === 0 => ['affiliation' => true, 'reason' => null, 'training_packs' => $packs],
                $packs !== [] => ['affiliation' => false, 'reason' => null, 'training_packs' => $packs],
                default => null,
            };

            $report[$covers === null ? 'unknown' : ($covers['affiliation'] ? 'affiliation' : 'packs')]++;

            if ($covers !== null && ! $dryRun) {
                Payment::query()->whereKey($payment->id)->update(['covers' => json_encode($covers)]);
            }
        }
    }
}
