<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dates the affiliations that were validated before the column existed.
 *
 * The audit log holds the transition for every affiliation the office moved by
 * hand, and that is the date the club will certify. It does not hold the ones
 * created straight in `confirmed` — a seeder, a federation import — so those
 * fall back to their creation date, which is the day the club took them on.
 *
 * Only `confirmed` and `paid` are dated: a pending request has not been
 * validated, and a cancelled one never will be.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('subscriptions')
            ->whereNull('confirmed_at')
            ->whereIn('status', ['confirmed', 'paid'])
            ->orderBy('id')
            ->each(function (object $subscription): void {
                DB::table('subscriptions')
                    ->where('id', $subscription->id)
                    ->update(['confirmed_at' => $this->validatedAt($subscription)]);
            });
    }

    /**
     * The earliest logged move into a validated status, or the creation date.
     *
     * Earliest rather than latest: an affiliation confirmed, unconfirmed and
     * confirmed again was first taken on at the first date, and that is the one
     * the period on the attestation starts from.
     */
    private function validatedAt(object $subscription): string
    {
        $logged = DB::table('activity_log')
            ->where('subject_type', Subscription::class)
            ->where('subject_id', $subscription->id)
            ->where(function ($query): void {
                $query->where('properties', 'like', '%"status":"confirmed"%')
                    ->orWhere('properties', 'like', '%"status":"paid"%');
            })
            ->orderBy('created_at')
            ->value('created_at');

        return (string) ($logged ?? $subscription->created_at);
    }
};
