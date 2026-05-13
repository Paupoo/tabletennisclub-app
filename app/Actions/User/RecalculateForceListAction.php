<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\ClubAdmin\Users\User;

class RecalculateForceListAction
{
    /**
     * Recalculates force_list for all competitors, ordered by ranking (A1 → NC).
     * Uses updateQuietly to avoid re-triggering the observer.
     */
    public static function handle(): void
    {
        User::where('is_competitor', true)
            ->orderBy('ranking')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id'])
            ->values() // reset to 0-based integer keys — each() passes the key as $index
            ->each(function (User $user, int $index): void {
                $user->updateQuietly(['force_list' => $index + 1]);
            });
    }
}
