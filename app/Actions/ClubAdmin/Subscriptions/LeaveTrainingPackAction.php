<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubEvents\Training\TrainingPack;
use App\Notifications\Training\TrainingPackCancelledNotification;
use Illuminate\Support\Facades\DB;

class LeaveTrainingPackAction
{
    public function __invoke(Subscription $subscription, TrainingPack $pack, int $familyMembersCount = 1): void
    {
        $pivot = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->where('training_pack_id', $pack->id)
            ->first();

        if (! $pivot) {
            return;
        }

        $wasEnrolled = $pivot->status === 'enrolled';
        $wasPending = $pivot->status === 'pending';

        $subscription->trainingPacks()->detach($pack->id);

        if ($wasPending) {
            $subscription->user->notify(new TrainingPackCancelledNotification($pack, $subscription));
        }

        (new CalculatePriceAction)($subscription, $familyMembersCount);

        // Promote next waitlisted user only if a confirmed spot was freed
        if ($wasEnrolled && $pack->waitlistCount() > 0) {
            (new PromoteFromTrainingWaitlistAction)($pack);
        }
    }
}
