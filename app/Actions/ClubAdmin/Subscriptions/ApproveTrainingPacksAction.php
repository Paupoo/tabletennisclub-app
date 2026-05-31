<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use Illuminate\Support\Facades\DB;

class ApproveTrainingPacksAction
{
    public function __invoke(Subscription $subscription, array $approvedPackIds, int $familyMembersCount = 1): void
    {
        $pendingPackIds = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->pluck('training_pack_id')
            ->toArray();

        foreach ($pendingPackIds as $packId) {
            if (in_array($packId, $approvedPackIds, true)) {
                DB::table('subscription_training_pack')
                    ->where('subscription_id', $subscription->id)
                    ->where('training_pack_id', $packId)
                    ->update(['status' => 'enrolled']);
            } else {
                $subscription->trainingPacks()->detach($packId);
            }
        }

        (new CalculatePriceAction)($subscription, $familyMembersCount);
    }
}
