<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Health snapshot of the database queue (jobs / failed_jobs tables).
 *
 * The worker never reports its own state: a healthy worker keeps the pending
 * queue young, so a pending job older than STALLED_AFTER_MINUTES means the
 * worker is most likely down — mails and notifications silently pile up.
 */
class QueueHealth
{
    public const int STALLED_AFTER_MINUTES = 10;

    public static function failedCount(): int
    {
        return DB::table('failed_jobs')->count();
    }

    public static function isStalled(): bool
    {
        return (self::oldestPendingMinutes() ?? 0) > self::STALLED_AFTER_MINUTES;
    }

    /**
     * Age in minutes of the oldest pending job, null when the queue is empty.
     */
    public static function oldestPendingMinutes(): ?int
    {
        $oldest = DB::table('jobs')->min('created_at');

        if ($oldest === null) {
            return null;
        }

        return (int) Carbon::createFromTimestamp((int) $oldest)->diffInMinutes(now());
    }

    public static function pendingCount(): int
    {
        return DB::table('jobs')->count();
    }
}
