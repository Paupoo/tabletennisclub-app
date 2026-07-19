<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Shared\Enums\TrainingCancellationType;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackDiscontinuedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stops a pack the club can no longer run: the coach quits, the room is lost.
 *
 * Not to be confused with withdrawing a pack from the offer, which only hides
 * it from new enrolments and leaves the sessions running. This one costs money
 * and sends mail, so the two are separate actions in the UI.
 */
class DiscontinueTrainingPackAction
{
    /**
     * @return array{sessions: int, members: int, refunded: float}
     */
    public function __invoke(TrainingPack $pack, ?string $reason = null): array
    {
        // Waiting and offered members go first: no spot will ever open, and
        // clearing them stops LeaveTrainingPackAction promoting someone into a
        // pack that is being shut down.
        $waitingSubscriptionIds = DB::table('subscription_training_pack')
            ->where('training_pack_id', $pack->id)
            ->whereIn('status', ['waiting', 'offered'])
            ->pluck('subscription_id');

        DB::table('subscription_training_pack')
            ->where('training_pack_id', $pack->id)
            ->whereIn('status', ['waiting', 'offered'])
            ->delete();

        foreach (Subscription::with('user')->whereIn('id', $waitingSubscriptionIds)->get() as $subscription) {
            $subscription->user->notify(new TrainingPackDiscontinuedNotification($pack, $reason));
        }

        // Enrolled and pending members: unenroll, work out what they are owed,
        // and hand the refund to the treasury workflow.
        $committedSubscriptionIds = DB::table('subscription_training_pack')
            ->where('training_pack_id', $pack->id)
            ->whereIn('status', ['enrolled', 'pending'])
            ->pluck('subscription_id');

        $totalRefunded = 0.0;
        $memberCount = 0;

        foreach (Subscription::with('user')->whereIn('id', $committedSubscriptionIds)->get() as $subscription) {
            $refundable = (new LeaveTrainingPackAction)(
                $subscription,
                $pack,
                $subscription->has_other_family_members ? 2 : 1,
                notifyUser: false,
            );

            if ($refundable > 0) {
                (new RequestSubscriptionRefundAction)($subscription, $refundable, __(':pack has been stopped by the club.', [
                    'pack' => $pack->name,
                ]));

                $totalRefunded += $refundable;
            }

            $subscription->user->notify(new TrainingPackDiscontinuedNotification($pack, $reason, $refundable));
            $memberCount++;
        }

        // Only sessions still to come: past ones carry attendance, and an
        // already-cancelled one was announced with its own wording.
        $cancelledSessions = 0;

        Training::where('training_pack_id', $pack->id)
            ->where('status', 'scheduled')
            ->where('start', '>=', Carbon::now())
            ->get()
            ->each(function (Training $training) use ($reason, &$cancelledSessions): void {
                $training->cancel(TrainingCancellationType::CLOSED, $reason);
                $cancelledSessions++;
            });

        $pack->update(['is_active' => false]);

        return [
            'sessions' => $cancelledSessions,
            'members' => $memberCount,
            'refunded' => round($totalRefunded, 2),
        ];
    }
}
