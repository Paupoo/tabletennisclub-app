<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Models\ClubAdmin\Subscription\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingWaitlistSpotOfferedNotification;
use Illuminate\Support\Facades\DB;

class PromoteFromTrainingWaitlistAction
{
    public function __invoke(TrainingPack $pack): void
    {
        // Find the subscription with the lowest waitlist position for this pack
        $pivot = DB::table('subscription_training_pack')
            ->where('training_pack_id', $pack->id)
            ->where('status', 'waiting')
            ->orderBy('waitlist_position')
            ->first();

        if (! $pivot) {
            return;
        }

        $deadline = now()->addHours(48);

        DB::table('subscription_training_pack')
            ->where('id', $pivot->id)
            ->update([
                'status' => 'offered',
                'waitlist_position' => null,
                'confirmation_deadline' => $deadline,
            ]);

        // Renumber remaining waitlist positions
        DB::table('subscription_training_pack')
            ->where('training_pack_id', $pack->id)
            ->where('status', 'waiting')
            ->orderBy('waitlist_position')
            ->get()
            ->each(function (object $row, int $index): void {
                DB::table('subscription_training_pack')
                    ->where('id', $row->id)
                    ->update(['waitlist_position' => $index + 1]);
            });

        $subscription = Subscription::find($pivot->subscription_id);
        if ($subscription) {
            $subscription->user->notify(
                new TrainingWaitlistSpotOfferedNotification($pack, $deadline)
            );
        }
    }
}
